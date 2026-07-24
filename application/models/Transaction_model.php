<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Transaction_model extends MY_Model {
	var $table = 'transaction';

	// Hoàn kho khi hủy đơn; reverse_buyed chỉ khi đơn đã hoàn thành (status 3).
	public function release_stock_for_transaction($transaction_id, $reverse_buyed = false)
	{
		$CI =& get_instance();
		$CI->load->model('order_model');
		$CI->load->model('product_model');

		$input = array('where' => array('transaction_id' => (int) $transaction_id));
		$lines = $CI->order_model->get_list($input);

		foreach ($lines as $item) {
			$product = $CI->product_model->get_info($item->product_id);
			if (empty($product)) {
				continue;
			}
			$qty = (int) $item->qty;
			$update = array(
				'quantity' => (int) $product->quantity + $qty,
			);
			if ($reverse_buyed) {
				$buyed = (int) $product->buyed - $qty;
				$update['buyed'] = $buyed < 0 ? 0 : $buyed;
			}
			$CI->product_model->update($product->id, $update);
		}

		return true;
	}

	// Cộng buyed một lần khi đơn chuyển sang hoàn thành (status 3).
	public function apply_buyed_for_transaction($transaction_id)
	{
		$CI =& get_instance();
		$CI->load->model('order_model');
		$CI->load->model('product_model');

		$input = array('where' => array('transaction_id' => (int) $transaction_id));
		$lines = $CI->order_model->get_list($input);

		foreach ($lines as $item) {
			$product = $CI->product_model->get_info($item->product_id);
			if (empty($product)) {
				continue;
			}
			$qty = (int) $item->qty;
			$CI->product_model->update($product->id, array(
				'buyed' => (int) $product->buyed + $qty,
			));
		}

		return true;
	}

	/**
	 * Đơn đang chờ xác nhận / xử lý / giao (status 0,1,2) — không tính hoàn thành (3) hay hủy (4).
	 */
	public function count_active_for_user($user_id, $user_email = '')
	{
		$user_id = (int) $user_id;
		$user_email = trim((string) $user_email);
		if ($user_id <= 0 && $user_email === '') {
			return 0;
		}

		$this->db->from($this->table);
		if ($user_id > 0) {
			$this->db->where('user_id', $user_id);
		} else {
			$this->db->where('user_email', $user_email);
		}
		$this->db->where_in('status', array('0', '1', '2'));

		return (int) $this->db->count_all_results();
	}
}