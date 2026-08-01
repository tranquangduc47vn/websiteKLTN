<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'models/Content_page_model.php';

class Tuyendung_model extends Content_page_model {
	protected $content_type = 'tuyendung';
	protected $legacy_table = 'tuyendung';

	public function get_list_tuyendung()
	{
		return $this->get_list(array('order' => array('id', 'DESC')));
	}
}
