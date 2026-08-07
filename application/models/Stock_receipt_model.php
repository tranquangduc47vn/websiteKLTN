<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Model bảng `stock_receipts` — phiếu nhập kho từ nhà cung cấp.
 * Mỗi phiếu có nhiều dòng chi tiết (stock_receipt_items); khi xác nhận sẽ cộng tồn kho vào product_variants.
 */
class Stock_receipt_model extends MY_Model {
	var $table = 'stock_receipts';
	var $order = array('id', 'DESC');

	public function get_detail($id)
	{
		$id = (int) $id;
		if ($id <= 0) {
			return false;
		}

		$this->db->select('stock_receipts.*, suppliers.name AS supplier_name, suppliers.code AS supplier_code');
		$this->db->select('creator.name AS creator_name, creator.level AS creator_level', false);
		$this->db->select('confirmer.name AS confirmer_name, confirmer.level AS confirmer_level', false);
		$this->db->from($this->table);
		$this->db->join('suppliers', 'suppliers.id = stock_receipts.supplier_id', 'left');
		$this->db->join('admin AS creator', 'creator.id = stock_receipts.created_by', 'left');
		$this->db->join('admin AS confirmer', 'confirmer.id = stock_receipts.confirmed_by', 'left');
		$this->db->where('stock_receipts.id', $id);
		$row = $this->db->get()->row();

		return $row ? $row : false;
	}

	public function count_by_code_prefix($prefix)
	{
		$prefix = (string) $prefix;
		$this->db->like('receipt_code', $prefix, 'after');
		return (int) $this->db->count_all_results($this->table);
	}
}
