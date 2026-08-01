<?php
/**
 * CLI integrity checks after DB refactor.
 *
 *   php tests/manual_db_integrity.php
 */
define('ENVIRONMENT', isset($_SERVER['CI_ENV']) ? $_SERVER['CI_ENV'] : 'development');

$root = dirname(__DIR__);
chdir($root);
ob_start();
require $root . '/index.php';
ob_end_clean();

$CI =& get_instance();
$db = $CI->db;

$passed = 0;
$failed = 0;
$warned = 0;

function check($label, $cond, $detail = '')
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

function warn($label, $detail = '')
{
	global $warned;
	echo 'WARN: ' . $label;
	if ($detail !== '') {
		echo ' — ' . $detail;
	}
	echo PHP_EOL;
	$warned++;
}

// 1. product.quantity matches SUM(product_variants.stock)
if ($db->table_exists('product') && $db->table_exists('product_variants')) {
	$row = $db->query("
		SELECT COUNT(*) AS c FROM product p
		LEFT JOIN (
			SELECT product_id, COALESCE(SUM(stock), 0) AS variant_sum
			FROM product_variants GROUP BY product_id
		) vs ON vs.product_id = p.id
		WHERE p.quantity != COALESCE(vs.variant_sum, 0)
	")->row();
	$mismatches = $row ? (int) $row->c : -1;
	check('product.quantity matches variant stock sum', $mismatches === 0, 'mismatches=' . $mismatches);
} else {
	warn('product.quantity cache check skipped', 'product or product_variants missing');
}

// 2. No orphan variant product_ids
if ($db->table_exists('product_variants')) {
	$row = $db->query('
		SELECT COUNT(*) AS c FROM product_variants pv
		LEFT JOIN product p ON p.id = pv.product_id
		WHERE p.id IS NULL
	')->row();
	$orphans = $row ? (int) $row->c : -1;
	check('no orphan product_variants.product_id', $orphans === 0, 'orphans=' . $orphans);
} else {
	warn('orphan variant check skipped', 'product_variants missing');
}

// 3. Cart storage
if ($db->table_exists('user_saved_cart')) {
	check('user_saved_cart table exists', true);
} else {
	check('user_saved_cart table exists', false);
}

if ($db->table_exists('user')) {
	check('user.saved_cart column removed', !$db->field_exists('saved_cart', 'user'));
}

// 4. Legacy inventory tables dropped
check('product_inventory dropped', !$db->table_exists('product_inventory'));
check('product_colors dropped', !$db->table_exists('product_colors'));

// 5. Unified CMS
if ($db->table_exists('content_pages')) {
	check('content_pages table exists', true);
	check('legacy news table dropped', !$db->table_exists('news'));
	check('legacy pages table dropped', !$db->table_exists('pages'));
	$row = $db->query("SELECT COUNT(*) AS c FROM content_pages")->row();
	$count = $row ? (int) $row->c : 0;
	check('content_pages has data', $count > 0, 'rows=' . $count);
} else {
	warn('content_pages missing', 'run run_migrations.php');
}

// 6. order.variant_id backfill status
if ($db->table_exists('order') && $db->field_exists('variant_id', 'order') && $db->table_exists('product_variants')) {
	$row = $db->query('SELECT COUNT(*) AS c FROM `order` WHERE variant_id = 0')->row();
	$missing = $row ? (int) $row->c : 0;
	if ($missing > 0) {
		warn('orders without variant_id', $missing . ' row(s) — may be unmatchable color/size');
	} else {
		check('all orders have variant_id', true);
	}
}

// 5. FK presence (informational)
$fks = array(
	array('product_variants', 'fk_pv_product'),
	array('user_saved_cart', 'fk_user_saved_cart_user'),
	array('stock_receipt_items', 'fk_sri_receipt'),
	array('stock_receipts', 'fk_sr_supplier'),
	array('stock_movements', 'fk_sm_variant'),
	array('password_resets', 'fk_password_resets_user'),
	array('user_phone', 'fk_user_phone_user'),
	array('product_review', 'fk_product_review_product'),
	array('user_point_log', 'fk_user_point_log_user'),
	array('voucher_use', 'fk_voucher_use_voucher'),
	array('voucher_use', 'fk_voucher_use_transaction'),
	array('ai_message', 'fk_ai_message_conversation'),
);
foreach ($fks as $fk) {
	list($table, $name) = $fk;
	if (!$db->table_exists($table)) {
		continue;
	}
	$row = $db->query("
		SELECT COUNT(*) AS c FROM information_schema.TABLE_CONSTRAINTS
		WHERE TABLE_SCHEMA = DATABASE()
			AND TABLE_NAME = ?
			AND CONSTRAINT_NAME = ?
			AND CONSTRAINT_TYPE = 'FOREIGN KEY'
	", array($table, $name))->row();
	$exists = $row && (int) $row->c > 0;
	check("FK {$name} on {$table}", $exists, $exists ? '' : 'run run_migrations.php');
}

echo PHP_EOL . "Total: {$passed} PASS, {$failed} FAIL, {$warned} WARN" . PHP_EOL;
exit($failed > 0 ? 1 : 0);
