<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Controller quản lý danh mục sản phẩm phía admin (thêm, sửa, xóa, sắp xếp 3 cấp).
 */
class Catalog extends MY_Admin_Controller {

	protected $currentUser;

	function __construct()
	{
		parent::__construct();
		$this->load->model('catalog_model');
		$this->load->library('form_validation');
		$this->load->helper('form');

		$this->currentUser = $this->session->userdata('login');

		if (!$this->currentUser) {
			redirect(admin_url('login'));
		}

		if (!admin_can('catalog.manage', $this->currentUser)) {
			admin_flash_fail( 'Bạn không có quyền quản lý danh mục sản phẩm!', 'dashboard');
			redirect(admin_url('home'));
		}
	}

	public function index()
	{

		$input = array();
		$input['order'] = array('id' , 'ASC');
		$list = $this->catalog_model->get_list($input);
		$this->data['list']= $list;

		$this->render_admin('admin/catalog/index');
	}

	public function add()
	{
		$input = array();
		$input['where'] = array('parent_id <' => '2');
		$list = $this->catalog_model->get_list($input);
		$this->data['list']= $list;

		$this->form_validation->set_error_delimiters('<div class="alert alert-danger" role="alert" style="padding:5px;border-bottom:0px;">', '</div>');

		if ($this->input->post()) {
			$this->form_validation->set_rules('name','Tên danh mục','required');
			if ($this->form_validation->run()) {
				$data = array(
					'name' => $this->input->post('name'),
					'description' => $this->input->post('description'),
					'parent_id' => $this->input->post('parent_id'),
					'sort_order' => $this->input->post('sort_order'),
					'created' => now()
				);
				if ($this->catalog_model->create($data)) {
					admin_flash_success( 'Thêm danh mục thành công');
				}else{
					admin_flash_fail( 'Thêm danh mục thất bại');
				}
				redirect(admin_url('catalog'));
			}
		}

		$this->render_admin('admin/catalog/add');
	}

	public function edit()
	{
		$id = $this->uri->segment(4);
		$catalog = $this->catalog_model->get_info($id);
		if (empty($catalog)) {
			admin_flash_fail( 'Danh mục không tồn tại');
			redirect(admin_url('catalog'));
		}
		$this->data['catalog'] = $catalog;

		if ($this->input->post()) {
			$this->form_validation->set_rules('name','Tên danh mục','required');
			if ($this->form_validation->run()) {
				$data = array(
					'name' => $this->input->post('name'),
					'description' => $this->input->post('description'),
					'parent_id' => $this->input->post('parent_id'),
					'sort_order' => $this->input->post('sort_order')
				);
				if ($this->catalog_model->update($id,$data)) {
					admin_flash_success( 'Thay đổi danh mục thành công');
				}else{
					admin_flash_fail( 'Thay đổi danh mục thất bại');
				}
				redirect(admin_url('catalog'));
			}
		}

		$input = array();
		$input['where'] = array('parent_id <' => '2');
		$list = $this->catalog_model->get_list($input);
		$this->data['list']= $list;
		
		$this->render_admin('admin/catalog/edit');
	}

	public function del()
	{
		admin_require_post();
		// Chỉ ADMIN xóa danh mục
		if (!admin_can('catalog.delete', $this->currentUser)) {
			admin_flash_fail( 'Bạn không có đủ quyền hạn để xóa danh mục sản phẩm!');
			redirect(admin_url('catalog'));
		}

		$id = $this->uri->segment(4);
		$where = array('id' => $id);
		if (!$this->catalog_model->check_exists($where)) {
			admin_flash_fail( 'Danh mục không tồn tại');
			redirect(admin_url('catalog'));
		}
		
		if ($this->catalog_model->delete($id)) {
			admin_flash_success( 'Xóa danh mục thành công');
		}else{
			admin_flash_fail( 'Xóa danh mục thất bại');
		}
		redirect(admin_url('catalog'));
	}
}
