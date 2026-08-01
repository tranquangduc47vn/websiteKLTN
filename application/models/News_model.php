<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'models/Content_page_model.php';

class News_model extends Content_page_model {
	protected $content_type = 'news';
	protected $legacy_table = 'news';
}
