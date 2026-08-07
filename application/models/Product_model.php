<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Model bảng `product` — thông tin sản phẩm (tên, giá, giảm giá, tồn kho tổng hợp, ảnh, catalog).
 * Tồn kho chi tiết theo biến thể (size/màu) lưu tại bảng `product_variants`.
 */
class Product_model extends MY_Model {
	var $table = 'product';
}