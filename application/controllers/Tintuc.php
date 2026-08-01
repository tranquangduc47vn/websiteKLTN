<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Tintuc extends MY_Controller {

    public function __construct() {
        parent::__construct();
        $this->load->database();
    }

    public function index() {
        $this->load->model('slider_model');
        $input_slider = array();
        $input_slider['order'] = array('sort_order', 'DESC');
        $this->data['slider'] = $this->slider_model->get_list($input_slider);

        $this->load->model('news_model');
        $input = array('order' => array('id', 'DESC'));
        $rows = $this->news_model->get_list($input);
        $this->data['list_news'] = array();
        foreach ($rows as $row) {
            $this->data['list_news'][] = (array) $row;
        }

        $this->data['page_title'] = 'Tin tức & Sự kiện - ' . shop_name();

        $this->data['temp'] = 'site/tintuc_index';
        
        $this->load->view('site/layout', $this->data);
    }
}
