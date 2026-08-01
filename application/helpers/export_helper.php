<?php
defined('BASEPATH') OR exit('No direct script access allowed');

if (!function_exists('admin_export_can')) {
	// Chỉ ROLE_ADMIN được export.
	function admin_export_can($login = null)
	{
		$CI =& get_instance();
		if ($login === null) {
			$login = $CI->session->userdata('login');
		}
		if (empty($login)) {
			return false;
		}
		return (int) $login->level === ROLE_ADMIN;
	}
}

if (!function_exists('admin_export_module_allowed')) {
	function admin_export_module_allowed($module, $login = null)
	{
		$allowed = array(
			'orders',
			'products',
			'customers',
			'inventory',
			'voucher',
			'revenue',
			'top_products',
			'dashboard',
		);
		return in_array((string) $module, $allowed, true) && admin_export_can($login);
	}
}

if (!function_exists('admin_export_query_string')) {
	function admin_export_query_string(array $extra = array())
	{
		$CI =& get_instance();
		$skip = array('module', 'format', 'scope');
		$params = array();

		foreach ($_GET as $key => $value) {
			if (in_array($key, $skip, true)) {
				continue;
			}
			if ($value === null || $value === '') {
				continue;
			}
			$params[$key] = $value;
		}

		foreach ($extra as $key => $value) {
			if ($value === null || $value === '') {
				unset($params[$key]);
			} else {
				$params[$key] = $value;
			}
		}

		return http_build_query($params);
	}
}

if (!function_exists('admin_export_pagination_params')) {
	function admin_export_pagination_params($module)
	{
		$CI =& get_instance();
		$params = array();

		switch ((string) $module) {
			case 'orders':
				$params['per_page'] = 10;
				$offset = (int) $CI->input->get('offset');
				if ($offset <= 0) {
					if ($CI->uri->segment(2) === 'orders' && is_numeric($CI->uri->segment(3))) {
						$offset = (int) $CI->uri->segment(3);
					} elseif (is_numeric($CI->uri->segment(4))) {
						$offset = (int) $CI->uri->segment(4);
					}
				}
				$params['offset'] = max(0, $offset);
				break;

			case 'products':
				$params['per_page'] = 10;
				$offset = isset($CI->uri->segments['4']) ? (int) $CI->uri->segments['4'] : 0;
				$params['offset'] = max(0, $offset);
				break;

			case 'inventory':
				$params['per_page'] = 20;
				$offset = isset($CI->uri->segments['4']) ? (int) $CI->uri->segments['4'] : 0;
				$params['offset'] = max(0, $offset);
				break;

			default:
				break;
		}

		return $params;
	}
}

if (!function_exists('admin_export_toolbar')) {
	function admin_export_toolbar($module, array $extra = array())
	{
		$CI =& get_instance();
		$login = $CI->session->userdata('login');
		if (!admin_export_can($login) || !admin_export_module_allowed($module, $login)) {
			return;
		}
		$extra = array_merge(admin_export_pagination_params($module), $extra);
		$CI->load->view('admin/partials/export_toolbar', array(
			'export_module' => (string) $module,
			'export_query' => admin_export_query_string($extra),
		));
	}
}

if (!function_exists('admin_export_format_cell')) {
	function admin_export_format_cell($value, $type = 'text')
	{
		if ($type === 'money' && is_numeric($value)) {
			return number_format((float) $value, 0, ',', '.') . ' ₫';
		}
		if ($type === 'int' && is_numeric($value)) {
			return number_format((int) $value, 0, ',', '.');
		}
		return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
	}
}
