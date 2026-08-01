<?php
$_SERVER['CI_ENV'] = 'development';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['SCRIPT_NAME'] = '/index.php';
chdir(__DIR__ . '/../');
ob_start();
require __DIR__ . '/../index.php';
ob_end_clean();

$CI =& get_instance();
$CI->load->helper(array('admin_dashboard', 'admin', 'permission'));

try {
	$dash = admin_dashboard_get_data(array('period' => '30d'));
	echo "get_data OK\n";
	$login = (object) array('id' => 1, 'level' => 10, 'name' => 'Admin');
	$perm = array(
		'orders' => true,
		'revenue' => true,
		'products' => true,
		'inventory' => true,
		'users' => true,
		'catalog' => true,
		'voucher' => true,
		'posts' => true,
		'slider' => true,
		'staff' => true,
	);
	$groups = admin_dashboard_notification_groups($dash, $perm, $login);
	echo 'notify_groups=' . count($groups) . "\n";

	ob_start();
	$CI->load->view('admin/home/index', array(
		'login' => $login,
		'temp' => 'admin/home/index',
	));
	$html = ob_get_clean();
	echo 'view_bytes=' . strlen($html) . "\n";
	echo (strpos($html, 'adm-dash-hero') !== false) ? "view_has_hero=1\n" : "view_has_hero=0\n";
} catch (Throwable $e) {
	echo 'ERR: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine() . "\n";
}
