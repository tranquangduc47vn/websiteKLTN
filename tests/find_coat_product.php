<?php
define('BASEPATH', true);
define('ENVIRONMENT', 'development');
require __DIR__ . '/../application/config/database.php';
$m = new mysqli($db['default']['hostname'], $db['default']['username'], $db['default']['password'], $db['default']['database']);
$r = $m->query("SELECT id, name, image_link FROM product WHERE name LIKE '%dạ%' OR name LIKE '%ao d%'");
while ($x = $r->fetch_assoc()) {
	$f = __DIR__ . '/../upload/product/' . $x['image_link'];
	echo $x['id'] . ' | ' . $x['name'] . ' | ' . $x['image_link'] . ' | ' . (file_exists($f) ? 'OK' : 'MISS') . PHP_EOL;
}
