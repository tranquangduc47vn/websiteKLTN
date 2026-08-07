<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Model bảng `admin` — tài khoản quản trị viên.
 * Phân quyền theo cột level: 0 = super admin, các level cao hơn có quyền hạn hẹp hơn.
 */
class Admin_model extends MY_Model {
	var $table = 'admin';
}