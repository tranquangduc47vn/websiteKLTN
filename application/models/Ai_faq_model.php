<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Model bảng `ai_faq` — câu hỏi thường gặp dùng làm ngữ cảnh cho AI assistant.
 * Sắp xếp theo sort_order; chỉ lấy bản ghi có is_active = 1 khi hiển thị.
 */
class Ai_faq_model extends MY_Model {
	var $table = 'ai_faq';
	var $order = array('sort_order', 'ASC');

	public function get_active()
	{
		return $this->get_list(array(
			'where' => array('is_active' => 1),
			'order' => array('sort_order', 'ASC'),
		));
	}

	public function create($data = array())
	{
		$data['created'] = time();
		$data['updated'] = time();
		return parent::create($data);
	}

	public function update($id, $data)
	{
		$data['updated'] = time();
		return parent::update($id, $data);
	}
}
