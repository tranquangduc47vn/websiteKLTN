<?php

defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Controller quản lý hệ thống cửa hàng vật lý phía admin (địa chỉ, giờ mở cửa, bản đồ).
 */
class Store extends MY_Admin_Controller {



    public $data = array();



    public function __construct() {

        parent::__construct();

        $this->load->model('store_model');

        

        // Header admin cần login khi admin_info chưa có

        if (isset($this->admin_info)) {

            $this->data['login'] = $this->admin_info; 

        } else {

            $this->data['login'] = $this->session->userdata('login'); 

        }

    }



    public function index() {



        $this->data['list_stores']     = $this->store_model->get_all();

        

        $this->render_admin('admin/store/index');

    }



    public function add() {

        if ($this->input->post()) {

            $insert_data = array(

                'name'     => $this->input->post('name'),

                'address'  => $this->input->post('address'),

                'phone'    => $this->input->post('phone'),

                'map_link' => $this->input->post('map_link'),

                'city'     => $this->input->post('city')

            );

            

            if ($this->store_model->add($insert_data)) {

                admin_flash_success( 'Thêm mới cửa hàng thành công!');

                redirect(base_url('admin/store'));

            }

        }

        

        $this->data['title'] = "Thêm cửa hàng mới";

        $this->render_admin('admin/store/add');

    }



    public function edit($id) {

        $store = $this->store_model->get_by_id($id);

        if (!$store) {

            admin_flash_fail( 'Không tồn tại cửa hàng này!');

            redirect(base_url('admin/store'));

        }



        if ($this->input->post()) {

            $update_data = array(

                'name'     => $this->input->post('name'),

                'address'  => $this->input->post('address'),

                'phone'    => $this->input->post('phone'),

                'map_link' => $this->input->post('map_link'),

                'city'     => $this->input->post('city')

            );

            

            if ($this->store_model->update($id, $update_data)) {

                admin_flash_success( 'Cập nhật thông tin thành công!');

                redirect(base_url('admin/store'));

            }

        }



        $this->data['store'] = $store;

        $this->data['title'] = "Chỉnh sửa cửa hàng";

        $this->render_admin('admin/store/edit');

    }



    public function delete($id) {
        admin_require_post();
        $store = $this->store_model->get_by_id($id);

        if ($store) {

            $this->store_model->delete($id);

            admin_flash_success( 'Xóa cửa hàng thành công!');

        }

        redirect(base_url('admin/store'));

    }

}

