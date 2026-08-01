<?php
/**
 * Export dữ liệu local (13 SP khớp ảnh) để import lên hosting.
 * Chạy: php databaseaddphpmyadmin/export_local_for_hosting.php
 * Output: databaseaddphpmyadmin/local_for_hosting.sql
 */
define('BASEPATH', true);
define('ENVIRONMENT', 'development');

require __DIR__ . '/../application/config/database.php';

$c = $db['default'];
$m = new mysqli($c['hostname'], $c['username'], $c['password'], $c['database']);
if ($m->connect_error) {
	fwrite(STDERR, 'DB error: ' . $m->connect_error . PHP_EOL);
	exit(1);
}

$m->set_charset('utf8mb4');

$out = __DIR__ . '/local_for_hosting.sql';
$fh = fopen($out, 'w');
if (!$fh) {
	fwrite(STDERR, "Cannot write {$out}\n");
	exit(1);
}

fwrite($fh, "-- Export local DB for hosting (product images match upload/product)\n");
fwrite($fh, "-- Generated: " . date('Y-m-d H:i:s') . "\n\n");
fwrite($fh, "SET FOREIGN_KEY_CHECKS=0;\n");
fwrite($fh, "SET NAMES utf8mb4;\n\n");

$tables = array(
	'catalog',
	'product',
	'product_variant',
	'product_variants',
	'content_pages',
	'product_image',
	'slider',
);

foreach ($tables as $table) {
	$exists = $m->query("SHOW TABLES LIKE '{$table}'");
	if (!$exists || $exists->num_rows === 0) {
		fwrite($fh, "-- Skip missing table: {$table}\n\n");
		continue;
	}

	fwrite($fh, "TRUNCATE TABLE `{$table}`;\n");

	$r = $m->query("SELECT * FROM `{$table}`");
	if (!$r) {
		fwrite($fh, "-- Error reading {$table}: " . $m->error . "\n\n");
		continue;
	}

	$cols = null;
	while ($row = $r->fetch_assoc()) {
		if ($cols === null) {
			$cols = array_keys($row);
		}
		if ($table === 'slider' && isset($row['link'])) {
			$row['link'] = preg_replace('#^https?://localhost/webshop/#', '', $row['link']);
		}
		$vals = array();
		foreach ($cols as $col) {
			if ($row[$col] === null) {
				$vals[] = 'NULL';
			} else {
				$vals[] = "'" . $m->real_escape_string($row[$col]) . "'";
			}
		}
		fwrite($fh, 'INSERT INTO `' . $table . '` (`' . implode('`,`', $cols) . '`) VALUES (' . implode(',', $vals) . ");\n");
	}
	fwrite($fh, "\n");
}

fwrite($fh, "SET FOREIGN_KEY_CHECKS=1;\n");
fclose($fh);

$products = $m->query('SELECT COUNT(*) AS c FROM product')->fetch_assoc()['c'];
echo "Exported to {$out}\n";
echo "Products: {$products}\n";
echo "Upload local_for_hosting.sql via phpMyAdmin → database qddesign_webshop\n";
