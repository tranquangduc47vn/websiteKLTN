<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Model bảng `suppliers` — danh sách nhà cung cấp hàng hóa.
 * Liên kết với phiếu nhập kho qua stock_receipts.supplier_id.
 */
class Supplier_model extends MY_Model {
	var $table = 'suppliers';
	var $order = array('name', 'ASC');
}
