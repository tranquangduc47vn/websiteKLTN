<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Model bảng `order` — mỗi dòng là một sản phẩm trong đơn hàng (transaction).
 * Quan hệ: nhiều order thuộc một transaction; liên kết product qua product_id và variant qua variant_id.
 */
class Order_model extends MY_Model {
	var $table = 'order';
}