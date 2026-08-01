<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'models/Content_page_model.php';

class Page_model extends Content_page_model {
	protected $content_type = 'page';
	protected $legacy_table = 'pages';

	public function get_by_slug($slug)
	{
		$slug = trim((string) $slug);
		if ($slug === '') {
			return false;
		}
		$input = array('where' => array('slug' => $slug));
		return $this->get_row($input);
	}
}
