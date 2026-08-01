<?php
defined('BASEPATH') OR exit('No direct script access allowed');

// Admin panel roles: ROLE_ADMIN (0), ROLE_MOD (1), ROLE_USER (2). Storefront user table — not used here.

if (!function_exists('admin_role_label')) {
	function admin_role_label($level)
	{
		switch ((int) $level) {
			case ROLE_ADMIN:
				return 'Admin';
			case ROLE_MOD:
				return 'Mod';
			case ROLE_USER:
				return 'User';
			default:
				return '—';
		}
	}
}

if (!function_exists('admin_can_access_panel')) {
	function admin_can_access_panel($login)
	{
		if (!$login || !isset($login->level)) {
			return false;
		}
		$level = (int) $login->level;
		return in_array($level, array(ROLE_ADMIN, ROLE_MOD, ROLE_USER), true);
	}
}

if (!function_exists('admin_can')) {
	function admin_can($permission, $login = null)
	{
		$CI =& get_instance();
		if ($login === null) {
			$login = $CI->session->userdata('login');
		}
		if (!$login || !isset($login->level)) {
			return false;
		}
		$level = (int) $login->level;
		$is_admin = ($level === ROLE_ADMIN);
		$is_mod = ($level === ROLE_MOD);
		$is_user = ($level === ROLE_USER);
		$is_staff = ($is_admin || $is_mod);

		switch ($permission) {
			case 'order.manage':
				return $is_staff;

			case 'order.chat':
			case 'panel.home':
				return $is_admin || $is_mod || $is_user;

			case 'inventory.view':
			case 'stock.view':
				return $is_admin || $is_mod || $is_user;

			// Mod: xem / kiểm kê tồn; không điều chỉnh số lượng.
			case 'inventory.count':
				return $is_admin || $is_mod;

			case 'inventory.adjust':
			case 'inventory.manage':
				return $is_admin;

			// Phiếu nhập kho — Admin + Mod.
			case 'stock.receipt':
				return $is_admin || $is_mod;

			case 'stock.manage':
			case 'product.manage':
			case 'catalog.manage':
			case 'banner.manage':
			case 'content.manage':
				return $is_staff;

			case 'voucher.manage':
				return $is_admin;

			case 'product.delete_single':
			case 'product.delete':
			case 'product.bulk_delete':
			case 'catalog.delete':
			case 'staff.delete':
				return $is_admin;

			case 'staff.grant_admin':
			case 'staff.edit_admin':
			case 'staff.manage':
				return $is_admin;

			case 'ai.manage':
				return $is_staff;

			default:
				return $is_admin;
		}
	}
}

if (!function_exists('admin_sidebar_slug_permission')) {
	/**
	 * Slug menu yêu cầu quyền riêng (khác mặc định Mod/Admin).
	 */
	function admin_sidebar_slug_permission($slug)
	{
		$map = array(
			'voucher' => 'voucher.manage',
			'admin' => 'staff.manage',
			'inventory-adjust' => 'inventory.count',
		);

		return isset($map[$slug]) ? $map[$slug] : null;
	}
}

if (!function_exists('admin_sidebar_allowed')) {
	function admin_sidebar_allowed($slug, $login = null)
	{
		if ($login === null) {
			$login = get_instance()->session->userdata('login');
		}
		if (!admin_can_access_panel($login)) {
			return false;
		}

		if (is_array($slug)) {
			foreach ($slug as $s) {
				if (admin_sidebar_allowed($s, $login)) {
					return true;
				}
			}
			return false;
		}

		$user_only = array('home');
		$staff = array('catalog', 'products', 'product-reviews', 'slider', 'users', 'admin', 'posts', 'page', 'banner', 'branding', 'store', 'tuyendung', 'hoptac', 'vanchuyen', 'voucher');
		$warehouse_slugs = array('inventory', 'receipts', 'inventory-adjust', 'stock-movements', 'suppliers', 'stock-receipts', 'stock-inventory');

		$level = (int) $login->level;
		if ($level === ROLE_USER) {
			if (in_array($slug, $user_only, true)) {
				return true;
			}
			if ($slug === 'inventory' || in_array($slug, array('receipts', 'inventory-adjust', 'stock-movements', 'suppliers', 'stock-receipts', 'stock-inventory'), true)) {
				return admin_can('inventory.view', $login) || admin_can('stock.view', $login);
			}
			if ($slug === 'stock-receipts' || $slug === 'stock-inventory') {
				return admin_can('stock.view', $login);
			}
			return false;
		}

		$required = admin_sidebar_slug_permission($slug);
		if ($required !== null) {
			return admin_can($required, $login);
		}

		if (in_array($slug, $user_only, true) || in_array($slug, $staff, true)) {
			return true;
		}
		if (in_array($slug, $warehouse_slugs, true)) {
			return admin_can('stock.view', $login) || admin_can('inventory.view', $login);
		}
		if ($slug === 'orders' || $slug === 'report') {
			return admin_can('order.manage', $login);
		}
		return false;
	}
}
