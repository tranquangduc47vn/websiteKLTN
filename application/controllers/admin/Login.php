<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Controller đăng nhập / đăng xuất phía admin.
 */
class Login extends MY_Controller {

	public function __construct()
	{
		parent::__construct();
		$this->load->helper(array('admin', 'permission'));
		admin_no_cache_headers();
		$login = $this->session->userdata('login');
		if ($login && admin_can_access_panel($login)) {
			redirect(admin_url('home'));
		}
		$this->load->model('admin_model');
		$this->load->library('form_validation');
		$this->load->helper('form');
	}

	public function index()
	{
		$flash = function_exists('admin_consume_scoped_flash') ? admin_consume_scoped_flash() : null;
		$this->data['message_success'] = ($flash && $flash['type'] === 'success') ? $flash['message'] : '';
		$this->data['message_fail'] = ($flash && $flash['type'] === 'fail') ? $flash['message'] : '';

		if ($this->input->post()) {
			$this->form_validation->set_rules('email', 'Email đăng nhập', 'required|valid_email');
			$this->form_validation->set_rules('password', 'Mật khẩu', 'required');
			$this->form_validation->set_rules('login', 'login', 'callback_check_login');
			if ($this->form_validation->run()) {
				$user = $this->get_info_login();
				$this->session->set_userdata('login', $user);
				admin_flash_success('Đăng nhập thành công', 'dashboard');
				redirect(admin_url('home'));
			}
		}
		$this->load->view('admin/login/index.php');
	}

	public function check_login()
	{
		$this->load->helper('rate_limit');
		$email = trim((string) $this->input->post('email'));
		$rate = rate_limit_check($email);
		if (!empty($rate['locked'])) {
			$this->form_validation->set_message(__FUNCTION__, rate_limit_message($rate['retry_after']));
			return FALSE;
		}

		$user = $this->get_info_login();
		if (!$user) {
			rate_limit_hit($email);
			$this->form_validation->set_message(__FUNCTION__, 'Đăng nhập thất bại');
			return FALSE;
		}
		if (!admin_can_access_panel($user)) {
			$this->form_validation->set_message(__FUNCTION__, 'Tài khoản không có quyền truy cập khu vực quản trị.');
			return FALSE;
		}
		rate_limit_clear($email);
		return TRUE;
	}

	function get_info_login()
	{
		$email = trim((string) $this->input->post('email'));
		$password = $this->input->post('password');
		$user = $this->admin_model->get_info_rule(array('email' => $email));
		if (!$user) {
			return false;
		}

		$verified = verify_user_password($password, $user->password);
		if ($verified === true) {
			unset($user->password);
			return $user;
		}
		if ($verified === 'rehash') {
			$this->admin_model->update($user->id, array('password' => hash_user_password($password)));
			unset($user->password);
			return $user;
		}

		return false;
	}
}
