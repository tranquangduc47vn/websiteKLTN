<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'models/Content_page_model.php';

class Hoptac_model extends Content_page_model {
	protected $content_type = 'hop_tac';
	protected $legacy_table = 'hop_tac';
}
