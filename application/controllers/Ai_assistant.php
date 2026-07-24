<?php
defined('BASEPATH') OR exit('No direct script access allowed');

// AJAX widget hỗ trợ khách
class Ai_assistant extends MY_Frontend_Controller {

	public function send()
	{
		$this->output->set_content_type('application/json', 'utf-8');

		if (!$this->_require_customer_login_json()) {
			return;
		}

		if (!$this->input->post()) {
			$this->output->set_output(json_encode(array('ok' => false, 'message' => 'Yêu cầu không hợp lệ.')));
			return;
		}

		require_once APPPATH . 'services/support/SupportChatService.php';

		$message = (string) $this->input->post('message');
		$conversationId = (int) $this->input->post('conversation_id');

		$userLogin = $this->session->userdata('user');
		$userId = ($userLogin && isset($userLogin->id)) ? (int) $userLogin->id : 0;

		try {
			$service = new SupportChatService();
			$result = $service->handleCustomerMessage($message, $conversationId, $userId, '');
			if (!is_array($result)) {
				$result = array('ok' => false, 'message' => 'Phản hồi không hợp lệ.');
			}
			if (function_exists('csrf_json_meta')) {
				$result = array_merge($result, csrf_json_meta());
			}
			$this->output->set_output(json_encode($result, JSON_UNESCAPED_UNICODE));
		} catch (Throwable $e) {
			log_message('error', 'Ai_assistant::send — ' . $e->getMessage());
			$payload = array('ok' => false, 'message' => 'Lỗi máy chủ khi xử lý tin nhắn.');
			if (function_exists('csrf_json_meta')) {
				$payload = array_merge($payload, csrf_json_meta());
			}
			$this->output->set_output(json_encode($payload, JSON_UNESCAPED_UNICODE));
		}
	}

	public function history()
	{
		$this->output->set_content_type('application/json', 'utf-8');

		if (!$this->_require_customer_login_json()) {
			return;
		}

		$conversationId = (int) $this->input->get('conversation_id');
		if ($conversationId <= 0) {
			$this->output->set_output(json_encode(array('ok' => true, 'messages' => array())));
			return;
		}

		require_once APPPATH . 'services/support/SupportChatService.php';

		$userLogin = $this->session->userdata('user');
		$userId = ($userLogin && isset($userLogin->id)) ? (int) $userLogin->id : 0;

		$service = new SupportChatService();
		$result = $service->historyForCustomer($conversationId, $userId, '');

		$this->output->set_output(json_encode($result, JSON_UNESCAPED_UNICODE));
	}

	// Polling tin mới (ChatSyncInterface)
	public function poll()
	{
		$this->output->set_content_type('application/json', 'utf-8');

		if (!$this->_require_customer_login_json()) {
			return;
		}

		require_once APPPATH . 'services/support/PollingChatSyncService.php';

		$conversationId = (int) $this->input->get('conversation_id');
		$afterId = (int) $this->input->get('after_id');

		$userLogin = $this->session->userdata('user');
		$userId = ($userLogin && isset($userLogin->id)) ? (int) $userLogin->id : 0;

		$sync = new PollingChatSyncService();
		$result = $sync->pollCustomer($conversationId, $afterId, $userId, '');

		$this->output->set_output(json_encode($result, JSON_UNESCAPED_UNICODE));
	}

	protected function _customer_user_id()
	{
		$userLogin = $this->session->userdata('user');
		return ($userLogin && isset($userLogin->id)) ? (int) $userLogin->id : 0;
	}

	protected function _require_customer_login_json()
	{
		if ($this->_customer_user_id() > 0) {
			return true;
		}

		$payload = array(
			'ok' => false,
			'login_required' => true,
			'message' => 'Bạn cần đăng nhập tài khoản để sử dụng hỗ trợ khách hàng.',
		);
		if (function_exists('csrf_json_meta')) {
			$payload = array_merge($payload, csrf_json_meta());
		}
		$this->output->set_output(json_encode($payload, JSON_UNESCAPED_UNICODE));
		return false;
	}
}
