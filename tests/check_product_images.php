<?php
define('BASEPATH', true);
define('ENVIRONMENT', 'development');
require __DIR__ . '/../application/config/database.php';

$c = $db['default'];
$m = new mysqli($c['hostname'], $c['username'], $c['password'], $c['database']);
if ($m->connect_error) {
	echo 'DB error: ' . $m->connect_error . PHP_EOL;
	exit(1);
}

function check_table_images($m, $table, $dir, $col = 'image_link')
{
	$uploadDir = realpath(__DIR__ . '/../' . ltrim($dir, '/'));
	if (!$uploadDir || !is_dir($uploadDir)) {
		echo strtoupper($table) . ': dir missing ' . $dir . PHP_EOL;
		return;
	}
	$diskFiles = array();
	foreach (glob($uploadDir . '/*') as $f) {
		$diskFiles[basename($f)] = true;
	}
	$r = $m->query("SELECT id, {$col} FROM `{$table}`");
	$missing = 0;
	$total = 0;
	while ($row = $r->fetch_assoc()) {
		$total++;
		if (empty($row[$col]) || !isset($diskFiles[$row[$col]])) {
			$missing++;
			if ($missing <= 5) {
				echo strtoupper($table) . " MISSING #{$row['id']}: {$row[$col]}" . PHP_EOL;
			}
		}
	}
	echo strtoupper($table) . ": total={$total}, disk=" . count($diskFiles) . ", missing={$missing}" . PHP_EOL;
}

check_table_images($m, 'product', 'upload/product');
check_table_images($m, 'slider', 'upload/slider');
check_table_images($m, 'banner', 'upload/banner');
check_table_images($m, 'news', 'upload/news', 'image_link');
