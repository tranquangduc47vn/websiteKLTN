<?php
/**
 * Smoke test export builders (no HTTP). Run: php tests/manual_export_smoke.php
 */
define('ENVIRONMENT', 'development');

$root = dirname(__DIR__);
chdir($root);
ob_start();
require $root . '/index.php';
ob_end_clean();

require_once APPPATH . 'services/export/ExportService.php';

$filter = ExportFilter::fromInput(array('scope' => 'all'));
$login = (object) array('id' => 1, 'name' => 'Test Admin', 'level' => 0);

$passed = 0;
$failed = 0;

function ok($label, $cond, $detail = '')
{
	global $passed, $failed;
	if ($cond) {
		echo "PASS: {$label}\n";
		$passed++;
	} else {
		echo "FAIL: {$label}" . ($detail !== '' ? " — {$detail}" : '') . "\n";
		$failed++;
	}
}

foreach (ExportService::supportedModules() as $module) {
	try {
		$def = ExportService::buildDefinition($module, $filter, $login);
		ok("build {$module}", $def instanceof ExportReportDefinition && $def->title !== '');
	} catch (Throwable $e) {
		ok("build {$module}", false, $e->getMessage());
	}
}

try {
	$pdf = ExportService::renderPdf('orders', $filter, $login);
	ok('pdf orders bytes', !empty($pdf['content']) && strncmp($pdf['content'], '%PDF', 4) === 0);
} catch (Throwable $e) {
	ok('pdf orders bytes', false, $e->getMessage());
}

try {
	$xlsx = ExportService::renderExcel('orders', $filter, $login);
	ok('excel orders bytes', !empty($xlsx['content']) && strncmp($xlsx['content'], 'PK', 2) === 0);
} catch (Throwable $e) {
	ok('excel orders bytes', false, $e->getMessage());
}

echo "\nResult: {$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
