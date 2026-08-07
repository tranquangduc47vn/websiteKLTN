<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Model bảng `user` — tài khoản khách hàng storefront.
 * Lưu thông tin đăng nhập (email + bcrypt hash), địa chỉ chính, số điện thoại chính và dữ liệu loyalty.
 */
class User_model extends MY_Model {
	var $table = 'user';
}