<?php
/**
 * Mô phỏng luồng trình duyệt: thêm giỏ → logout → login → khôi phục.
 * Run: php tests/manual_cart_restore_flow.php
 */
define('ENVIRONMENT', isset($_SERVER['CI_ENV']) ? $_SERVER['CI_ENV'] : 'development');

$root = dirname(__DIR__);
chdir($root);
ob_start();
require $root . '/index.php';
ob_end_clean();

$CI =& get_instance();
$CI->load->helper('cart');

$passed = 0;
$failed = 0;

function assert_case($label, $cond, $detail = '')
{
	global $passed, $failed;
	if ($cond) {
		echo 'PASS: ' . $label . PHP_EOL;
		$passed++;
	} else {
		echo 'FAIL: ' . $label . ($detail !== '' ? ' — ' . $detail : '') . PHP_EOL;
		$failed++;
	}
}

$user = $CI->db->query('SELECT * FROM user ORDER BY id ASC LIMIT 1')->row();
if (!$user) {
	echo "SKIP: no user\n";
	exit(1);
}

unset($user->password);
$user_id = (int) $user->id;
$rowid = '88888_' . md5('flow-test');

$sample = array(
	$rowid => array(
		'rowid' => $rowid,
		'id' => 88888,
		'variant_id' => 0,
		'qty' => 3,
		'price' => 99000,
		'name' => 'Flow test item',
		'subtotal' => 297000,
		'options' => array('size' => 'L', 'color' => 'Trắng', 'image_link' => 'no-image.png'),
	),
);

// --- Request 1: logged in, add to cart ---
$CI->session->set_userdata('user', $user);
cart_persist_session($sample);
assert_case('session has item after add', !empty(cart_get_session()));

$db = $CI->db->where('user_id', $user_id)->get('user_saved_cart', 1)->row();
assert_case('DB saved after add', !empty($db) && strpos((string) $db->cart_json, 'Flow test item') !== false);

// --- Request 2: logout ---
cart_sync_to_user($user_id, cart_get_session());
$CI->session->unset_userdata('user');
$CI->session->unset_userdata('custom_cart');
assert_case('session cart cleared on logout', empty(cart_get_session()));

$db2 = $CI->db->where('user_id', $user_id)->get('user_saved_cart', 1)->row();
assert_case('DB still has cart after logout', !empty($db2) && strpos((string) $db2->cart_json, 'Flow test item') !== false);

// --- Request 3: login (constructor clears guest cart first) ---
$CI->session->unset_userdata('custom_cart'); // MY_Controller guest branch
$CI->session->set_userdata('user', $user);
cart_restore_for_user(cart_resolve_user_id($user));
assert_case('session restored after login', !empty(cart_get_session()));
assert_case('restored qty = 3', isset(cart_get_session()[$rowid]['qty']) && (int) cart_get_session()[$rowid]['qty'] === 3);

// --- Request 4: next page load via MY_Controller helper ---
$CI->session->unset_userdata('custom_cart');
cart_ensure_for_logged_in_user($user);
assert_case('cart_ensure restores when session empty', !empty(cart_get_session()));

// cleanup
$CI->db->where('user_id', $user_id)->delete('user_saved_cart');
$CI->session->unset_userdata('custom_cart');
$CI->session->unset_userdata('user');

echo PHP_EOL . "Results: {$passed} passed, {$failed} failed" . PHP_EOL;
exit($failed > 0 ? 1 : 0);
