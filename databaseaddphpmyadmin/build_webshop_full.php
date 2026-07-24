<?php
/**
 * Gộp schema migration + backfill biến thể vào webshop.sql.
 *
 *   php databaseaddphpmyadmin/build_webshop_full.php
 *
 * Cần application/config/database.php trỏ tới MySQL local.
 */
define('BASEPATH', true);
define('ENVIRONMENT', 'development');

$sqlFile = __DIR__ . '/webshop.sql';
$dbConfigFile = __DIR__ . '/../application/config/database.php';
if (!is_readable($sqlFile)) {
	fwrite(STDERR, "Missing {$sqlFile}\n");
	exit(1);
}
if (!is_readable($dbConfigFile)) {
	fwrite(STDERR, "Missing {$dbConfigFile}\n");
	exit(1);
}

require $dbConfigFile;
$c = $db['default'];
$m = new mysqli($c['hostname'], $c['username'], $c['password']);
if ($m->connect_error) {
	fwrite(STDERR, 'Connect error: ' . $m->connect_error . PHP_EOL);
	exit(1);
}
$m->set_charset('utf8mb4');

$tmpDb = 'webshop_build_' . substr(md5((string) microtime(true)), 0, 8);
if (!$m->query("CREATE DATABASE `{$tmpDb}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci")) {
	fwrite(STDERR, 'Create DB error: ' . $m->error . PHP_EOL);
	exit(1);
}
$m->select_db($tmpDb);

function run_sql_file($mysqli, $path)
{
	$sql = file_get_contents($path);
	$sql = preg_replace('/^-- FULL SCHEMA:.*?\n--\n/m', '', $sql);
	$sql = preg_replace(
		'/\r?\n-- --------------------------------------------------------\r?\n-- Module kho v2: mã SP, biến thể.*?(?=\r?\n--\r?\n-- Ràng buộc đối với|\r?\nCOMMIT;)/s',
		"\n",
		$sql
	);

	if (!$mysqli->multi_query($sql)) {
		throw new RuntimeException('Import error: ' . $mysqli->error);
	}
	do {
		if ($result = $mysqli->store_result()) {
			$result->free();
		}
	} while ($mysqli->more_results() && $mysqli->next_result());
}

function esc($mysqli, $value)
{
	return $mysqli->real_escape_string((string) $value);
}

function export_backfill_sql($mysqli)
{
	$lines = array();
	$lines[] = '';
	$lines[] = '-- --------------------------------------------------------';
	$lines[] = '-- Module kho v2: mã SP, biến thể (gộp từ run_migrations.php)';
	$lines[] = '-- --------------------------------------------------------';
	$lines[] = '';

	$r = $mysqli->query('SELECT id FROM product ORDER BY id ASC');
	if ($r) {
		while ($row = $r->fetch_assoc()) {
			$id = (int) $row['id'];
			$code = 'SP' . str_pad((string) $id, 5, '0', STR_PAD_LEFT);
			$lines[] = "UPDATE `product` SET `code` = '{$code}' WHERE `id` = {$id};";
		}
	}

	$vr = $mysqli->query('SELECT * FROM product_variants ORDER BY id ASC');
	if ($vr) {
		while ($v = $vr->fetch_assoc()) {
			$lines[] = 'INSERT INTO `product_variants` (`id`, `product_id`, `sku`, `color`, `size`, `price`, `cost_price`, `stock`, `min_stock`, `image`, `status`, `created`, `updated`) VALUES ('
				. (int) $v['id'] . ', '
				. (int) $v['product_id'] . ", '"
				. esc($mysqli, $v['sku']) . "', '"
				. esc($mysqli, $v['color']) . "', '"
				. esc($mysqli, $v['size']) . "', "
				. (float) $v['price'] . ', '
				. (float) $v['cost_price'] . ', '
				. (int) $v['stock'] . ', '
				. (int) $v['min_stock'] . ", '"
				. esc($mysqli, $v['image']) . "', "
				. (int) $v['status'] . ', '
				. (int) $v['created'] . ', '
				. (int) $v['updated'] . ');';
		}
	}

	$pr = $mysqli->query('SELECT id FROM product ORDER BY id ASC');
	if ($pr) {
		while ($row = $pr->fetch_assoc()) {
			$id = (int) $row['id'];
			$lines[] = "UPDATE `product` SET `quantity` = (SELECT COALESCE(SUM(`stock`), 0) FROM `product_variants` WHERE `product_id` = {$id}) WHERE `id` = {$id};";
		}
	}

	$sr = $mysqli->query('SELECT * FROM suppliers ORDER BY id ASC');
	if ($sr && $sr->num_rows > 0) {
		while ($s = $sr->fetch_assoc()) {
			$lines[] = 'INSERT INTO `suppliers` (`id`, `code`, `name`, `contact_name`, `phone`, `email`, `address`, `note`, `status`, `created`, `updated`) VALUES ('
				. (int) $s['id'] . ", '"
				. esc($mysqli, $s['code']) . "', '"
				. esc($mysqli, $s['name']) . "', '"
				. esc($mysqli, $s['contact_name']) . "', '"
				. esc($mysqli, $s['phone']) . "', '"
				. esc($mysqli, $s['email']) . "', '"
				. esc($mysqli, $s['address']) . "', '"
				. esc($mysqli, $s['note']) . "', "
				. (int) $s['status'] . ', '
				. (int) $s['created'] . ', '
				. (int) $s['updated'] . ');';
		}
	}

	$lines[] = '';
	$maxId = 0;
	$vr = $mysqli->query('SELECT MAX(id) AS m FROM product_variants');
	if ($vr && ($row = $vr->fetch_assoc())) {
		$maxId = (int) $row['m'];
	}
	if ($maxId > 0) {
		$lines[] = 'ALTER TABLE `product_variants` AUTO_INCREMENT = ' . ($maxId + 1) . ';';
		$lines[] = '';
	}

	return implode("\n", $lines);
}

function patch_sql_schema($sql)
{
	$sql = preg_replace('/^--\n-- FULL SCHEMA:.*?\n--\n/m', '', $sql);
	$sql = preg_replace(
		'/^(-- phpMyAdmin SQL Dump.*?\n)/',
		"$1--\n-- FULL SCHEMA: đã gộp module kho v2 (suppliers, stock_*, product_variants).\n-- Import file này một lần trên hosting; run_migrations.php chỉ cần khi nâng cấp DB cũ.\n--\n",
		$sql,
		1
	);

	$sql = preg_replace(
		'/(`discount_percent` int NOT NULL DEFAULT \'0\',)\s*\n(\s*`image_link`)/',
		"$1\n  `code` varchar(64) COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT '',\n  `status` tinyint NOT NULL DEFAULT '1',\n$2",
		$sql,
		1,
		$productPatched
	);

	if (!preg_match('/CREATE TABLE `order`[\s\S]*?`variant_id`/m', $sql)) {
		$sql = preg_replace(
			'/(`qty` int NOT NULL DEFAULT \'0\',)\s*\n(\s*`amount`)/',
			"$1\n  `variant_id` int NOT NULL DEFAULT '0',\n  `cost_price` decimal(15,2) NOT NULL DEFAULT '0.00',\n$2",
			$sql,
			1,
			$orderPatched
		);
	}

	$stockTables = <<<'SQL'

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `suppliers`
--

CREATE TABLE `suppliers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(32) COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT '',
  `name` varchar(255) COLLATE utf8mb3_unicode_ci NOT NULL,
  `contact_name` varchar(128) COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT '',
  `phone` varchar(32) COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT '',
  `email` varchar(128) COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT '',
  `address` varchar(512) COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT '',
  `note` text COLLATE utf8mb3_unicode_ci,
  `status` tinyint NOT NULL DEFAULT '1',
  `created` int NOT NULL DEFAULT '0',
  `updated` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_suppliers_code` (`code`),
  KEY `idx_suppliers_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `stock_receipts`
--

CREATE TABLE `stock_receipts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `receipt_code` varchar(20) COLLATE utf8mb3_unicode_ci NOT NULL,
  `supplier_id` int NOT NULL DEFAULT '0',
  `status` enum('draft','confirmed','cancelled') COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT 'draft',
  `receipt_date` date DEFAULT NULL,
  `note` text COLLATE utf8mb3_unicode_ci,
  `total_qty` int NOT NULL DEFAULT '0',
  `total_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `created_by` int NOT NULL DEFAULT '0',
  `confirmed_by` int NOT NULL DEFAULT '0',
  `confirmed_at` int NOT NULL DEFAULT '0',
  `cancelled_at` int NOT NULL DEFAULT '0',
  `created` int NOT NULL DEFAULT '0',
  `updated` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_stock_receipts_code` (`receipt_code`),
  KEY `idx_stock_receipts_status` (`status`),
  KEY `idx_stock_receipts_supplier` (`supplier_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `stock_receipt_items`
--

CREATE TABLE `stock_receipt_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `receipt_id` int NOT NULL,
  `product_id` int NOT NULL,
  `variant_id` int NOT NULL DEFAULT '0',
  `size` varchar(64) COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT '',
  `color` varchar(64) COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT '',
  `qty` int NOT NULL DEFAULT '0',
  `unit_cost` decimal(15,2) NOT NULL DEFAULT '0.00',
  `subtotal` decimal(15,2) NOT NULL DEFAULT '0.00',
  `note` varchar(255) COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `idx_sri_receipt` (`receipt_id`),
  KEY `idx_sri_product` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `product_inventory`
--

CREATE TABLE `product_inventory` (
  `id` int NOT NULL AUTO_INCREMENT,
  `product_id` int NOT NULL,
  `size` varchar(64) COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT '',
  `color` varchar(64) COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT '',
  `quantity` int NOT NULL DEFAULT '0',
  `updated` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_product_inventory_variant` (`product_id`,`size`,`color`),
  KEY `idx_pi_product` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `stock_movements`
--

CREATE TABLE `stock_movements` (
  `id` int NOT NULL AUTO_INCREMENT,
  `product_id` int NOT NULL,
  `variant_id` int NOT NULL DEFAULT '0',
  `size` varchar(64) COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT '',
  `color` varchar(64) COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT '',
  `movement_type` varchar(16) COLLATE utf8mb3_unicode_ci NOT NULL,
  `qty_change` int NOT NULL,
  `before_qty` int NOT NULL DEFAULT '0',
  `after_qty` int NOT NULL DEFAULT '0',
  `reference_type` varchar(32) COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT '',
  `reference_id` int NOT NULL DEFAULT '0',
  `note` varchar(255) COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT '',
  `created_by` int NOT NULL DEFAULT '0',
  `created` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_sm_product` (`product_id`),
  KEY `idx_sm_reference` (`reference_type`,`reference_id`),
  KEY `idx_sm_created` (`created`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `product_variants`
--

CREATE TABLE `product_variants` (
  `id` int NOT NULL AUTO_INCREMENT,
  `product_id` int NOT NULL,
  `sku` varchar(64) COLLATE utf8mb3_unicode_ci NOT NULL,
  `color` varchar(64) COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT '',
  `size` varchar(64) COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT '',
  `price` decimal(15,2) NOT NULL DEFAULT '0.00',
  `cost_price` decimal(15,2) NOT NULL DEFAULT '0.00',
  `stock` int NOT NULL DEFAULT '0',
  `min_stock` int NOT NULL DEFAULT '5',
  `image` varchar(255) COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT '',
  `status` tinyint NOT NULL DEFAULT '1',
  `created` int NOT NULL DEFAULT '0',
  `updated` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_product_variants_sku` (`sku`),
  UNIQUE KEY `uk_product_variants_combo` (`product_id`,`color`,`size`),
  KEY `idx_pv_product` (`product_id`),
  KEY `idx_pv_stock` (`stock`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

SQL;

	if (strpos($sql, 'CREATE TABLE `suppliers`') === false) {
		$sql = str_replace(
			"-- --------------------------------------------------------\n\n--\n-- Cấu trúc bảng cho bảng `user_point_log`",
			$stockTables . "\n-- --------------------------------------------------------\n\n--\n-- Cấu trúc bảng cho bảng `user_point_log`",
			$sql
		);
	}

	$sql = preg_replace(
		'/\r?\n-- --------------------------------------------------------\r?\n-- Module kho v2: mã SP, biến thể.*?(?=\r?\n--\r?\n-- Ràng buộc đối với|\r?\nCOMMIT;)/s',
		"\n",
		$sql
	);

	return $sql;
}

function run_migrations_backfill($mysqli)
{
	function table_exists_local($mysqli, $table)
	{
		$table = $mysqli->real_escape_string($table);
		$r = $mysqli->query("SHOW TABLES LIKE '{$table}'");
		return $r && $r->num_rows > 0;
	}

	function column_exists_local($mysqli, $table, $column)
	{
		$table = $mysqli->real_escape_string($table);
		$column = $mysqli->real_escape_string($column);
		$r = $mysqli->query("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
		return $r && $r->num_rows > 0;
	}

	$alterSpecs = array(
		'product' => array(
			'code' => "varchar(64) NOT NULL DEFAULT ''",
			'status' => "tinyint NOT NULL DEFAULT '1'",
		),
		'stock_receipts' => array(
			'receipt_date' => "date DEFAULT NULL",
			'total_amount' => "decimal(15,2) NOT NULL DEFAULT '0.00'",
		),
		'stock_receipt_items' => array(
			'variant_id' => "int NOT NULL DEFAULT '0'",
			'subtotal' => "decimal(15,2) NOT NULL DEFAULT '0.00'",
		),
		'stock_movements' => array(
			'variant_id' => "int NOT NULL DEFAULT '0'",
		),
		'order' => array(
			'variant_id' => "int NOT NULL DEFAULT '0'",
			'cost_price' => "decimal(15,2) NOT NULL DEFAULT '0.00'",
		),
	);

	foreach ($alterSpecs as $table => $cols) {
		if (!table_exists_local($mysqli, $table)) {
			continue;
		}
		foreach ($cols as $col => $def) {
			if (!column_exists_local($mysqli, $table, $col)) {
				$mysqli->query("ALTER TABLE `{$table}` ADD COLUMN `{$col}` {$def}");
			}
		}
	}

	if (table_exists_local($mysqli, 'suppliers')) {
		$r = $mysqli->query('SELECT COUNT(*) AS c FROM `suppliers`');
		$row = $r ? $r->fetch_assoc() : null;
		if ($row && (int) $row['c'] === 0) {
			$now = time();
			$mysqli->query("INSERT INTO `suppliers` (`code`,`name`,`contact_name`,`phone`,`email`,`address`,`note`,`status`,`created`,`updated`) VALUES
				('NCC001','Nhà cung cấp mẫu qD Design','Nguyễn Văn A','0901234567','ncc@example.com','TP.HCM','Dữ liệu demo nhập kho',1,{$now},{$now})");
		}
	}

	if (!table_exists_local($mysqli, 'product') || !table_exists_local($mysqli, 'product_variants')) {
		return;
	}

	$slug = function ($text) {
		$text = trim((string) $text);
		if ($text === '') {
			return 'DF';
		}
		$text = preg_replace('/[^A-Za-z0-9]+/', '-', $text);
		$text = strtoupper(trim($text, '-'));
		return $text !== '' ? substr($text, 0, 12) : 'DF';
	};

	$r = $mysqli->query('SELECT id, name, color, size, price, quantity FROM product ORDER BY id ASC');
	if (!$r) {
		return;
	}

	$now = time();
	while ($p = $r->fetch_assoc()) {
		$pid = (int) $p['id'];
		$code = 'SP' . str_pad((string) $pid, 5, '0', STR_PAD_LEFT);
		$mysqli->query("UPDATE product SET code = '" . esc($mysqli, $code) . "' WHERE id = {$pid} AND (code IS NULL OR code = '')");

		$colors = array('');
		if (!empty($p['color'])) {
			$colors = array_values(array_filter(array_map('trim', explode(',', $p['color'])), function ($v) {
				return $v !== '';
			}));
		}
		if (empty($colors)) {
			$colors = array('');
		}

		$sizes = array('');
		if (!empty($p['size'])) {
			$sizes = array_values(array_filter(array_map('trim', explode(',', $p['size'])), function ($v) {
				return $v !== '';
			}));
		}
		if (empty($sizes)) {
			$sizes = array('');
		}

		foreach ($colors as $color) {
			foreach ($sizes as $size) {
				$cEsc = esc($mysqli, $color);
				$sEsc = esc($mysqli, $size);
				$chk = $mysqli->query("SELECT id FROM product_variants WHERE product_id = {$pid} AND color = '{$cEsc}' AND size = '{$sEsc}' LIMIT 1");
				if ($chk && $chk->num_rows > 0) {
					continue;
				}

				$sku = 'SP' . str_pad((string) $pid, 5, '0', STR_PAD_LEFT) . '-' . $slug($color) . '-' . $slug($size);
				$n = 1;
				$baseSku = $sku;
				while (true) {
					$skuEsc = esc($mysqli, $sku);
					$dupe = $mysqli->query("SELECT id FROM product_variants WHERE sku = '{$skuEsc}' LIMIT 1");
					if (!$dupe || $dupe->num_rows === 0) {
						break;
					}
					$n++;
					$sku = $baseSku . '-' . $n;
				}

				$stock = 0;
				if (table_exists_local($mysqli, 'product_inventory')) {
					$inv = $mysqli->query("SELECT quantity FROM product_inventory WHERE product_id = {$pid} AND color = '{$cEsc}' AND size = '{$sEsc}' LIMIT 1");
					if ($inv && ($invRow = $inv->fetch_assoc())) {
						$stock = (int) $invRow['quantity'];
					}
				}
				if ($stock === 0 && count($colors) * count($sizes) === 1) {
					$stock = (int) $p['quantity'];
				}

				$price = (float) $p['price'];
				$skuEsc = esc($mysqli, $sku);
				$mysqli->query("INSERT INTO product_variants (product_id, sku, color, size, price, cost_price, stock, min_stock, image, status, created, updated)
					VALUES ({$pid}, '{$skuEsc}', '{$cEsc}', '{$sEsc}', {$price}, 0, {$stock}, 5, '', 1, {$now}, {$now})");
			}
		}

		$sum = $mysqli->query("SELECT COALESCE(SUM(stock),0) AS s FROM product_variants WHERE product_id = {$pid}");
		if ($sum && ($sumRow = $sum->fetch_assoc())) {
			$mysqli->query('UPDATE product SET quantity = ' . (int) $sumRow['s'] . " WHERE id = {$pid}");
		}
	}
}

try {
	$baseSql = patch_sql_schema(file_get_contents($sqlFile));
	file_put_contents($sqlFile, $baseSql);

	run_sql_file($m, $sqlFile);
	run_migrations_backfill($m);

	$backfill = export_backfill_sql($m);
	$finalSql = file_get_contents($sqlFile);
	$finalSql = str_replace(
		"\n--\n-- Ràng buộc đối với các bảng kết xuất",
		"\n" . $backfill . "\n--\n-- Ràng buộc đối với các bảng kết xuất",
		$finalSql
	);

	$variantCount = substr_count($backfill, 'INSERT INTO `product_variants`');
	$productCount = substr_count($backfill, 'UPDATE `product` SET `code`');

	file_put_contents($sqlFile, $finalSql);
	echo "Updated {$sqlFile}\n";
	echo "Products with code: {$productCount}\n";
	echo "Variants generated: {$variantCount}\n";
} catch (Throwable $e) {
	fwrite(STDERR, $e->getMessage() . PHP_EOL);
	exit(1);
} finally {
	$m->query("DROP DATABASE IF EXISTS `{$tmpDb}`");
	$m->close();
}
