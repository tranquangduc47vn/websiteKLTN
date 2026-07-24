<?php
/**
 * Chẩn đoán ảnh sản phẩm trên hosting — xóa file này sau khi xong.
 * Truy cập: https://qddesign.shop/image_diag.php
 */
header('Content-Type: text/plain; charset=utf-8');

define('BASEPATH', true);
define('ENVIRONMENT', 'production');

$configFile = __DIR__ . '/application/config/database.php';
if (!is_readable($configFile)) {
	echo "Thiếu application/config/database.php\n";
	exit(1);
}

require $configFile;

$c = $db['default'];
$m = new mysqli($c['hostname'], $c['username'], $c['password'], $c['database']);
if ($m->connect_error) {
	echo 'DB error: ' . $m->connect_error . "\n";
	exit(1);
}

$uploadDir = __DIR__ . '/upload/product';
echo "FCPATH/upload/product: {$uploadDir}\n";
echo 'Folder exists: ' . (is_dir($uploadDir) ? 'YES' : 'NO') . "\n";
echo 'Files on disk: ' . count(glob($uploadDir . '/*') ?: array()) . "\n\n";

$host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$base = $scheme . '://' . $host . '/';

$r = $m->query('SELECT id, name, image_link FROM product ORDER BY id ASC LIMIT 10');
$missing = 0;
$ok = 0;

while ($row = $r->fetch_assoc()) {
	$file = $uploadDir . '/' . $row['image_link'];
	$exists = is_file($file);
	if ($exists) {
		$ok++;
	} else {
		$missing++;
	}
	$encoded = implode('/', array_map('rawurlencode', explode('/', $row['image_link'])));
	$url = $base . 'upload/product/' . $encoded;
	echo ($exists ? '[OK] ' : '[MISSING] ') . '#' . $row['id'] . ' ' . $row['image_link'] . "\n";
	echo '  File: ' . ($exists ? 'found' : 'NOT FOUND') . "\n";
	echo '  Test URL: ' . $url . "\n\n";
}

$total = $ok + $missing;
echo "Sample: {$ok}/{$total} files match DB (first 10 products)\n";
echo "\nXóa file image_diag.php sau khi kiểm tra xong.\n";
