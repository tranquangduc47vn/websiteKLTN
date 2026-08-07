<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Controller trang chính sách tích điểm và hạng thành viên phía storefront.
 */
class Loyalty extends MY_Frontend_Controller {

	public function index()
	{
		$this->load->helper('loyalty');
		$this->data['page_title'] = 'Chính sách tích điểm & hạng thành viên';
		$this->render_frontend_sub('site/loyalty/index');
	}
}
