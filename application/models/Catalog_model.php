<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Model bảng `catalog` — danh mục sản phẩm dạng cây (3 cấp: root → danh mục → danh mục con).
 * Node gốc có parent_id = 0; danh mục cấp 1 có parent_id = 1; cấp 2 trỏ vào id cấp 1.
 */
class Catalog_model extends MY_Model {
	var $table = 'catalog';
}