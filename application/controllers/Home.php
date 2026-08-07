<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Controller trang chủ storefront: hiển thị sản phẩm nổi bật, mới, bán chạy và slider banner.
 */
class Home extends MY_Frontend_Controller {

	public function index()
	{

		$this->load->model('slider_model');
		$input_slider = array();
		$input_slider['order'] = array('sort_order', 'DESC');
		$this->data['slider'] = $this->slider_model->get_list($input_slider);

		$this->load->model('banner_model');
		$input_banner = array();
		$input_banner['order'] = array('sort_order', 'ASC'); 
        

		$this->data['list_banner'] = $this->banner_model->get_list($input_banner);

		$this->load->model('product_model');
		
		$input_new = product_storefront_input(array(
			'order' => array('id', 'DESC'),
			'limit' => array('6', '0'),
		));
		$this->data['new_product'] = $this->product_model->get_list($input_new);

		$input_hot = product_storefront_input(array(
			'where' => array('buyed >' => 0),
			'order' => array('buyed', 'DESC'),
			'limit' => array('6', '0'),
		));
		$this->data['hot_product'] = $this->product_model->get_list($input_hot);

		$input_view = product_storefront_input(array(
			'order' => array('view', 'DESC'),
			'limit' => array('6', '0'),
		));
		$this->data['view_product'] = $this->product_model->get_list($input_view);

		$this->render_frontend_main('site/home/index');
	}
}
