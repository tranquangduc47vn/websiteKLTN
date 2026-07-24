<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class MY_Admin_Controller extends MY_Controller
{
	public function __construct()
	{
		parent::__construct();

		$this->load->helper(array('admin', 'permission'));
		admin_no_cache_headers();

		$login = $this->session->userdata('login');

		if (!$login) {
			redirect(admin_url('login'));
		}

		if (!admin_can_access_panel($login)) {
			$this->session->unset_userdata('login');
			admin_flash_fail('Tài khoản không có quyền truy cập khu vực quản trị.');
			redirect(admin_url('login'));
		}

		$this->data['login'] = $login;
		admin_discard_storefront_flash();
	}

	protected function render_admin($view, array $data = array())
	{
		$this->data = array_merge($this->data, $data);
		$this->data['temp'] = $view;
		$this->load->view('admin/layouts/main', $this->data);
	}
}
