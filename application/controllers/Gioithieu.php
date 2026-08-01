<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Gioithieu extends MY_Controller {

	public function index()
	{
		$this->load->database();

		$this->load->model('slider_model');
		$input_slider = array();
		$input_slider['order'] = array('sort_order', 'DESC');
		$slider = $this->slider_model->get_list($input_slider);
		$this->data['slider'] = $slider;

		$this->load->model('page_model');
		$page = $this->page_model->get_by_slug('gioi-thieu');
		$page = $page ? (array) $page : array();

		$this->data['page_title'] = isset($page['title']) ? $page['title'] : ('Giới thiệu về ' . shop_name());
		$this->data['page_content'] = isset($page['content']) ? $page['content'] : "Nội dung giới thiệu đang được cập nhật...";

		$this->load->model('news_model'); 
		
		$input_news = array();
		$input_news['order'] = array('id', 'DESC');
		$input_news['limit'] = array(6, 0);
		
		$other_news = $this->news_model->get_list($input_news);
		$this->data['other_news'] = $other_news;

		$this->data['temp'] = 'site/gioithieu';
		
		$this->load->view('site/layout', $this->data);
	}
}
