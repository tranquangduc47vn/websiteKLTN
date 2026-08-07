<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Controller cấu hình thương hiệu website phía admin: tên shop, logo, favicon.
 */
class Site_branding extends MY_Admin_Controller {

	public function __construct()
	{
		parent::__construct();
		$this->load->library('site_branding_service');

		if (!admin_can('content.manage', $this->data['login'])) {
			admin_flash_fail('Bạn không có quyền quản lý logo website.', 'dashboard');
			redirect(admin_url('home'));
		}
	}

	public function index()
	{
		if ($this->input->post('submit')) {
			$result = $this->site_branding_service->save_logo_upload('logo');
			if (!empty($result['ok'])) {
				admin_flash_success($result['message']);
			} else {
				admin_flash_fail($result['message']);
			}
			redirect(admin_url('branding'));
		}

		$this->data['logo_url'] = site_logo_url();
		$this->data['logo_exists'] = $this->site_branding_service->logo_exists();
		$this->data['logo_path'] = $this->site_branding_service->get_logo_relative_path();

		$this->render_admin('admin/site_branding/index');
	}
}
