<?php 
	function admin_url($url='')
	{
		$parts = explode('/', ltrim($url, '/'));
		$canonical = array(
			'product' => 'products',
			'user' => 'users',
			'transaction' => 'orders',
			'news' => 'posts',
		);
		if (!empty($parts[0]) && isset($canonical[$parts[0]])) {
			$parts[0] = $canonical[$parts[0]];
			$url = implode('/', $parts);
		}
		return base_url('admin/'.$url);
	}

	// Admin header title from current URL; override with $admin_header_title in view.
	function admin_header_title()
	{
		$CI =& get_instance();
		$seg2 = $CI->uri->segment(2);
		$seg3 = $CI->uri->segment(3);

		if ($seg2 === '' || $seg2 === false) {
			$seg2 = 'home';
		}

		if ($seg2 === 'orders' && $seg3 === 'chat') {
			return 'Hỗ trợ khách hàng';
		}
		if ($seg2 === 'support-chat') {
			if ($seg3 === 'chat') {
				return 'Chat hỗ trợ khách hàng';
			}
			return 'Hỗ trợ khách hàng';
		}
		if ($seg2 === 'orders' && $seg3 === 'detail') {
			return 'Chi tiết đơn đặt hàng';
		}
		if ($seg2 === 'users' && $seg3 === 'order') {
			return 'Đơn đặt hàng khách hàng';
		}
		if ($seg2 === 'users' && $seg3 === 'detail') {
			return 'Chi tiết đơn đặt hàng';
		}
		if ($seg2 === 'report') {
			return 'Thống kê doanh thu & số lượng bán';
		}
		if ($seg2 === 'ai-assistant') {
			if ($seg3 === 'faq') {
				return 'Quản lý FAQ trợ lý AI';
			}
			return 'Cấu hình trợ lý AI';
		}

		$map = array(
			'home' => array(
				'default' => 'Tổng quan quản trị',
			),
			'catalog' => array(
				'default' => 'Quản lý danh mục',
				'add' => 'Thêm danh mục',
				'edit' => 'Sửa danh mục',
			),
			'product' => array(
				'default' => 'Quản lý sản phẩm',
				'add' => 'Thêm sản phẩm',
				'edit' => 'Chỉnh sửa sản phẩm',
			),
			'products' => array(
				'default' => 'Quản lý sản phẩm',
				'add' => 'Thêm sản phẩm',
				'edit' => 'Chỉnh sửa sản phẩm',
			),
			'product-reviews' => array(
				'default' => 'Duyệt đánh giá sản phẩm',
			),
			'inventory' => array(
				'default' => 'Quản lý tồn kho',
				'low_stock' => 'Hàng sắp hết',
				'adjust' => 'Kiểm kê tồn kho',
			),
			'receipts' => array(
				'default' => 'Phiếu nhập kho',
				'create' => 'Tạo phiếu nhập',
				'view' => 'Chi tiết phiếu nhập',
			),
			'stock-movements' => array(
				'default' => 'Lịch sử biến động kho',
			),
			'suppliers' => array(
				'default' => 'Nhà cung cấp',
				'add' => 'Thêm nhà cung cấp',
			),
			'stock-receipts' => array(
				'default' => 'Phiếu nhập kho',
				'add' => 'Tạo phiếu nhập',
				'edit' => 'Sửa phiếu nhập',
				'view' => 'Chi tiết phiếu nhập',
			),
			'stock-inventory' => array(
				'default' => 'Quản lý tồn kho',
				'movements' => 'Lịch sử biến động kho',
			),
			'slider' => array(
				'default' => 'Quản lý slider',
				'add' => 'Thêm slider',
				'edit' => 'Chỉnh sửa slider',
			),
			'transaction' => array(
				'default' => 'Quản lý đơn đặt hàng',
			),
			'orders' => array(
				'default' => 'Quản lý đơn đặt hàng',
			),
			'user' => array(
				'default' => 'Danh sách khách hàng',
			),
			'users' => array(
				'default' => 'Danh sách khách hàng',
			),
			'admin' => array(
				'default' => 'Danh sách quản trị viên',
				'add' => 'Thêm quản trị viên',
				'edit' => 'Sửa thông tin thành viên',
			),
			'news' => array(
				'default' => 'Quản lý tin tức',
				'add' => 'Thêm tin tức mới',
				'edit' => 'Chỉnh sửa bài viết',
			),
			'posts' => array(
				'default' => 'Quản lý tin tức',
				'add' => 'Thêm tin tức mới',
				'edit' => 'Chỉnh sửa bài viết',
			),
			'page' => array(
				'default' => 'Quản lý trang tĩnh',
				'add' => 'Thêm trang tĩnh mới',
				'edit' => 'Chỉnh sửa trang',
			),
			'pages' => array(
				'default' => 'Quản lý trang tĩnh',
				'add' => 'Thêm trang tĩnh mới',
				'edit' => 'Chỉnh sửa trang',
			),
			'banner' => array(
				'default' => 'Quản lý banner danh mục',
				'edit' => 'Chỉnh sửa banner',
			),
			'store' => array(
				'default' => 'Quản lý hệ thống cửa hàng',
				'add' => 'Thêm cửa hàng',
				'edit' => 'Sửa cửa hàng',
			),
			'tuyendung' => array(
				'default' => 'Quản lý tuyển dụng',
				'add' => 'Thêm vị trí tuyển dụng',
				'edit' => 'Chỉnh sửa tuyển dụng',
			),
			'hoptac' => array(
				'default' => 'Quản lý hợp tác kinh doanh',
				'add' => 'Thêm bài viết hợp tác',
				'edit' => 'Chỉnh sửa bài hợp tác',
			),
			'vanchuyen' => array(
				'default' => 'Chính sách vận chuyển',
				'add' => 'Thêm chính sách vận chuyển',
				'edit' => 'Sửa chính sách vận chuyển',
			),
			'voucher' => array(
				'default' => 'Quản lý voucher',
				'add' => 'Thêm voucher',
				'edit' => 'Sửa voucher',
			),
		);

		if (!isset($map[$seg2])) {
			return 'Webshop Admin';
		}

		$entry = $map[$seg2];
		if ($seg3 && isset($entry[$seg3])) {
			return $entry[$seg3];
		}

		return $entry['default'];
	}

	// Order status labels (0–4), shared admin + dashboard.
	function admin_order_status_text($status)
	{
		switch ((string) $status) {
			case '0':
				return 'Mới đặt';
			case '1':
				return 'Đã xác nhận';
			case '2':
				return 'Đang giao hàng';
			case '3':
				return 'Hoàn thành';
			case '4':
				return 'Đã hủy / Hoàn';
			default:
				return 'Không rõ';
		}
	}

	// Prevent browser cache on admin pages after logout.
	function admin_no_cache_headers()
	{
		$CI =& get_instance();
		if (!isset($CI->output)) {
			return;
		}
		$CI->output->set_header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
		$CI->output->set_header('Pragma: no-cache');
		$CI->output->set_header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
	}

	if (!function_exists('admin_flash_scope_for_controller')) {
		function admin_flash_scope_for_controller($class = null)
		{
			if ($class === null) {
				$class = strtolower((string) get_instance()->router->class);
			}

			$map = array(
				'transaction' => 'orders',
				'report' => 'orders',
				'product' => 'products',
				'inventory' => 'inventory',
				'stock_receipt' => 'inventory',
				'stock_movements' => 'inventory',
				'stock_inventory' => 'inventory',
				'suppliers' => 'inventory',
				'ai_assistant' => 'ai',
				'support_chat' => 'support',
				'user' => 'users',
				'catalog' => 'catalog',
				'news' => 'posts',
				'store' => 'store',
				'admin' => 'staff',
				'voucher' => 'marketing',
				'banner' => 'content',
				'slider' => 'content',
				'pages' => 'content',
				'tuyendung' => 'content',
				'hoptac' => 'content',
				'vanchuyen' => 'content',
				'site_branding' => 'branding',
				'login' => 'auth',
				'home' => 'dashboard',
				'export' => 'dashboard',
			);

			return isset($map[$class]) ? $map[$class] : 'dashboard';
		}
	}

	if (!function_exists('admin_page_scope')) {
		function admin_page_scope()
		{
			$CI =& get_instance();
			$seg2 = $CI->uri->segment(2);
			if ($seg2 === false || $seg2 === '') {
				$seg2 = 'home';
			}

			$map = array(
				'transaction' => 'orders',
				'orders' => 'orders',
				'report' => 'orders',
				'products' => 'products',
				'product' => 'products',
				'inventory' => 'inventory',
				'receipts' => 'inventory',
				'stock-receipts' => 'inventory',
				'stock-inventory' => 'inventory',
				'stock-movements' => 'inventory',
				'suppliers' => 'inventory',
				'ai-assistant' => 'ai',
				'support-chat' => 'support',
				'users' => 'users',
				'catalog' => 'catalog',
				'posts' => 'posts',
				'store' => 'store',
				'branding' => 'branding',
				'voucher' => 'marketing',
				'slider' => 'content',
				'banner' => 'content',
				'page' => 'content',
				'pages' => 'content',
				'tuyendung' => 'content',
				'hoptac' => 'content',
				'vanchuyen' => 'content',
				'admin' => 'staff',
				'home' => 'dashboard',
				'login' => 'auth',
				'export' => 'dashboard',
			);

			return isset($map[$seg2]) ? $map[$seg2] : 'dashboard';
		}
	}

	if (!function_exists('admin_infer_flash_scope_from_message')) {
		function admin_infer_flash_scope_from_message($message)
		{
			$text = mb_strtolower(trim((string) $message), 'UTF-8');
			if ($text === '') {
				return '';
			}

			if (mb_strpos($text, 'đơn đặt hàng') !== false || mb_strpos($text, 'đơn hàng') !== false || mb_strpos($text, 'đơn cần') !== false || mb_strpos($text, 'giao hàng') !== false) {
				return 'orders';
			}
			if (mb_strpos($text, 'trợ lý ai') !== false || mb_strpos($text, 'cấu hình trợ lý') !== false || mb_strpos($text, 'faq') !== false || mb_strpos($text, 'hội thoại') !== false) {
				return 'ai';
			}
			if (mb_strpos($text, 'hỗ trợ khách') !== false) {
				return 'support';
			}
			if (mb_strpos($text, 'tồn kho') !== false || mb_strpos($text, 'nhập kho') !== false || mb_strpos($text, 'phiếu nhập') !== false || mb_strpos($text, 'nhà cung cấp') !== false || mb_strpos($text, 'kiểm kê') !== false) {
				return 'inventory';
			}
			if (mb_strpos($text, 'sản phẩm') !== false) {
				return 'products';
			}
			if (mb_strpos($text, 'danh mục') !== false) {
				return 'catalog';
			}
			if (mb_strpos($text, 'voucher') !== false || mb_strpos($text, 'mã giảm') !== false) {
				return 'marketing';
			}
			if (mb_strpos($text, 'tin tức') !== false || mb_strpos($text, 'bài viết') !== false) {
				return 'posts';
			}
			if (mb_strpos($text, 'slider') !== false || mb_strpos($text, 'banner') !== false || mb_strpos($text, 'hợp tác') !== false || mb_strpos($text, 'tuyển dụng') !== false || mb_strpos($text, 'vận chuyển') !== false) {
				return 'content';
			}
			if (mb_strpos($text, 'thành viên') !== false || mb_strpos($text, 'nhân viên') !== false || mb_strpos($text, ' admin') !== false) {
				return 'staff';
			}
			if (mb_strpos($text, 'khách hàng') !== false || mb_strpos($text, ' user') !== false) {
				return 'users';
			}
			if (mb_strpos($text, 'cửa hàng') !== false) {
				return 'store';
			}
			if (mb_strpos($text, 'logo') !== false || mb_strpos($text, 'branding') !== false) {
				return 'branding';
			}
			if (mb_strpos($text, 'đăng nhập') !== false || mb_strpos($text, 'đăng xuất') !== false) {
				return 'dashboard';
			}

			return '';
		}
	}

	if (!function_exists('admin_flash_scope_matches_page')) {
		function admin_flash_scope_matches_page($flash_scope, $page_scope = null)
		{
			if ($page_scope === null) {
				$page_scope = admin_page_scope();
			}

			$flash_scope = trim((string) $flash_scope);
			if ($flash_scope === '') {
				return false;
			}

			if ($page_scope === 'auth') {
				return in_array($flash_scope, array('auth', 'dashboard'), true);
			}

			return $flash_scope === $page_scope;
		}
	}

	if (!function_exists('admin_purge_stale_admin_flash_userdata')) {
		function admin_purge_stale_admin_flash_userdata()
		{
			$CI =& get_instance();
			$CI->session->unset_userdata(array('admin_message_success', 'admin_message_fail', 'admin_message'));
		}
	}

	if (!function_exists('admin_flash_fail_home')) {
		function admin_flash_fail_home($message)
		{
			admin_set_scoped_flash('fail', $message, 'dashboard');
		}
	}

	if (!function_exists('admin_set_scoped_flash')) {
		function admin_set_scoped_flash($type, $message, $scope = null)
		{
			$CI =& get_instance();
			$type = in_array($type, array('success', 'fail', 'info'), true) ? $type : 'info';
			if ($scope === null) {
				$scope = admin_flash_scope_for_controller();
			}

			$CI->session->unset_userdata(array('admin_message_success', 'admin_message_fail', 'admin_message'));
			$CI->session->set_flashdata('admin_flash', array(
				'type' => $type,
				'message' => (string) $message,
				'scope' => (string) $scope,
			));
		}
	}

	if (!function_exists('admin_consume_scoped_flash')) {
		function admin_consume_scoped_flash()
		{
			$CI =& get_instance();
			$page_scope = admin_page_scope();

			$bundle = $CI->session->flashdata('admin_flash');
			if (is_array($bundle) && !empty($bundle['message'])) {
				$scope = isset($bundle['scope']) ? trim((string) $bundle['scope']) : '';
				if (admin_flash_scope_matches_page($scope, $page_scope)) {
					return array(
						'type' => isset($bundle['type']) ? (string) $bundle['type'] : 'info',
						'message' => (string) $bundle['message'],
					);
				}
				return null;
			}

			$legacy = array(
				array('key' => 'admin_message_success', 'type' => 'success'),
				array('key' => 'admin_message_fail', 'type' => 'fail'),
				array('key' => 'admin_message', 'type' => 'info'),
			);

			foreach ($legacy as $item) {
				$message = $CI->session->flashdata($item['key']);
				if ($message === null || $message === '') {
					continue;
				}
				$scope = admin_infer_flash_scope_from_message($message);
				if (admin_flash_scope_matches_page($scope, $page_scope)) {
					return array(
						'type' => $item['type'],
						'message' => (string) $message,
					);
				}
			}

			return null;
		}
	}

	if (!function_exists('admin_flash_success')) {
		function admin_flash_success($message, $scope = null)
		{
			admin_set_scoped_flash('success', $message, $scope);
		}
	}

	if (!function_exists('admin_flash_fail')) {
		function admin_flash_fail($message, $scope = null)
		{
			admin_set_scoped_flash('fail', $message, $scope);
		}
	}

	if (!function_exists('admin_flash_info')) {
		function admin_flash_info($message, $scope = null)
		{
			admin_set_scoped_flash('info', $message, $scope);
		}
	}

	if (!function_exists('admin_discard_storefront_flash')) {
		function admin_discard_storefront_flash()
		{
			$CI =& get_instance();
			$keys = array(
				'store_message_success',
				'store_message_fail',
				'auth_message_success',
				'auth_message_fail',
				'message',
				'message_success',
				'message_fail',
			);
			foreach ($keys as $key) {
				$CI->session->flashdata($key);
			}
		}
	}

	if (!function_exists('user_registered_timestamp')) {
		function user_registered_timestamp($user, $addresses = array())
		{
			$created = isset($user->created) ? $user->created : null;

			if (!is_numeric($created) && is_string($created) && trim($created) !== '') {
				$ts = strtotime($created);
				if ($ts !== false && $ts >= 946684800) {
					return $ts;
				}
			}

			if (is_numeric($created)) {
				$n = (int) $created;
				if ($n >= 946684800) {
					return $n;
				}
			}

			if (!empty($addresses)) {
				$min = null;
				foreach ($addresses as $addr) {
					if (!isset($addr->created)) {
						continue;
					}
					$c = (int) $addr->created;
					if ($c >= 946684800 && ($min === null || $c < $min)) {
						$min = $c;
					}
				}
				if ($min !== null) {
					return $min;
				}
			}

			return null;
		}
	}

	if (!function_exists('format_user_registered')) {
		function format_user_registered($user, $addresses = array())
		{
			$ts = user_registered_timestamp($user, $addresses);
			if ($ts !== null) {
				return mdate('%d/%m/%Y %H:%i', $ts);
			}

			if (isset($user->created) && is_numeric($user->created)) {
				$n = (int) $user->created;
				if ($n >= 2000 && $n <= 2100) {
					return 'Năm ' . $n;
				}
			}

			return '—';
		}
	}

	if (!function_exists('admin_require_post')) {
		function admin_require_post()
		{
			$CI =& get_instance();
			if (strtoupper($CI->input->method()) !== 'POST') {
				show_404();
				exit;
			}
		}
	}

	if (!function_exists('admin_csrf_post_button')) {
		function admin_csrf_post_button($action_url, $inner_html, array $options = array())
		{
			$class = isset($options['class']) ? $options['class'] : 'btn btn-sm btn-outline-danger';
			$title = isset($options['title']) ? $options['title'] : '';
			$confirm = isset($options['confirm']) ? $options['confirm'] : '';
			$form_class = isset($options['form_class']) ? $options['form_class'] : 'd-inline';

			$confirm_attr = '';
			if ($confirm !== '') {
				$confirm_attr = ' onclick="return confirm(' . json_encode($confirm, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) . ')"';
			}

			$title_attr = $title !== '' ? ' title="' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '"' : '';

			$hidden = '';
			if (!empty($options['hidden']) && is_array($options['hidden'])) {
				foreach ($options['hidden'] as $name => $value) {
					$hidden .= '<input type="hidden" name="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '" value="' . htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') . '">';
				}
			}

			return '<form method="post" action="' . htmlspecialchars($action_url, ENT_QUOTES, 'UTF-8') . '" class="' . htmlspecialchars($form_class, ENT_QUOTES, 'UTF-8') . '">'
				. csrf_field()
				. $hidden
				. '<button type="submit" class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '"' . $title_attr . $confirm_attr . '>' . $inner_html . '</button>'
				. '</form>';
		}
	}

	if (!function_exists('admin_prompt_post_button')) {
		function admin_prompt_post_button($action_url, $inner_html, $prompt, array $options = array())
		{
			$form_id = 'admin-prompt-post-' . substr(md5($action_url . $prompt), 0, 10);
			$class = isset($options['class']) ? $options['class'] : 'btn btn-danger';
			$form_class = isset($options['form_class']) ? $options['form_class'] : 'd-inline';

			return '<form id="' . htmlspecialchars($form_id, ENT_QUOTES, 'UTF-8') . '" method="post" action="' . htmlspecialchars($action_url, ENT_QUOTES, 'UTF-8') . '" class="' . htmlspecialchars($form_class, ENT_QUOTES, 'UTF-8') . '">'
				. csrf_field()
				. '<input type="hidden" name="reason" value="">'
				. '<button type="button" class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '" onclick="adminPromptPost(' . json_encode($form_id) . ',' . json_encode($prompt, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) . ')">' . $inner_html . '</button>'
				. '</form>';
		}
	}

	if (!function_exists('admin_pending_orders_count')) {
		function admin_pending_orders_count()
		{
			$CI =& get_instance();
			$CI->load->model('transaction_model');

			return $CI->transaction_model->count_pending_for_admin();
		}
	}
?>