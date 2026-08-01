<?php
/**
 * Simulate two concurrent checkout deducts on the same variant.
 * Run: php tests/manual_concurrent_stock_deduct.php
 */
define('ENVIRONMENT', isset($_SERVER['CI_ENV']) ? $_SERVER['CI_ENV'] : 'development');

$root = dirname(__DIR__);
chdir($root);
ob_start();
require $root . '/index.php';
ob_end_clean();

$CI =& get_instance();
$CI->load->library('inventory_service');
$CI->load->library('product_service');

$passed = 0;
$failed = 0;

function assert_case($label, $cond)
{
	global $passed, $failed;
	if ($cond) {
		echo "PASS: {$label}\n";
		$passed++;
	} else {
		echo "FAIL: {$label}\n";
		$failed++;
	}
}

$row = $CI->db->query("
	SELECT pv.id AS variant_id, pv.product_id, pv.stock, pv.color, pv.size, p.name
	FROM product_variants pv
	INNER JOIN product p ON p.id = pv.product_id
	WHERE pv.status = 1
	ORDER BY pv.id ASC
	LIMIT 1
")->row();

if (!$row) {
	echo "SKIP: No product_variants row.\n";
	exit(0);
}

$variant_id = (int) $row->variant_id;
$original_stock = (int) $row->stock;
$test_stock = 5;

$CI->db->where('id', $variant_id)->update('product_variants', array(
	'stock' => $test_stock,
	'updated' => time(),
));
$CI->product_service->sync_product_quantity_cache((int) $row->product_id);

function attempt_deduct($variant_id, $qty, $ref)
{
	$CI =& get_instance();
	$CI->load->library('inventory_service');
	$CI->db->trans_start();
	$result = $CI->inventory_service->deduct_for_order($variant_id, $qty, $ref, 0, 'test concurrent', false);
	if (empty($result['ok'])) {
		$CI->db->trans_rollback();
		return array('ok' => false, 'message' => isset($result['message']) ? $result['message'] : '');
	}
	$CI->db->trans_complete();
	return array('ok' => $CI->db->trans_status() !== false, 'message' => '');
}

$r1 = attempt_deduct($variant_id, 5, 900001);
$r2 = attempt_deduct($variant_id, 5, 900002);

$after = (int) $CI->db->select('stock')->where('id', $variant_id)->get('product_variants')->row()->stock;

assert_case('first deduct succeeds', !empty($r1['ok']));
assert_case('second deduct fails', empty($r2['ok']));
assert_case('final stock is 0', $after === 0);
assert_case('failure message mentions stock', !$r2['ok'] && stripos($r2['message'], 'chỉ còn') !== false);

$CI->db->where('id', $variant_id)->update('product_variants', array(
	'stock' => $original_stock,
	'updated' => time(),
));
$CI->product_service->sync_product_quantity_cache((int) $row->product_id);
$CI->db->where('reference_id IN (900001, 900002)')->where('reference_type', 'order')->delete('stock_movements');

echo "\nVariant #{$variant_id} ({$row->name})\n";
echo "First: " . ($r1['ok'] ? 'OK' : $r1['message']) . "\n";
echo "Second: " . ($r2['ok'] ? 'OK' : $r2['message']) . "\n";
echo "Stock after: {$after}\n";
echo "---\nTotal: {$passed} PASS, {$failed} FAIL\n";
exit($failed > 0 ? 1 : 0);
