<?php
define('BASEPATH', true);
define('ENVIRONMENT', 'development');
require __DIR__ . '/../application/config/database.php';

$m = new mysqli($db['default']['hostname'], $db['default']['username'], $db['default']['password'], $db['default']['database']);
$r = $m->query('SELECT COUNT(*) c FROM product');
echo 'Products in DB: ' . $r->fetch_assoc()['c'] . PHP_EOL;

$uploadDir = realpath(__DIR__ . '/../upload/product');
$disk = array();
foreach (glob($uploadDir . '/*') as $f) {
	$disk[basename($f)] = true;
}

$r = $m->query('SELECT id, name, image_link, image_list FROM product');
while ($p = $r->fetch_assoc()) {
	$mainOk = isset($disk[$p['image_link']]);
	$list = json_decode($p['image_list'], true);
	$listMissing = 0;
	if (is_array($list)) {
		foreach ($list as $img) {
			if (!isset($disk[$img])) {
				$listMissing++;
			}
		}
	}
	if (!$mainOk || $listMissing > 0) {
		echo "#{$p['id']} {$p['name']} main=" . ($mainOk ? 'OK' : 'MISS:' . $p['image_link']) . " gallery_missing={$listMissing}" . PHP_EOL;
	}
}
