<?php
$sql = file_get_contents(__DIR__ . '/../databaseaddphpmyadmin/webshop.sql');
$uploadDir = realpath(__DIR__ . '/../upload/product');
$disk = array();
foreach (glob($uploadDir . '/*') as $f) {
	$disk[basename($f)] = true;
}

preg_match_all("/,\s*'([^']+\.(?:jpg|jpeg|png|gif|webp))',\s*'\[/i", $sql, $m);
$links = array_unique($m[1]);
$missing = 0;
foreach ($links as $link) {
	if (!isset($disk[$link])) {
		$missing++;
		if ($missing <= 10) {
			echo 'SQL missing on disk: ' . $link . PHP_EOL;
		}
	}
}
echo 'Unique product main images in webshop.sql: ' . count($links) . PHP_EOL;
echo 'Missing on disk: ' . $missing . PHP_EOL;
echo 'Disk files: ' . count($disk) . PHP_EOL;
