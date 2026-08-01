<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'services/export/AbstractModuleExport.php';

class ProductExport extends AbstractModuleExport
{
	public function moduleKey()
	{
		return 'products';
	}

	public function buildDefinition()
	{
		$ci = $this->ci();
		$ci->load->model('product_model');
		$ci->load->model('catalog_model');

		$name = trim((string) $this->filter()->get('name'));
		$catalog_id = (int) $this->filter()->get('catalog_id');

		$def = $this->newDefinition('Báo cáo sản phẩm', 'san-pham');
		$def->landscape = true;

		if ($name !== '') {
			$def->addFilterLine('Tên sản phẩm', $name);
		}
		if ($catalog_id > 0) {
			$catalog = $ci->catalog_model->get_info($catalog_id);
			$def->addFilterLine('Danh mục', $catalog ? $catalog->name : '#' . $catalog_id);
		}

		$def->columns = array(
			array('key' => 'stt', 'label' => 'STT', 'align' => 'center', 'type' => 'int'),
			array('key' => 'id', 'label' => 'Mã SP', 'align' => 'center', 'type' => 'int'),
			array('key' => 'name', 'label' => 'Tên sản phẩm', 'align' => 'left', 'type' => 'text'),
			array('key' => 'catalog', 'label' => 'Danh mục', 'align' => 'left', 'type' => 'text'),
			array('key' => 'price', 'label' => 'Giá bán', 'align' => 'right', 'type' => 'money'),
			array('key' => 'discount', 'label' => 'Giảm giá', 'align' => 'right', 'type' => 'money'),
			array('key' => 'quantity', 'label' => 'Tồn kho', 'align' => 'center', 'type' => 'int'),
			array('key' => 'buyed', 'label' => 'Đã bán', 'align' => 'center', 'type' => 'int'),
			array('key' => 'view', 'label' => 'Lượt xem', 'align' => 'center', 'type' => 'int'),
		);

		$input = array('order' => array('product.id', 'DESC'));
		if ($name !== '') {
			$input['like'] = array('product.name', $name);
		}
		if ($catalog_id > 0) {
			$input['where'] = array('product.catalog_id' => $catalog_id);
		}

		list($limit, $offset) = $this->applyLimitOffset(10);
		if ($limit !== null) {
			$input['limit'] = array($limit, $offset);
		}

		$ci->db->select('
			product.id as id,
			product.name as name,
			price,
			discount,
			view,
			buyed,
			quantity,
			catalog.name as namecatalog
		');
		$ci->db->join('catalog', 'catalog.id = product.catalog_id');
		$rows = $ci->product_model->get_list($input);

		$stt = $offset !== null ? (int) $offset : 0;
		foreach ($rows as $row) {
			$stt++;
			$def->rows[] = array(
				'stt' => $stt,
				'id' => (int) $row->id,
				'name' => (string) $row->name,
				'catalog' => (string) $row->namecatalog,
				'price' => (float) $row->price,
				'discount' => (float) $row->discount,
				'quantity' => (int) $row->quantity,
				'buyed' => (int) $row->buyed,
				'view' => (int) $row->view,
			);
		}

		$def->addTotal('Số sản phẩm', count($def->rows), 'int');

		return $def;
	}
}
