<?php
defined('BASEPATH') OR exit('No direct script access allowed');

if (!function_exists('product_default_colors')) {
	function product_default_colors()
	{
		return array(
			'Đen' => '#000000',
			'Trắng' => '#ffffff',
			'Đỏ' => '#dd021b',
			'Vàng' => '#fcd535',
			'Xanh dương' => '#2196f3',
			'Xanh lá' => '#4caf50',
			'Hồng' => '#ffb6c1',
			'Xám' => '#808080',
			'Nâu' => '#8b5a2b',
			'Kem' => '#f5f5dc',
			'Cam' => '#ff9800',
			'Tím' => '#9c27b0',
		);
	}
}

if (!function_exists('product_default_sizes')) {
	function product_default_sizes()
	{
		return array('S', 'M', 'L', 'XL', 'XXL');
	}
}

if (!function_exists('product_master_colors')) {
	/**
	 * @param array $extra_names Màu đã lưu trên sản phẩm nhưng chưa có trong master (giữ tương thích).
	 */
	function product_master_colors(array $extra_names = array())
	{
		$CI =& get_instance();
		$colors = product_default_colors();

		if ($CI->db->table_exists('master_colors')) {
			$rows = $CI->db->where('status', 1)
				->order_by('sort_order', 'ASC')
				->order_by('name', 'ASC')
				->get('master_colors')
				->result();
			if (!empty($rows)) {
				$colors = array();
				foreach ($rows as $row) {
					$colors[(string) $row->name] = (string) $row->hex_code;
				}
			}
		}

		foreach ($extra_names as $name) {
			$name = trim((string) $name);
			if ($name !== '' && !isset($colors[$name])) {
				$colors[$name] = '#cccccc';
			}
		}

		return $colors;
	}
}

if (!function_exists('product_master_sizes')) {
	function product_master_sizes(array $extra_names = array())
	{
		$CI =& get_instance();
		$sizes = product_default_sizes();

		if ($CI->db->table_exists('master_sizes')) {
			$rows = $CI->db->where('status', 1)
				->order_by('sort_order', 'ASC')
				->order_by('name', 'ASC')
				->get('master_sizes')
				->result();
			if (!empty($rows)) {
				$sizes = array();
				foreach ($rows as $row) {
					$sizes[] = (string) $row->name;
				}
			}
		}

		foreach ($extra_names as $name) {
			$name = trim((string) $name);
			if ($name !== '' && !in_array($name, $sizes, true)) {
				$sizes[] = $name;
			}
		}

		return $sizes;
	}
}

if (!function_exists('product_db_active_only')) {
	function product_db_active_only($db = null, $alias = '')
	{
		$CI =& get_instance();
		if ($db === null) {
			$db = $CI->db;
		}
		if (!$db->field_exists('status', 'product')) {
			return;
		}
		$col = ($alias !== '') ? $alias . '.status' : 'status';
		$db->where($col, 1);
	}
}

if (!function_exists('product_is_active')) {
	function product_is_active($product)
	{
		if (!$product) {
			return FALSE;
		}
		$CI =& get_instance();
		if (!$CI->db->field_exists('status', 'product')) {
			return TRUE;
		}
		return (int) $product->status === 1;
	}
}

if (!function_exists('product_storefront_input')) {
	function product_storefront_input(array $input = array())
	{
		$CI =& get_instance();
		if (!$CI->db->field_exists('status', 'product')) {
			return $input;
		}
		if (!isset($input['where']) || !is_array($input['where'])) {
			$input['where'] = array();
		}
		$input['where']['status'] = 1;
		return $input;
	}
}

if (!function_exists('product_apply_search_keyword')) {
	function product_apply_search_keyword($keyword, $db = null, $use_catalog_join = TRUE)
	{
		$CI =& get_instance();
		if ($db === null) {
			$db = $CI->db;
		}
		$keyword = trim((string) $keyword);
		if ($keyword === '') {
			return;
		}

		if ($use_catalog_join) {
			$db->join('catalog', 'catalog.id = product.catalog_id', 'left');
		}

		$name_col = $use_catalog_join ? 'product.name' : 'name';
		$content_col = $use_catalog_join ? 'product.content' : 'content';
		$code_col = $use_catalog_join ? 'product.code' : 'code';

		$db->group_start();
		$db->like($name_col, $keyword);
		$db->or_like($content_col, $keyword);
		if ($db->field_exists('code', 'product')) {
			$db->or_like($code_col, $keyword);
		}
		if ($use_catalog_join) {
			$db->or_like('catalog.name', $keyword);
		}
		$db->group_end();
	}
}
