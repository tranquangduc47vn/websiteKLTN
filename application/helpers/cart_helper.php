<?php
defined('BASEPATH') OR exit('No direct script access allowed');

if (!function_exists('cart_get_session')) {
	function cart_get_session()
	{
		$CI =& get_instance();
		$carts = $CI->session->userdata('custom_cart');
		return is_array($carts) ? $carts : array();
	}
}

if (!function_exists('cart_storage_ready')) {
	function cart_storage_ready()
	{
		$CI =& get_instance();
		return $CI->db->table_exists('user_saved_cart');
	}
}

if (!function_exists('cart_resolve_user_id')) {
	function cart_resolve_user_id($user)
	{
		if (empty($user)) {
			return 0;
		}

		$user_id = isset($user->id) ? (int) $user->id : 0;
		if ($user_id > 0) {
			return $user_id;
		}

		if (empty($user->email)) {
			return 0;
		}

		$CI =& get_instance();
		$CI->load->model('user_model');
		$row = $CI->user_model->get_info_rule(array('email' => $user->email));
		return $row ? (int) $row->id : 0;
	}
}

if (!function_exists('cart_fetch_saved_for_user')) {
	/**
	 * Read persisted cart from user_saved_cart.
	 */
	function cart_fetch_saved_for_user($user_id)
	{
		$user_id = (int) $user_id;
		if ($user_id <= 0) {
			return null;
		}

		$CI =& get_instance();

		if ($CI->db->table_exists('user_saved_cart')) {
			$row = $CI->db->where('user_id', $user_id)->get('user_saved_cart', 1)->row();
			if ($row && trim((string) $row->cart_json) !== '') {
				$decoded = json_decode((string) $row->cart_json, true);
				return is_array($decoded) ? $decoded : array();
			}
		}

		return null;
	}
}

if (!function_exists('cart_persist_session')) {
	function cart_persist_session(array $carts)
	{
		$CI =& get_instance();
		$CI->session->set_userdata('custom_cart', $carts);

		$user = $CI->session->userdata('user');
		$user_id = cart_resolve_user_id($user);
		if ($user_id > 0) {
			cart_sync_to_user($user_id, $carts);
		}
	}
}

if (!function_exists('cart_clear_session')) {
	function cart_clear_session()
	{
		cart_persist_session(array());
	}
}

if (!function_exists('cart_sync_to_user')) {
	/**
	 * Persist cart to user_saved_cart only (legacy user.saved_cart is read-only fallback).
	 */
	function cart_sync_to_user($user_id, array $carts = null)
	{
		$user_id = (int) $user_id;
		if ($user_id <= 0) {
			return;
		}

		if ($carts === null) {
			$carts = cart_get_session();
		}

		$CI =& get_instance();
		if (!$CI->db->table_exists('user_saved_cart')) {
			return;
		}

		$json = json_encode($carts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		$CI->db->replace('user_saved_cart', array(
			'user_id' => $user_id,
			'cart_json' => $json,
			'updated' => time(),
		));
	}
}

if (!function_exists('cart_restore_for_user')) {
	function cart_restore_for_user($user_id)
	{
		$user_id = (int) $user_id;
		if ($user_id <= 0) {
			return false;
		}

		$saved = cart_fetch_saved_for_user($user_id);
		if ($saved === null) {
			return false;
		}

		$CI =& get_instance();
		$CI->session->set_userdata('custom_cart', $saved);
		return true;
	}
}

if (!function_exists('cart_ensure_for_logged_in_user')) {
	function cart_ensure_for_logged_in_user($user)
	{
		if (empty($user)) {
			return;
		}

		$user_id = cart_resolve_user_id($user);
		if ($user_id <= 0) {
			return;
		}

		$session = cart_get_session();
		if (!empty($session)) {
			return;
		}

		cart_restore_for_user($user_id);
	}
}
