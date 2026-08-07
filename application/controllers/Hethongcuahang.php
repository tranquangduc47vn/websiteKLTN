<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Controller trang hệ thống cửa hàng vật lý: hiển thị danh sách địa điểm trên bản đồ.
 */
class Hethongcuahang extends MY_Frontend_Controller {

    public function index()
    {
        $this->data['list_stores'] = $this->db->get('stores')->result();
        $this->data['slider'] = $this->db->get('slider')->result();

        $this->data['page_title'] = 'Hệ thống cửa hàng - ' . shop_name();
        $this->render_frontend_main('site/hethongcuahang/index');
    }
}
