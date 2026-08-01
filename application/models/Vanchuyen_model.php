<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'models/Content_page_model.php';

class Vanchuyen_model extends Content_page_model {
	protected $content_type = 'van_chuyen';
	protected $legacy_table = 'van_chuyen';
}
