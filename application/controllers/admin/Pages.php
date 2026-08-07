<?php

defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Controller quản lý trang tĩnh CMS phía admin (trang giới thiệu, vận chuyển, hợp tác, tuyển dụng).
 */
class Pages extends MY_Admin_Controller {

	public function __construct()
	{
		parent::__construct();
		$this->load->model('page_model');
	}

	public function index()
	{
		$list = $this->page_model->get_list(array('order' => array('id', 'ASC')));
		$this->data['list'] = array();
		foreach ($list as $row) {
			$this->data['list'][] = (array) $row;
		}
		$this->render_admin('admin/pages/index');
	}

	public function add()
	{
		if ($this->input->post('submit')) {
			$data = array(
				'title' => $this->input->post('title'),
				'slug' => $this->input->post('slug'),
				'content' => $this->input->post('content'),
			);
			if ($this->page_model->create($data)) {
				admin_flash_info('Thêm trang mới thành công!');
			} else {
				admin_flash_info('Có lỗi xảy ra khi thêm mới.');
			}
			redirect(admin_url('pages'));
		}
		$this->render_admin('admin/pages/add');
	}

	public function edit()
	{
		$id = $this->uri->rsegment(3);
		$page = $this->page_model->get_info($id);
		if (!$page) {
			admin_flash_info('Không tồn tại trang này');
			redirect(admin_url('pages'));
		}

		if ($this->input->post('submit')) {
			$data = array(
				'title' => $this->input->post('title'),
				'content' => $this->input->post('content'),
			);
			if ($this->page_model->update($id, $data)) {
				admin_flash_info('Cập nhật trang thành công!');
			} else {
				admin_flash_info('Có lỗi xảy ra, vui lòng thử lại.');
			}
			redirect(admin_url('pages'));
		}

		$this->data['page'] = (array) $page;
		$this->render_admin('admin/pages/edit');
	}
}
