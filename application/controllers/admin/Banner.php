<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Controller quản lý banner quảng cáo phía admin.
 */
class Banner extends MY_Admin_Controller {

    protected $currentUser;

    public function __construct() {
        parent::__construct();
        $this->load->model('banner_model');
        $this->load->library('upload'); 
        
        $this->currentUser = $this->session->userdata('login');

        if (!$this->currentUser) {
            redirect(admin_url('login'));
        }

        if (!admin_can('banner.manage', $this->currentUser)) {
            admin_flash_fail( 'Bạn không có quyền quản lý khu vực Banner!', 'dashboard');
            redirect(admin_url('home'));
        }
    }

    public function index() {
        $input = array('order' => array('sort_order', 'ASC'));
        $this->data['list'] = $this->banner_model->get_list($input);

        $this->render_admin('admin/banner/index');
    }

    public function edit() {
        $id = $this->uri->rsegment(3);
        $banner = $this->banner_model->get_info($id);
        if(!$banner) {
            admin_flash_info( 'Không tồn tại banner này');
            redirect(admin_url('banner'));
        }
        $this->data['banner'] = $banner;

        if($this->input->post('submit')) {
            $this->load->library('upload_library');
            $upload_path = './upload/slider';
            $upload_data = $this->upload_library->upload($upload_path, 'image');
            
            $data = array(
                'name' => $this->input->post('name'),
                'link' => $this->input->post('link')
            );

            if(isset($upload_data['file_name'])) {
                $data['image_link'] = $upload_data['file_name'];
            }

            if($this->banner_model->update($banner->id, $data)) {
                admin_flash_info( 'Cập nhật dữ liệu thành công');
            } else {
                admin_flash_info( 'Không có temporary thay đổi nào được thực hiện');
            }
            redirect(admin_url('banner'));
        }

        $this->render_admin('admin/banner/edit');
    }
}
