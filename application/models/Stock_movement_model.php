<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Model bảng `stock_movements` — lịch sử xuất/nhập kho theo từng biến thể sản phẩm.
 * Mỗi dòng ghi một thay đổi tồn kho: nhập từ phiếu, xuất theo đơn hàng, điều chỉnh thủ công.
 */
class Stock_movement_model extends MY_Model {
	var $table = 'stock_movements';
	var $order = array('id', 'DESC');
}
