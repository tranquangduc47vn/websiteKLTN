<?php
/**
 * Kiểm tra lưu/khôi phục giỏ hàng theo user (logout → login).
 * Run: php tests/manual_cart_restore.php
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
		echo 'FAIL: ' . $label;
		if ($detail !== '') {
			echo ' — ' . $detail;
		}
		echo PHP_EOL;
		$failed++;
	}
}

assert_case('cart storage ready', cart_storage_ready());

$user = $CI->db->query('SELECT id, email FROM user ORDER BY id ASC LIMIT 1')->row();
if (!$user) {
	echo "SKIP: no user row in DB\n";
	exit($failed > 0 ? 1 : 0);
}

$user_id = (int) $user->id;
$rowid = '99999_' . md5('restore-test');

$sample_cart = array(
	$rowid => array(
		'rowid' => $rowid,
		'id' => 99999,
		'variant_id' => 0,
		'qty' => 2,
		'price' => 150000,
		'name' => 'Cart restore test item',
		'subtotal' => 300000,
		'options' => array(
			'image_link' => 'no-image.png',
			'size' => 'M',
			'color' => 'Đen',
		),
	),
);

cart_sync_to_user($user_id, $sample_cart);

$db_row = null;
if ($CI->db->table_exists('user_saved_cart')) {
	$db_row = $CI->db->where('user_id', $user_id)->get('user_saved_cart', 1)->row();
}

assert_case('user_saved_cart row exists', !empty($db_row));
if ($db_row) {
	$decoded = json_decode((string) $db_row->cart_json, true);
	assert_case('DB cart_json decodes', is_array($decoded));
	assert_case('DB cart item count', is_array($decoded) && count($decoded) === 1);
	assert_case('DB cart qty matches', isset($decoded[$rowid]['qty']) && (int) $decoded[$rowid]['qty'] === 2);
}

$CI->session->set_userdata('custom_cart', array());
assert_case('session cleared before restore', empty(cart_get_session()));

$restored = cart_restore_for_user($user_id);
assert_case('cart_restore_for_user returns true', $restored === true);

$session_cart = cart_get_session();
assert_case('session restored item count', count($session_cart) === 1);
assert_case('session restored qty matches', isset($session_cart[$rowid]['qty']) && (int) $session_cart[$rowid]['qty'] === 2);
assert_case('session restored name matches', isset($session_cart[$rowid]['name']) && $session_cart[$rowid]['name'] === 'Cart restore test item');

$resolved = cart_resolve_user_id((object) array('email' => $user->email));
assert_case('cart_resolve_user_id via email', $resolved === $user_id);

if ($CI->db->table_exists('user_saved_cart')) {
	$CI->db->where('user_id', $user_id)->delete('user_saved_cart');
}

echo PHP_EOL . "Results: {$passed} passed, {$failed} failed" . PHP_EOL;
exit($failed > 0 ? 1 : 0);
