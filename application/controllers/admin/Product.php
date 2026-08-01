<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Product extends MY_Admin_Controller {

	protected $currentUser;

	function __construct()
	{
		parent::__construct();
		$this->load->helper('permission');
		$this->load->model('product_model');
		$this->load->model('catalog_model');
		$this->load->model('product_variant_model');
		$this->load->library('product_service');
		$this->load->library('form_validation');
		$this->load->helper('form');
		$this->load->library('upload');
		$this->load->library('upload_library');

		$this->currentUser = $this->session->userdata('login');

		if (!$this->currentUser) {
			redirect(admin_url('login'));
		}

		if (!admin_can('product.manage', $this->currentUser)) {
			admin_flash_fail( 'Bạn không có quyền truy cập khu vực Sản phẩm!', 'dashboard');
			redirect(admin_url('home')); 
		}
	}

	public function index()
	{
		$search_name = $this->input->get('name');
		$search_catalog = $this->input->get('catalog_id');
		$search_status = $this->input->get('status');
		if (!in_array((string) $search_status, array('0', '1'), true)) {
			$search_status = '';
		}
		$has_status_col = $this->db->field_exists('status', 'product');
		
		$input_total = array();
		$where_total = array();
		if (!empty($search_name)) {
			$input_total['like'] = array('name', $search_name);
		}
		if (!empty($search_catalog)) {
			$where_total['catalog_id'] = $search_catalog;
		}
		if ($has_status_col && $search_status !== '') {
			$where_total['status'] = (int) $search_status;
		}
		if (!empty($where_total)) {
			$input_total['where'] = $where_total;
		}
		$total = $this->product_model->get_total($input_total);
		$this->data['total'] = $total;

		$this->load->library('pagination');
		$config = array();
		$base_url = admin_url('product/index');
		$per = 10;
		$uri = 4;
		
		$config = pagination($base_url, $total, $per, $uri);
		$config['page_query_string'] = FALSE;
		$config['reuse_query_string'] = FALSE;

		$admin_query = array();
		if (!empty($search_name)) {
			$admin_query['name'] = $search_name;
		}
		if (!empty($search_catalog)) {
			$admin_query['catalog_id'] = (int) $search_catalog;
		}
		if ($search_status !== '') {
			$admin_query['status'] = (int) $search_status;
		}
		if (!empty($admin_query)) {
			$config['suffix'] = '?' . http_build_query($admin_query);
		}
		
		$this->pagination->initialize($config);

		$segment = isset($this->uri->segments['4']) ? $this->uri->segments['4'] : NULL;
		$segment = intval($segment);
		
		$input = array();
		$input['limit'] = array($config['per_page'], $segment);
		$where_list = array();
		
		if (!empty($search_name)) {
			$input['like'] = array('product.name', $search_name);
		}
		if (!empty($search_catalog)) {
			$where_list['product.catalog_id'] = $search_catalog;
		}
		if ($has_status_col && $search_status !== '') {
			$where_list['product.status'] = (int) $search_status;
		}
		if (!empty($where_list)) {
			$input['where'] = $where_list;
		}

		$select_status = $has_status_col ? ', product.status as status' : '';
		$this->db->select('
			product.id as id,
			product.name as name,
			price,
			discount,
			image_link,
			view,
			buyed,
			rate_count,
			rate_total,
			color,
			size,
			quantity,
			catalog.name as namecatalog' . $select_status . '
		');
		$this->db->join('catalog', 'catalog.id = product.catalog_id');
		
		$product = $this->product_model->get_list($input);
		$this->data['product'] = $product;
		
		$this->data['catalog'] = $this->list_catalog();
		
		$this->data['search_name'] = $search_name;
		$this->data['search_catalog'] = $search_catalog;
		$this->data['search_status'] = $search_status;
		$this->data['has_product_status'] = $has_status_col;
		
		$this->render_admin('admin/products/index');
	}

	public function add()
	{
		$this->data['catalog'] = $this->list_catalog();
		$this->load->helper('product');
		$this->data['available_colors'] = product_master_colors();
		$this->data['available_sizes'] = product_master_sizes();
		$this->form_validation->set_error_delimiters('<div class="alert alert-danger" role="alert" style="padding:5px;border-bottom:0px;">', '</div>');

		if ($this->input->post()) {
			$this->form_validation->set_rules('name','Tên sản phẩm','required');
			$this->form_validation->set_rules('catalog_id','Danh mục','required');
			$this->form_validation->set_rules('price','Giá sản phẩm','required|callback__validate_non_negative_price');
			
			if ($this->form_validation->run()) {
				$price_value = $this->_parse_price($this->input->post('price'));
				if ($price_value < 0) {
					admin_flash_fail('Giá sản phẩm không được âm.');
					redirect(admin_url('products/add'));
				}

				$path = './upload/product/';
				$image_link = $this->upload_library->upload($path, 'image');
				if (empty($image_link)) {
					admin_flash_fail(
						'Không tải được ảnh đại diện. ' . $this->upload_library->last_error()
					);
				} else {
				$image_list = array();
				$image_list = $this->upload_library->upload_file($path,'list_image');
				$gallery_warning = '';
				if (!empty($this->upload_library->last_error()) && empty($image_list)) {
					$gallery_warning = ' Ảnh kèm theo: ' . $this->upload_library->last_error();
				}
				$image_list = json_encode($image_list);

				$color_array = $this->input->post('color');
				if (is_array($color_array) && !empty($color_array)) {
					$color_string = implode(',', $color_array);
				} else {
					$color_string = NULL;
				}

				$size_array = $this->input->post('size');
				if (is_array($size_array) && !empty($size_array)) {
					$size_string = implode(',', $size_array);
				} else {
					$size_string = NULL;
				}

				$discount_fields = $this->_build_discount_fields(
					$price_value,
					$this->input->post('discount')
				);

				$data = array(
					'name'        => $this->input->post('name'),
					'image_link'  => $image_link,
					'image_list'  => $image_list,
					'content'     => $this->input->post('content'),
					'catalog_id'  => $this->input->post('catalog_id'),
					'price'       => $price_value,
					'discount'    => $discount_fields['discount'],
					'discount_type' => $discount_fields['discount_type'],
					'discount_percent' => $discount_fields['discount_percent'],
					'color'       => $color_string,
					'size'        => $size_string,
					'quantity'    => 0,
					'status'      => 1,
					'created'     => now()
				);

				if ($this->product_model->create($data)) {
					$product_id = (int) $this->db->insert_id();
					$code = 'SP' . str_pad((string) $product_id, 5, '0', STR_PAD_LEFT);
					$this->product_model->update($product_id, array('code' => $code));

					$colors = is_array($color_array) ? $color_array : array();
					$sizes = is_array($size_array) ? $size_array : array();
					$this->product_service->sync_variants($product_id, $colors, $sizes, $price_value);

					admin_flash_success(
						'Sản phẩm đã tạo thành công. Tồn kho hiện tại = 0. Hãy tạo phiếu nhập để cập nhật số lượng thực tế.' . $gallery_warning
					);
				}else{
					admin_flash_fail('Thêm sản phẩm thất bại');
				}
				redirect(admin_url('product'));
				}
			}
		}

		$this->render_admin('admin/products/add');
	}

	public function edit()
	{
		$id = $this->uri->rsegment(3);
		$product = $this->product_model->get_info($id);
		if(!$product) {
			admin_flash_fail( 'Không tồn tại sản phẩm này');
			redirect(admin_url('product'));
		}

		$this->form_validation->set_rules('name', 'Tên sản phẩm', 'required');
		$this->form_validation->set_rules('catalog_id', 'Danh mục', 'required');
		$this->form_validation->set_rules('price', 'Giá tiền', 'required|callback__validate_non_negative_price');

	if($this->form_validation->run())
	{
		$old_price = (float) $product->price;
		$price    = $this->_parse_price($this->input->post('price'));
		if ($price < 0) {
			admin_flash_fail('Giá sản phẩm không được âm.');
			redirect(admin_url('product/edit/' . $product->id));
		}
		$discount_fields = $this->_build_discount_fields(
			$price,
			$this->input->post('discount')
		);
		$discount = $discount_fields['discount'];

		$color_array = $this->input->post('color'); 
		if (is_array($color_array) && !empty($color_array)) {
			$color_string = implode(',', $color_array);
		} else {
			$color_string = NULL;
		}

		$size_array = $this->input->post('size'); 
		if (is_array($size_array) && !empty($size_array)) {
			$size_string = implode(',', $size_array);
		} else {
			$size_string = NULL;
		}

		$data = array(
			'name'       => $this->input->post('name'),
			'catalog_id' => $this->input->post('catalog_id'),
			'price'      => $price,
			'discount'   => $discount,
			'discount_type' => $discount_fields['discount_type'],
			'discount_percent' => $discount_fields['discount_percent'],
			'content'    => $this->input->post('content'),
			'color'      => $color_string,
			'size'       => $size_string,
		);

		if ($this->db->field_exists('status', 'product')) {
			$data['status'] = ($this->input->post('status') === '0') ? 0 : 1;
		}

		$this->load->library('upload_library');
		$upload_path = './upload/product/';

		$this->_apply_product_images_on_edit($product, $upload_path, $data);

		if($this->product_model->update($product->id, $data)) {
			$colors = is_array($color_array) ? $color_array : array();
			$sizes = is_array($size_array) ? $size_array : array();
			$this->product_service->sync_variants($product->id, $colors, $sizes, $price, 0, $old_price);
			$this->_save_variant_edits($product->id);
			admin_flash_success( 'Cập nhật sản phẩm thành công!');
		} else {
			admin_flash_fail( 'Có lỗi xảy ra, không thể cập nhật.');
		}
		redirect(admin_url('product'));
	}

		$this->load->model('catalog_model');
		$this->load->helper('product');
		$this->data['catalog'] = $this->list_catalog();
		$this->data['product'] = $product;
		$this->data['variants'] = $this->product_variant_model->get_by_product($product->id);

		$extra_colors = !empty($product->color) ? explode(',', (string) $product->color) : array();
		$extra_sizes = !empty($product->size) ? explode(',', (string) $product->size) : array();
		$this->data['available_colors'] = product_master_colors($extra_colors);
		$this->data['available_sizes'] = product_master_sizes($extra_sizes);

		$this->render_admin('admin/products/edit');
	}

	public function del()
	{
		if (!$this->input->is_ajax_request() && !$this->input->post()) {
			show_404();
			return;
		}

		if (!admin_can('product.delete_single', $this->currentUser)) {
			$this->_product_del_response(false);
			return;
		}

		$id = (int) $this->input->post('id');
		if ($id <= 0) {
			$this->_product_del_response(false);
			return;
		}

		$this->_product_del_response($this->_delete_products_by_ids(array($id)) > 0);
	}

	protected function _product_del_response($ok)
	{
		$this->load->helper('csrf');
		if ($this->input->is_ajax_request()) {
			$this->output->set_content_type('application/json', 'utf-8')
				->set_output(json_encode(array_merge(array(
					'ok' => (bool) $ok,
					'status' => $ok ? 'success' : 'failer',
				), csrf_json_meta()), JSON_UNESCAPED_UNICODE));
			return;
		}

		echo $ok ? 'success' : 'failer';
	}

	public function bulk_del()
	{
		if (!$this->input->post()) {
			show_404();
			return;
		}

		if (!admin_can('product.bulk_delete', $this->currentUser)) {
			if ($this->input->is_ajax_request()) {
				echo 'failer';
				return;
			}
			admin_flash_fail( 'Tài khoản nhân viên (MOD) không có quyền xóa hàng loạt sản phẩm!');
			redirect(admin_url('product'));
			return;
		}

		$checkbox = $this->input->post('checkbox');
		if (!is_array($checkbox) || empty($checkbox)) {
			if ($this->input->is_ajax_request()) {
				echo 'failer';
				return;
			}
			admin_flash_fail( 'Không có sản phẩm nào được chọn');
			redirect(admin_url('product'));
			return;
		}

		$deleted = $this->_delete_products_by_ids($checkbox);

		if ($this->input->is_ajax_request()) {
			echo $deleted > 0 ? 'success' : 'failer';
			return;
		}

		$selected = count(array_filter(array_map('intval', $checkbox)));
		if ($deleted > 0) {
			$msg = 'Xóa ' . $deleted . ' sản phẩm thành công';
			if ($deleted < $selected) {
				$msg .= ' (' . ($selected - $deleted) . ' sản phẩm không xóa được)';
			}
			admin_flash_success( $msg);
		} else {
			admin_flash_fail( 'Không xóa được sản phẩm đã chọn. Vui lòng thử lại.');
		}
		redirect(admin_url('product'));
	}

	private function _delete_products_by_ids(array $ids)
	{
		$deleted = 0;
		$ids = array_values(array_unique(array_filter(array_map('intval', $ids))));

		foreach ($ids as $id) {
			if ($id <= 0) {
				continue;
			}

			$product = $this->product_model->get_info($id);
			if (!$product) {
				continue;
			}

			$this->_delete_product_related($id);
			$this->_delete_product_assets($product);

			$this->db->where('id', $id);
			$this->db->delete('product');
			if ($this->db->affected_rows() > 0) {
				$deleted++;
			}
		}

		return $deleted;
	}

	private function _delete_product_related($product_id)
	{
		$product_id = (int) $product_id;
		if ($product_id <= 0) {
			return;
		}

		$this->db->where('product_id', $product_id)->delete('order');

		if ($this->db->table_exists('product_review')) {
			$this->db->where('product_id', $product_id)->delete('product_review');
		}

		if ($this->db->table_exists('product_variants')) {
			$this->db->where('product_id', $product_id)->delete('product_variants');
		}
	}

	private function _save_variant_edits($product_id)
	{
		$raw = $this->input->post('variant');
		if (!is_array($raw)) {
			return;
		}

		$product_id = (int) $product_id;
		$now = time();

		foreach ($raw as $variant_id => $fields) {
			$variant_id = (int) $variant_id;
			if ($variant_id <= 0 || !is_array($fields)) {
				continue;
			}

			$variant = $this->product_variant_model->get_info($variant_id);
			if (!$variant || (int) $variant->product_id !== $product_id) {
				continue;
			}

			$update = array('updated' => $now);

			if (isset($fields['price'])) {
				$price = (int) str_replace(',', '', (string) $fields['price']);
				if ($price >= 0) {
					$update['price'] = $price;
				}
			}
			if (isset($fields['cost_price'])) {
				$cost = (int) str_replace(',', '', (string) $fields['cost_price']);
				if ($cost >= 0) {
					$update['cost_price'] = $cost;
				}
			}
			if (isset($fields['min_stock'])) {
				$update['min_stock'] = max(0, (int) $fields['min_stock']);
			}
			if (isset($fields['status'])) {
				$update['status'] = ((string) $fields['status'] === '1') ? 1 : 0;
			}

			if (count($update) > 1) {
				$this->product_variant_model->update($variant_id, $update);
			}
		}

		$this->product_service->sync_product_quantity_cache($product_id);
	}

	private function _parse_price($raw)
	{
		return (int) str_replace(',', '', (string) $raw);
	}

	public function _validate_non_negative_price($str)
	{
		$price = (int) str_replace(',', '', (string) $str);
		if ($price < 0) {
			$this->form_validation->set_message('_validate_non_negative_price', 'Giá sản phẩm không được âm.');
			return false;
		}
		return true;
	}

	private function _parse_discount_amount($price, $raw_discount)
	{
		$fields = $this->_build_discount_fields($price, $raw_discount);
		return (int) $fields['discount'];
	}

	private function _build_discount_fields($price, $raw_discount)
	{
		$price = (int) $price;
		$raw = str_replace(',', '', (string) $raw_discount);

		if ($raw === '' || !is_numeric($raw)) {
			return array(
				'discount' => 0,
				'discount_type' => 'percent',
				'discount_percent' => 0,
			);
		}

		$percent = (float) $raw;
		if ($percent < 0) {
			$percent = 0;
		}
		if ($percent > 100) {
			$percent = 100;
		}

		$amount = ($price > 0) ? (int) round($price * $percent / 100) : 0;

		return array(
			'discount' => $amount,
			'discount_type' => 'percent',
			'discount_percent' => (int) round($percent),
		);
	}

	private function _sanitize_image_filename($name)
	{
		$name = basename(str_replace('\\', '/', (string) $name));
		if ($name === '' || strpos($name, '..') !== false) {
			return '';
		}
		return $name;
	}

	private function _apply_product_images_on_edit($product, $upload_path, array &$data)
	{
		$main = $this->_sanitize_image_filename($this->input->post('product_image_main'));
		$list = json_decode($this->input->post('product_image_list'), true);
		if (!is_array($list)) {
			$list = array();
		}

		$list = array_values(array_unique(array_filter(array_map(array($this, '_sanitize_image_filename'), $list))));
		$list = array_values(array_filter($list, function ($filename) use ($main) {
			return $filename !== '' && $filename !== $main;
		}));

		$removed = $this->input->post('product_images_remove');
		if (!is_array($removed)) {
			$removed = array();
		}
		$removed = array_unique(array_filter(array_map(array($this, '_sanitize_image_filename'), $removed)));

		if (!empty($_FILES['image']['name'])) {
			$uploaded_main = $this->upload_library->upload($upload_path, 'image');
			if (empty($uploaded_main)) {
				admin_flash_fail(
					'Không cập nhật được ảnh đại diện. ' . $this->upload_library->last_error()
				);
				redirect(admin_url('product/edit/' . $product->id));
				return;
			}
			if ($main !== '' && $main !== $uploaded_main) {
				$removed[] = $main;
			}
			$main = $this->_sanitize_image_filename($uploaded_main);
		}

		if (!empty($_FILES['list_image']['name'][0])) {
			$new_gallery = $this->upload_library->upload_file($upload_path, 'list_image');
			if (!empty($new_gallery)) {
				foreach ($new_gallery as $filename) {
					$filename = $this->_sanitize_image_filename($filename);
					if ($filename !== '' && $filename !== $main) {
						$list[] = $filename;
					}
				}
				$list = array_values(array_unique($list));
			}
		}

		$data['image_link'] = $main !== '' ? $main : null;
		$data['image_list'] = json_encode($list);

		$still_used = $list;
		if ($main !== '') {
			$still_used[] = $main;
		}

		$upload_dir = rtrim(FCPATH, '/\\') . DIRECTORY_SEPARATOR . 'upload' . DIRECTORY_SEPARATOR . 'product' . DIRECTORY_SEPARATOR;
		foreach ($removed as $filename) {
			if (in_array($filename, $still_used, true)) {
				continue;
			}
			$path = $upload_dir . $filename;
			if (is_file($path)) {
				@unlink($path);
			}
		}
	}

	private function _delete_product_assets($product)
	{
		if (!$product) {
			return;
		}

		$upload_dir = rtrim(FCPATH, '/\\') . DIRECTORY_SEPARATOR . 'upload' . DIRECTORY_SEPARATOR . 'product' . DIRECTORY_SEPARATOR;
		$files = array();

		if (!empty($product->image_link)) {
			$files[] = $product->image_link;
		}

		$image_list = json_decode($product->image_list, true);
		if (is_array($image_list)) {
			foreach ($image_list as $img_value) {
				if (is_string($img_value) && $img_value !== '') {
					$files[] = $img_value;
				}
			}
		}

		$files = array_unique(array_filter(array_map(function ($name) {
			return ltrim(str_replace('\\', '/', (string) $name), '/');
		}, $files)));

		foreach ($files as $file_name) {
			if ($file_name === '' || strpos($file_name, '..') !== false) {
				continue;
			}
			$path = $upload_dir . $file_name;
			if (is_file($path)) {
				@unlink($path);
			}
		}
	}

	protected function list_catalog()
	{
		$input = array();
		$input['where'] = array('parent_id' => '1');
		$input['order'] = array('sort_order' , 'asc');
		$catalog = $this->catalog_model->get_list($input);
		foreach ($catalog as $value) {
			$input['where'] = array('parent_id' => $value->id);
			$subs = $this->catalog_model->get_list($input);
			$value->sub = $subs;
		}
		return $catalog;
	}
}
