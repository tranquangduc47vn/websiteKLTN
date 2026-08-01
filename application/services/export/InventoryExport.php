<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'services/export/AbstractModuleExport.php';

class InventoryExport extends AbstractModuleExport
{
	public function moduleKey()
	{
		return 'inventory';
	}

	public function buildDefinition()
	{
		$ci = $this->ci();
		$ci->load->model('inventory_model');
		$ci->load->model('catalog_model');

		$filters = array(
			'name' => trim((string) $this->filter()->get('name')),
			'catalog_id' => (int) $this->filter()->get('catalog_id'),
			'sku' => trim((string) $this->filter()->get('sku')),
			'stock' => $this->filter()->get('stock'),
		);

		$def = $this->newDefinition('Báo cáo tồn kho', 'ton-kho');
		$def->landscape = true;

		if ($filters['name'] !== '') {
			$def->addFilterLine('Tên sản phẩm', $filters['name']);
		}
		if ($filters['sku'] !== '') {
			$def->addFilterLine('SKU', $filters['sku']);
		}
		if ($filters['catalog_id'] > 0) {
			$catalog = $ci->catalog_model->get_info($filters['catalog_id']);
			$def->addFilterLine('Danh mục', $catalog ? $catalog->name : '#' . $filters['catalog_id']);
		}
		if ($filters['stock'] !== '') {
			$stock_labels = array('out' => 'Hết hàng', 'low' => 'Sắp hết', 'ok' => 'Còn hàng');
			$key = (string) $filters['stock'];
			$def->addFilterLine('Trạng thái tồn', isset($stock_labels[$key]) ? $stock_labels[$key] : $key);
		}

		$def->columns = array(
			array('key' => 'stt', 'label' => 'STT', 'align' => 'center', 'type' => 'int'),
			array('key' => 'sku', 'label' => 'SKU', 'align' => 'left', 'type' => 'text'),
			array('key' => 'product_name', 'label' => 'Sản phẩm', 'align' => 'left', 'type' => 'text'),
			array('key' => 'catalog_name', 'label' => 'Danh mục', 'align' => 'left', 'type' => 'text'),
			array('key' => 'color', 'label' => 'Màu', 'align' => 'left', 'type' => 'text'),
			array('key' => 'size', 'label' => 'Size', 'align' => 'left', 'type' => 'text'),
			array('key' => 'stock', 'label' => 'Tồn', 'align' => 'right', 'type' => 'int'),
			array('key' => 'min_stock', 'label' => 'Tối thiểu', 'align' => 'right', 'type' => 'int'),
			array('key' => 'status', 'label' => 'Trạng thái', 'align' => 'center', 'type' => 'text'),
		);

		list($limit, $offset) = $this->applyLimitOffset(20);
		if ($limit !== null) {
			$rows = $ci->inventory_model->get_list($filters, $limit, $offset);
		} else {
			$rows = $ci->inventory_model->get_list($filters, 0, 0);
		}

		$stt = $offset !== null ? (int) $offset : 0;
		foreach ($rows as $row) {
			$stt++;
			$stock = (int) $row->stock;
			$min = (int) $row->min_stock;
			if ($stock <= 0) {
				$status = 'Hết hàng';
			} elseif ($stock <= $min) {
				$status = 'Sắp hết';
			} else {
				$status = 'Còn hàng';
			}
			$def->rows[] = array(
				'stt' => $stt,
				'sku' => (string) $row->sku,
				'product_name' => (string) $row->product_name,
				'catalog_name' => isset($row->catalog_name) ? (string) $row->catalog_name : '',
				'color' => $row->color !== '' ? (string) $row->color : '—',
				'size' => $row->size !== '' ? (string) $row->size : '—',
				'stock' => $stock,
				'min_stock' => $min,
				'status' => $status,
			);
		}

		$def->addTotal('Số biến thể', count($def->rows), 'int');

		return $def;
	}
}
