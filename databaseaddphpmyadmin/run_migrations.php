<?php
/**
 * Cập nhật schema DB sau khi import webshop.sql — chạy lại an toàn.
 *
 *   php databaseaddphpmyadmin/run_migrations.php
 *
 * Lưu ý: webshop.sql (bản FULL) đã gộp schema + biến thể — import một lần trên hosting.
 * Script này chỉ cần khi nâng cấp DB cũ hoặc sau khi import webshop.sql.
 * Bắt buộc chạy sau mỗi lần import dump: php databaseaddphpmyadmin/run_migrations.php
 */
define('BASEPATH', true);
define('ENVIRONMENT', 'development');

require __DIR__ . '/../application/config/database.php';

$c = $db['default'];
$m = new mysqli($c['hostname'], $c['username'], $c['password'], $c['database']);
if ($m->connect_error) {
	fwrite(STDERR, 'Connect error: ' . $m->connect_error . PHP_EOL);
	exit(1);
}

$m->set_charset('utf8mb4');

function table_exists($mysqli, $table)
{
	$table = $mysqli->real_escape_string($table);
	$r = $mysqli->query("SHOW TABLES LIKE '{$table}'");
	return $r && $r->num_rows > 0;
}

function column_exists($mysqli, $table, $column)
{
	$table = $mysqli->real_escape_string($table);
	$column = $mysqli->real_escape_string($column);
	$r = $mysqli->query("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
	return $r && $r->num_rows > 0;
}

function index_exists($mysqli, $table, $indexName)
{
	$table = $mysqli->real_escape_string($table);
	$indexName = $mysqli->real_escape_string($indexName);
	$r = $mysqli->query("SHOW INDEX FROM `{$table}` WHERE Key_name = '{$indexName}'");
	return $r && $r->num_rows > 0;
}

function fk_exists($mysqli, $table, $constraintName)
{
	$table = $mysqli->real_escape_string($table);
	$constraintName = $mysqli->real_escape_string($constraintName);
	$r = $mysqli->query("
		SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
		WHERE TABLE_SCHEMA = DATABASE()
			AND TABLE_NAME = '{$table}'
			AND CONSTRAINT_TYPE = 'FOREIGN KEY'
			AND CONSTRAINT_NAME = '{$constraintName}'
	");
	return $r && $r->num_rows > 0;
}

function table_engine($mysqli, $table)
{
	$table = $mysqli->real_escape_string($table);
	$r = $mysqli->query("SHOW TABLE STATUS LIKE '{$table}'");
	if (!$r || !($row = $r->fetch_assoc())) {
		return '';
	}
	return isset($row['Engine']) ? strtoupper((string) $row['Engine']) : '';
}

function run_sql_or_skip($mysqli, $sql, $okLabel, $skipLabel = null)
{
	if ($mysqli->query($sql)) {
		echo "OK: {$okLabel}\n";
		return true;
	}
	$msg = $skipLabel ?: $okLabel;
	echo "SKIP: {$msg} — " . $mysqli->error . "\n";
	return false;
}

function count_query($mysqli, $sql)
{
	$r = $mysqli->query($sql);
	if (!$r || !($row = $r->fetch_assoc())) {
		return 0;
	}
	$key = key($row);
	return (int) $row[$key];
}

$schemaSql = <<<'SQL'
CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `token_hash` varchar(64) COLLATE utf8mb3_unicode_ci NOT NULL,
  `expires_at` int NOT NULL,
  `used_at` int NOT NULL DEFAULT '0',
  `created` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_password_resets_token` (`token_hash`),
  KEY `idx_password_resets_user` (`user_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_phone` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `phone_label` varchar(50) COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT '',
  `phone_number` varchar(20) COLLATE utf8mb3_unicode_ci NOT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT '0',
  `created` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_user_phone_user` (`user_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

CREATE TABLE IF NOT EXISTS `product_review` (
  `id` int NOT NULL AUTO_INCREMENT,
  `product_id` int NOT NULL,
  `user_id` int NOT NULL DEFAULT '0',
  `user_name` varchar(100) COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT '',
  `stars` tinyint NOT NULL DEFAULT '5',
  `comment` text COLLATE utf8mb3_unicode_ci,
  `status` tinyint NOT NULL DEFAULT '0' COMMENT '0=pending,1=approved,2=rejected',
  `reviewed_at` int NOT NULL DEFAULT '0',
  `reviewed_by` int NOT NULL DEFAULT '0',
  `session_token` varchar(64) COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT '',
  `created` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_product_review_product` (`product_id`),
  KEY `idx_product_review_user` (`user_id`),
  KEY `idx_product_review_session` (`session_token`),
  KEY `idx_product_review_status` (`status`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

CREATE TABLE IF NOT EXISTS `suppliers` (
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

CREATE TABLE IF NOT EXISTS `stock_receipts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `receipt_code` varchar(20) COLLATE utf8mb3_unicode_ci NOT NULL,
  `supplier_id` int DEFAULT NULL,
  `status` enum('draft','confirmed','cancelled') COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT 'draft',
  `note` text COLLATE utf8mb3_unicode_ci,
  `total_qty` int NOT NULL DEFAULT '0',
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

CREATE TABLE IF NOT EXISTS `stock_receipt_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `receipt_id` int NOT NULL,
  `product_id` int NOT NULL,
  `size` varchar(64) COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT '',
  `color` varchar(64) COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT '',
  `qty` int NOT NULL DEFAULT '0',
  `unit_cost` decimal(15,2) NOT NULL DEFAULT '0.00',
  `note` varchar(255) COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `idx_sri_receipt` (`receipt_id`),
  KEY `idx_sri_product` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

CREATE TABLE IF NOT EXISTS `stock_movements` (
  `id` int NOT NULL AUTO_INCREMENT,
  `product_id` int NOT NULL,
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
SQL;

if (!$m->multi_query($schemaSql)) {
	fwrite(STDERR, 'Schema error: ' . $m->error . PHP_EOL);
	exit(1);
}
do {
	if ($result = $m->store_result()) {
		$result->free();
	}
} while ($m->more_results() && $m->next_result());

foreach (array('password_resets', 'user_phone', 'product_review', 'suppliers', 'stock_receipts', 'stock_receipt_items', 'stock_movements') as $t) {
	echo (table_exists($m, $t) ? 'OK' : 'FAIL') . ": table `{$t}`" . PHP_EOL;
}

// Dữ liệu mẫu NCC (chỉ khi bảng trống)
if (table_exists($m, 'suppliers')) {
	$r = $m->query('SELECT COUNT(*) AS c FROM `suppliers`');
	$row = $r ? $r->fetch_assoc() : null;
	if ($row && (int) $row['c'] === 0) {
		$now = time();
		$m->query("INSERT INTO `suppliers` (`code`,`name`,`contact_name`,`phone`,`email`,`address`,`note`,`status`,`created`,`updated`) VALUES
			('NCC001','Nhà cung cấp mẫu qD Design','Nguyễn Văn A','0901234567','ncc@example.com','TP.HCM','Dữ liệu demo nhập kho',1,{$now},{$now})");
		echo "OK: sample supplier NCC001\n";
	} else {
		echo "SKIP: suppliers already has data\n";
	}
}

if (table_exists($m, 'product')) {
	if (!column_exists($m, 'product', 'discount_type')) {
		if (!$m->query("ALTER TABLE `product` ADD COLUMN `discount_type` varchar(10) NOT NULL DEFAULT 'percent' AFTER `discount`")) {
			fwrite(STDERR, 'ALTER discount_type: ' . $m->error . PHP_EOL);
			exit(1);
		}
		echo "OK: product.discount_type added\n";
	} else {
		echo "SKIP: product.discount_type exists\n";
	}

	if (!column_exists($m, 'product', 'discount_percent')) {
		if (!$m->query("ALTER TABLE `product` ADD COLUMN `discount_percent` int NOT NULL DEFAULT 0 AFTER `discount_type`")) {
			fwrite(STDERR, 'ALTER discount_percent: ' . $m->error . PHP_EOL);
			exit(1);
		}
		echo "OK: product.discount_percent added\n";
	} else {
		echo "SKIP: product.discount_percent exists\n";
	}
} else {
	echo "WARN: table `product` not found — import webshop.sql first\n";
}

// --- Module kho v2: product_variants + variant_id ---
$variantSql = <<<'SQL'
CREATE TABLE IF NOT EXISTS `product_variants` (
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

if (!$m->query($variantSql)) {
	fwrite(STDERR, 'product_variants: ' . $m->error . PHP_EOL);
	exit(1);
}
echo (table_exists($m, 'product_variants') ? 'OK' : 'FAIL') . ": table `product_variants`" . PHP_EOL;

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
		'variant_id' => "int DEFAULT NULL",
	),
	'order' => array(
		'variant_id' => "int NOT NULL DEFAULT '0'",
		'cost_price' => "decimal(15,2) NOT NULL DEFAULT '0.00'",
	),
);

foreach ($alterSpecs as $table => $cols) {
	if (!table_exists($m, $table)) {
		echo "SKIP: alter `{$table}` — table missing\n";
		continue;
	}
	foreach ($cols as $col => $def) {
		if (!column_exists($m, $table, $col)) {
			if (!$m->query("ALTER TABLE `{$table}` ADD COLUMN `{$col}` {$def}")) {
				fwrite(STDERR, "ALTER {$table}.{$col}: " . $m->error . PHP_EOL);
				exit(1);
			}
			echo "OK: {$table}.{$col} added\n";
		} else {
			echo "SKIP: {$table}.{$col} exists\n";
		}
	}
}

// Backfill product.code
if (table_exists($m, 'product') && table_exists($m, 'product_variants')) {
	$r = $m->query("SELECT id, name, color, size, price, quantity FROM product ORDER BY id ASC");
	if ($r) {
		$now = time();
		while ($p = $r->fetch_assoc()) {
			$pid = (int) $p['id'];
			if (column_exists($m, 'product', 'code')) {
				$code = 'SP' . str_pad((string) $pid, 5, '0', STR_PAD_LEFT);
				$m->query("UPDATE product SET code = '{$m->real_escape_string($code)}' WHERE id = {$pid} AND (code IS NULL OR code = '')");
			}

			$colors = array('');
			if (!empty($p['color'])) {
				$colors = array_values(array_filter(array_map('trim', explode(',', $p['color'])), function ($v) { return $v !== ''; }));
			}
			if (empty($colors)) {
				$colors = array('');
			}
			$sizes = array('');
			if (!empty($p['size'])) {
				$sizes = array_values(array_filter(array_map('trim', explode(',', $p['size'])), function ($v) { return $v !== ''; }));
			}
			if (empty($sizes)) {
				$sizes = array('');
			}

			foreach ($colors as $color) {
				foreach ($sizes as $size) {
					$cEsc = $m->real_escape_string($color);
					$sEsc = $m->real_escape_string($size);
					$chk = $m->query("SELECT id FROM product_variants WHERE product_id = {$pid} AND color = '{$cEsc}' AND size = '{$sEsc}' LIMIT 1");
					if ($chk && $chk->num_rows > 0) {
						continue;
					}

					$slug = function ($text) {
						$text = trim((string) $text);
						if ($text === '') return 'DF';
						$text = preg_replace('/[^A-Za-z0-9]+/', '-', $text);
						$text = strtoupper(trim($text, '-'));
						return $text !== '' ? substr($text, 0, 12) : 'DF';
					};
					$sku = 'SP' . str_pad((string) $pid, 5, '0', STR_PAD_LEFT) . '-' . $slug($color) . '-' . $slug($size);
					$n = 1;
					$baseSku = $sku;
					while (true) {
						$skuEsc = $m->real_escape_string($sku);
						$dupe = $m->query("SELECT id FROM product_variants WHERE sku = '{$skuEsc}' LIMIT 1");
						if (!$dupe || $dupe->num_rows === 0) break;
						$n++;
						$sku = $baseSku . '-' . $n;
					}

					$stock = 0;
					$price = (float) $p['price'];
					$skuEsc = $m->real_escape_string($sku);
					$m->query("INSERT INTO product_variants (product_id, sku, color, size, price, cost_price, stock, min_stock, image, status, created, updated)
						VALUES ({$pid}, '{$skuEsc}', '{$cEsc}', '{$sEsc}', {$price}, 0, {$stock}, 5, '', 1, {$now}, {$now})");
				}
			}

			// Sync product.quantity from variants
			$sum = $m->query("SELECT COALESCE(SUM(stock),0) AS s FROM product_variants WHERE product_id = {$pid}");
			if ($sum && ($sumRow = $sum->fetch_assoc())) {
				$m->query('UPDATE product SET quantity = ' . (int) $sumRow['s'] . " WHERE id = {$pid}");
			}
		}
		echo "OK: product_variants backfill from product attributes\n";
	}

	// Map stock_receipt_items.variant_id
	if (table_exists($m, 'stock_receipt_items')) {
		$m->query("UPDATE stock_receipt_items sri
			INNER JOIN product_variants pv ON pv.product_id = sri.product_id AND pv.color = sri.color AND pv.size = sri.size
			SET sri.variant_id = pv.id
			WHERE sri.variant_id = 0");
		$m->query("UPDATE stock_receipt_items SET subtotal = qty * unit_cost WHERE subtotal = 0");
		echo "OK: stock_receipt_items variant_id mapped\n";
	}

	if (table_exists($m, 'stock_movements')) {
		$m->query("UPDATE stock_movements sm
			INNER JOIN product_variants pv ON pv.product_id = sm.product_id AND pv.color = sm.color AND pv.size = sm.size
			SET sm.variant_id = pv.id
			WHERE sm.variant_id = 0");
		echo "OK: stock_movements variant_id mapped\n";
	}
}

if (table_exists($m, 'user') && !column_exists($m, 'user', 'saved_cart')) {
	if (!$m->query("ALTER TABLE `user` ADD COLUMN `saved_cart` LONGTEXT NULL AFTER `created`")) {
		fwrite(STDERR, 'ALTER user.saved_cart: ' . $m->error . PHP_EOL);
		exit(1);
	}
	echo "OK: user.saved_cart column added\n";
} elseif (table_exists($m, 'user')) {
	echo "SKIP: user.saved_cart exists\n";
} else {
	echo "WARN: table `user` not found — import webshop.sql first\n";
}

if (!table_exists($m, 'user_saved_cart')) {
	if (!$m->query("CREATE TABLE `user_saved_cart` (
		`user_id` int NOT NULL,
		`cart_json` longtext COLLATE utf8mb3_unicode_ci NOT NULL,
		`updated` int NOT NULL DEFAULT '0',
		PRIMARY KEY (`user_id`)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci")) {
		fwrite(STDERR, 'CREATE user_saved_cart: ' . $m->error . PHP_EOL);
		exit(1);
	}
	echo "OK: table user_saved_cart created\n";
} else {
	echo "SKIP: user_saved_cart exists\n";
}

echo (table_exists($m, 'user_saved_cart') ? 'OK' : 'FAIL') . ": table `user_saved_cart`" . PHP_EOL;
if (table_exists($m, 'user')) {
	echo (column_exists($m, 'user', 'saved_cart') ? 'OK' : 'FAIL') . ": column `user.saved_cart`" . PHP_EOL;
}

if (table_exists($m, 'order') && column_exists($m, 'order', 'variant_id') && !index_exists($m, 'order', 'idx_order_variant_id')) {
	if (!$m->query('ALTER TABLE `order` ADD KEY `idx_order_variant_id` (`variant_id`)')) {
		fwrite(STDERR, 'ALTER order.idx_order_variant_id: ' . $m->error . PHP_EOL);
		exit(1);
	}
	echo "OK: order.idx_order_variant_id added\n";
} elseif (table_exists($m, 'order')) {
	echo "SKIP: order.idx_order_variant_id exists or variant_id missing\n";
}

// --- DB refactor: indexes, data backfill, FKs (idempotent) ---

if (table_exists($m, 'catalog') && !index_exists($m, 'catalog', 'idx_catalog_parent_id')) {
	run_sql_or_skip($m, 'ALTER TABLE `catalog` ADD KEY `idx_catalog_parent_id` (`parent_id`)', 'catalog.idx_catalog_parent_id added');
} elseif (table_exists($m, 'catalog')) {
	echo "SKIP: catalog.idx_catalog_parent_id exists\n";
}

if (table_exists($m, 'transaction') && column_exists($m, 'transaction', 'created') && !index_exists($m, 'transaction', 'idx_transaction_created')) {
	run_sql_or_skip($m, 'ALTER TABLE `transaction` ADD KEY `idx_transaction_created` (`created`)', 'transaction.idx_transaction_created added');
} elseif (table_exists($m, 'transaction')) {
	echo "SKIP: transaction.idx_transaction_created exists or created column missing\n";
}

if (table_exists($m, 'product_variants') && !index_exists($m, 'product_variants', 'idx_pv_product')) {
	run_sql_or_skip($m, 'ALTER TABLE `product_variants` ADD KEY `idx_pv_product` (`product_id`)', 'product_variants.idx_pv_product added');
} elseif (table_exists($m, 'product_variants')) {
	echo "SKIP: product_variants.idx_pv_product exists\n";
}

// Backfill order.variant_id from product_id + color + size
if (table_exists($m, 'order') && table_exists($m, 'product_variants') && column_exists($m, 'order', 'variant_id')) {
	$m->query("UPDATE `order` o
		INNER JOIN product_variants pv
			ON pv.product_id = o.product_id
			AND pv.color = COALESCE(o.color, '')
			AND pv.size = COALESCE(o.size, '')
		SET o.variant_id = pv.id
		WHERE o.variant_id = 0");
	$remaining = count_query($m, 'SELECT COUNT(*) AS c FROM `order` WHERE variant_id = 0');
	echo "OK: order.variant_id backfill (remaining without variant: {$remaining})\n";
} else {
	echo "SKIP: order.variant_id backfill — tables/columns missing\n";
}

// Migrate legacy user.saved_cart JSON → user_saved_cart
if (table_exists($m, 'user') && table_exists($m, 'user_saved_cart') && column_exists($m, 'user', 'saved_cart')) {
	$r = $m->query("SELECT u.id, u.saved_cart
		FROM `user` u
		LEFT JOIN user_saved_cart usc ON usc.user_id = u.id
		WHERE u.saved_cart IS NOT NULL
			AND TRIM(u.saved_cart) != ''
			AND (usc.user_id IS NULL OR TRIM(usc.cart_json) = '')");
	$migrated = 0;
	if ($r) {
		$now = time();
		while ($row = $r->fetch_assoc()) {
			$uid = (int) $row['id'];
			$json = $row['saved_cart'];
			$esc = $m->real_escape_string($json);
			if ($m->query("REPLACE INTO user_saved_cart (user_id, cart_json, updated) VALUES ({$uid}, '{$esc}', {$now})")) {
				$migrated++;
			}
		}
	}
	echo "OK: migrated {$migrated} user.saved_cart row(s) to user_saved_cart\n";
} else {
	echo "SKIP: user.saved_cart migration — tables/columns missing\n";
}

// Sync product.quantity cache from SUM(product_variants.stock)
if (table_exists($m, 'product') && table_exists($m, 'product_variants')) {
	$m->query("UPDATE product p
		SET p.quantity = (
			SELECT COALESCE(SUM(pv.stock), 0)
			FROM product_variants pv
			WHERE pv.product_id = p.id
		)");
	$mismatches = count_query($m, "
		SELECT COUNT(*) AS c FROM product p
		LEFT JOIN (
			SELECT product_id, COALESCE(SUM(stock), 0) AS variant_sum
			FROM product_variants GROUP BY product_id
		) vs ON vs.product_id = p.id
		WHERE p.quantity != COALESCE(vs.variant_sum, 0)
	");
	echo "OK: product.quantity synced from product_variants (mismatches: {$mismatches})\n";
} else {
	echo "SKIP: product.quantity sync — tables missing\n";
}

// Foreign keys (InnoDB only, after orphan cleanup)
if (table_exists($m, 'product_variants') && table_engine($m, 'product_variants') === 'INNODB') {
	$orphans = count_query($m, 'SELECT COUNT(*) AS c FROM product_variants pv LEFT JOIN product p ON p.id = pv.product_id WHERE p.id IS NULL');
	if ($orphans > 0) {
		$m->query('DELETE pv FROM product_variants pv LEFT JOIN product p ON p.id = pv.product_id WHERE p.id IS NULL');
		echo "OK: removed {$orphans} orphan product_variants row(s)\n";
	}
	if (!fk_exists($m, 'product_variants', 'fk_pv_product')) {
		run_sql_or_skip($m,
			'ALTER TABLE `product_variants` ADD CONSTRAINT `fk_pv_product` FOREIGN KEY (`product_id`) REFERENCES `product` (`id`) ON DELETE CASCADE ON UPDATE CASCADE',
			'FK product_variants.product_id → product.id',
			'FK product_variants.product_id'
		);
	} else {
		echo "SKIP: fk_pv_product exists\n";
	}
} else {
	echo "SKIP: FK product_variants — table missing or not InnoDB\n";
}

if (table_exists($m, 'user_saved_cart') && table_engine($m, 'user_saved_cart') === 'INNODB') {
	$orphans = count_query($m, 'SELECT COUNT(*) AS c FROM user_saved_cart usc LEFT JOIN `user` u ON u.id = usc.user_id WHERE u.id IS NULL');
	if ($orphans > 0) {
		$m->query('DELETE usc FROM user_saved_cart usc LEFT JOIN `user` u ON u.id = usc.user_id WHERE u.id IS NULL');
		echo "OK: removed {$orphans} orphan user_saved_cart row(s)\n";
	}
	if (!fk_exists($m, 'user_saved_cart', 'fk_user_saved_cart_user')) {
		run_sql_or_skip($m,
			'ALTER TABLE `user_saved_cart` ADD CONSTRAINT `fk_user_saved_cart_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE',
			'FK user_saved_cart.user_id → user.id',
			'FK user_saved_cart.user_id'
		);
	} else {
		echo "SKIP: fk_user_saved_cart_user exists\n";
	}
} else {
	echo "SKIP: FK user_saved_cart — table missing or not InnoDB\n";
}

if (table_exists($m, 'stock_receipt_items') && table_engine($m, 'stock_receipt_items') === 'INNODB') {
	$orphans = count_query($m, 'SELECT COUNT(*) AS c FROM stock_receipt_items sri LEFT JOIN stock_receipts sr ON sr.id = sri.receipt_id WHERE sr.id IS NULL');
	if ($orphans > 0) {
		$m->query('DELETE sri FROM stock_receipt_items sri LEFT JOIN stock_receipts sr ON sr.id = sri.receipt_id WHERE sr.id IS NULL');
		echo "OK: removed {$orphans} orphan stock_receipt_items row(s)\n";
	}
	if (!fk_exists($m, 'stock_receipt_items', 'fk_sri_receipt')) {
		run_sql_or_skip($m,
			'ALTER TABLE `stock_receipt_items` ADD CONSTRAINT `fk_sri_receipt` FOREIGN KEY (`receipt_id`) REFERENCES `stock_receipts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE',
			'FK stock_receipt_items.receipt_id → stock_receipts.id',
			'FK stock_receipt_items.receipt_id'
		);
	} else {
		echo "SKIP: fk_sri_receipt exists\n";
	}
} else {
	echo "SKIP: FK stock_receipt_items — table missing or not InnoDB\n";
}

if (table_exists($m, 'stock_receipts') && table_engine($m, 'stock_receipts') === 'INNODB') {
	if (column_exists($m, 'stock_receipts', 'supplier_id')) {
		$badSuppliers = count_query($m, 'SELECT COUNT(*) AS c FROM stock_receipts sr LEFT JOIN suppliers s ON s.id = sr.supplier_id WHERE sr.supplier_id > 0 AND s.id IS NULL');
		if ($badSuppliers > 0) {
			$m->query('UPDATE stock_receipts sr LEFT JOIN suppliers s ON s.id = sr.supplier_id SET sr.supplier_id = 0 WHERE sr.supplier_id > 0 AND s.id IS NULL');
			echo "OK: zeroed {$badSuppliers} stock_receipts row(s) with invalid supplier_id\n";
		}
		// Allow NULL for optional supplier FK
		if (!$m->query('ALTER TABLE `stock_receipts` MODIFY `supplier_id` int DEFAULT NULL')) {
			echo "SKIP: stock_receipts.supplier_id nullable — " . $m->error . "\n";
		} else {
			$m->query('UPDATE stock_receipts SET supplier_id = NULL WHERE supplier_id = 0');
		}
	}
	if (!fk_exists($m, 'stock_receipts', 'fk_sr_supplier')) {
		run_sql_or_skip($m,
			'ALTER TABLE `stock_receipts` ADD CONSTRAINT `fk_sr_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE',
			'FK stock_receipts.supplier_id → suppliers.id',
			'FK stock_receipts.supplier_id'
		);
	} else {
		echo "SKIP: fk_sr_supplier exists\n";
	}
} else {
	echo "SKIP: FK stock_receipts.supplier_id — table missing or not InnoDB\n";
}

if (table_exists($m, 'stock_movements') && table_engine($m, 'stock_movements') === 'INNODB' && column_exists($m, 'stock_movements', 'variant_id')) {
	$badVariants = count_query($m, 'SELECT COUNT(*) AS c FROM stock_movements sm LEFT JOIN product_variants pv ON pv.id = sm.variant_id WHERE sm.variant_id > 0 AND pv.id IS NULL');
	if ($badVariants > 0) {
		$m->query('UPDATE stock_movements sm LEFT JOIN product_variants pv ON pv.id = sm.variant_id SET sm.variant_id = 0 WHERE sm.variant_id > 0 AND pv.id IS NULL');
		echo "OK: nulled variant_id on {$badVariants} stock_movements row(s)\n";
	}
	if (!$m->query('ALTER TABLE `stock_movements` MODIFY `variant_id` int DEFAULT NULL')) {
		echo "SKIP: stock_movements.variant_id nullable — " . $m->error . "\n";
	} else {
		$m->query('UPDATE stock_movements SET variant_id = NULL WHERE variant_id = 0');
	}
	if (!fk_exists($m, 'stock_movements', 'fk_sm_variant')) {
		run_sql_or_skip($m,
			'ALTER TABLE `stock_movements` ADD CONSTRAINT `fk_sm_variant` FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`id`) ON DELETE SET NULL ON UPDATE CASCADE',
			'FK stock_movements.variant_id → product_variants.id',
			'FK stock_movements.variant_id'
		);
	} else {
		echo "SKIP: fk_sm_variant exists\n";
	}
} else {
	echo "SKIP: FK stock_movements.variant_id — table/column missing or not InnoDB\n";
}

// --- Phase 3: Unified CMS, drop legacy tables, InnoDB + FK hardening ---

if (!table_exists($m, 'content_pages')) {
	run_sql_or_skip($m, "CREATE TABLE `content_pages` (
		`id` int NOT NULL AUTO_INCREMENT,
		`type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
		`legacy_id` int NOT NULL DEFAULT '0',
		`title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
		`slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
		`intro` text COLLATE utf8mb4_unicode_ci,
		`content` longtext COLLATE utf8mb4_unicode_ci,
		`image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
		`created_at` datetime DEFAULT NULL,
		PRIMARY KEY (`id`),
		UNIQUE KEY `uk_content_type_legacy` (`type`,`legacy_id`),
		KEY `idx_content_type` (`type`),
		KEY `idx_content_type_slug` (`type`,`slug`)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", 'table content_pages created');
} else {
	echo "SKIP: content_pages exists\n";
}

if (table_exists($m, 'content_pages')) {
	$cmsMigrations = array(
		array('news', "INSERT IGNORE INTO content_pages (type, legacy_id, title, slug, intro, content, image, created_at)
			SELECT 'news', id, title, CONCAT('tin-tuc-', id), intro, content, image_link, created FROM `news`"),
		array('hop_tac', "INSERT IGNORE INTO content_pages (type, legacy_id, title, slug, intro, content, image, created_at)
			SELECT 'hop_tac', id, title, slug, intro, content, image, created_at FROM hop_tac"),
		array('tuyendung', "INSERT IGNORE INTO content_pages (type, legacy_id, title, slug, intro, content, image, created_at)
			SELECT 'tuyendung', id, title, slug, intro, content, image, created_at FROM tuyendung"),
		array('van_chuyen', "INSERT IGNORE INTO content_pages (type, legacy_id, title, slug, intro, content, image, created_at)
			SELECT 'van_chuyen', id, title, slug, intro, content, image, created_at FROM van_chuyen"),
		array('pages', "INSERT IGNORE INTO content_pages (type, legacy_id, title, slug, intro, content, image, created_at)
			SELECT 'page', id, title, slug, '', content, NULL, created_at FROM pages"),
	);
	foreach ($cmsMigrations as $cms) {
		list($src, $sql) = $cms;
		if (table_exists($m, $src)) {
			run_sql_or_skip($m, $sql, "content_pages migrated from {$src}");
		}
	}
	$m->query("UPDATE content_pages SET legacy_id = id WHERE legacy_id = 0");
}

$legacyCms = array('news', 'hop_tac', 'tuyendung', 'van_chuyen', 'pages');
foreach ($legacyCms as $tbl) {
	if (table_exists($m, 'content_pages') && table_exists($m, $tbl)) {
		run_sql_or_skip($m, "DROP TABLE `{$tbl}`", "dropped legacy CMS table {$tbl}");
	}
}

if (table_exists($m, 'product_colors')) {
	if (fk_exists($m, 'product_colors', 'fk_product_colors_product')) {
		run_sql_or_skip($m, 'ALTER TABLE `product_colors` DROP FOREIGN KEY `fk_product_colors_product`', 'dropped FK fk_product_colors_product');
	}
	run_sql_or_skip($m, 'DROP TABLE IF EXISTS `product_colors`', 'dropped product_colors');
}

if (table_exists($m, 'product_inventory')) {
	run_sql_or_skip($m, 'DROP TABLE IF EXISTS `product_inventory`', 'dropped product_inventory');
}

if (table_exists($m, 'user') && column_exists($m, 'user', 'saved_cart') && table_exists($m, 'user_saved_cart')) {
	run_sql_or_skip($m, 'ALTER TABLE `user` DROP COLUMN `saved_cart`', 'dropped user.saved_cart column');
}

$innodbTargets = array(
	'password_resets', 'user_phone', 'product_review', 'voucher', 'voucher_use',
	'user_point_log', 'ai_conversation', 'ai_faq', 'ai_message',
);
foreach ($innodbTargets as $tbl) {
	if (table_exists($m, $tbl) && table_engine($m, $tbl) === 'MYISAM') {
		run_sql_or_skip($m, "ALTER TABLE `{$tbl}` ENGINE=InnoDB", "{$tbl} converted to InnoDB");
	}
}

if (table_exists($m, 'password_resets') && table_engine($m, 'password_resets') === 'INNODB') {
	$orphans = count_query($m, 'SELECT COUNT(*) AS c FROM password_resets pr LEFT JOIN `user` u ON u.id = pr.user_id WHERE u.id IS NULL');
	if ($orphans > 0) {
		$m->query('DELETE pr FROM password_resets pr LEFT JOIN `user` u ON u.id = pr.user_id WHERE u.id IS NULL');
		echo "OK: removed {$orphans} orphan password_resets row(s)\n";
	}
	if (!fk_exists($m, 'password_resets', 'fk_password_resets_user')) {
		run_sql_or_skip($m,
			'ALTER TABLE `password_resets` ADD CONSTRAINT `fk_password_resets_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE',
			'FK password_resets.user_id → user.id');
	}
}

if (table_exists($m, 'user_phone') && table_engine($m, 'user_phone') === 'INNODB') {
	$orphans = count_query($m, 'SELECT COUNT(*) AS c FROM user_phone up LEFT JOIN `user` u ON u.id = up.user_id WHERE u.id IS NULL');
	if ($orphans > 0) {
		$m->query('DELETE up FROM user_phone up LEFT JOIN `user` u ON u.id = up.user_id WHERE u.id IS NULL');
		echo "OK: removed {$orphans} orphan user_phone row(s)\n";
	}
	if (!fk_exists($m, 'user_phone', 'fk_user_phone_user')) {
		run_sql_or_skip($m,
			'ALTER TABLE `user_phone` ADD CONSTRAINT `fk_user_phone_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE',
			'FK user_phone.user_id → user.id');
	}
}

if (table_exists($m, 'product_review')) {
	if (!column_exists($m, 'product_review', 'comment')) {
		run_sql_or_skip($m,
			'ALTER TABLE `product_review` ADD COLUMN `comment` text COLLATE utf8mb3_unicode_ci NULL AFTER `stars`',
			'product_review.comment');
	}
	if (!column_exists($m, 'product_review', 'status')) {
		run_sql_or_skip($m,
			"ALTER TABLE `product_review` ADD COLUMN `status` tinyint NOT NULL DEFAULT '0' COMMENT '0=pending,1=approved,2=rejected' AFTER `comment`",
			'product_review.status');
		run_sql_or_skip($m,
			"UPDATE `product_review` SET `status` = 1 WHERE `status` = 0",
			'product_review.status backfill approved');
	}
	if (!column_exists($m, 'product_review', 'reviewed_at')) {
		run_sql_or_skip($m,
			"ALTER TABLE `product_review` ADD COLUMN `reviewed_at` int NOT NULL DEFAULT '0' AFTER `status`",
			'product_review.reviewed_at');
	}
	if (!column_exists($m, 'product_review', 'reviewed_by')) {
		run_sql_or_skip($m,
			"ALTER TABLE `product_review` ADD COLUMN `reviewed_by` int NOT NULL DEFAULT '0' AFTER `reviewed_at`",
			'product_review.reviewed_by');
	}
	if (!index_exists($m, 'product_review', 'idx_product_review_status')) {
		run_sql_or_skip($m,
			'ALTER TABLE `product_review` ADD KEY `idx_product_review_status` (`status`)',
			'product_review idx status');
	}
}

if (table_exists($m, 'product_review') && table_engine($m, 'product_review') === 'INNODB') {
	$orphans = count_query($m, 'SELECT COUNT(*) AS c FROM product_review pr LEFT JOIN product p ON p.id = pr.product_id WHERE p.id IS NULL');
	if ($orphans > 0) {
		$m->query('DELETE pr FROM product_review pr LEFT JOIN product p ON p.id = pr.product_id WHERE p.id IS NULL');
		echo "OK: removed {$orphans} orphan product_review row(s)\n";
	}
	if (!fk_exists($m, 'product_review', 'fk_product_review_product')) {
		run_sql_or_skip($m,
			'ALTER TABLE `product_review` ADD CONSTRAINT `fk_product_review_product` FOREIGN KEY (`product_id`) REFERENCES `product` (`id`) ON DELETE CASCADE ON UPDATE CASCADE',
			'FK product_review.product_id → product.id');
	}
}

if (table_exists($m, 'user_point_log') && table_engine($m, 'user_point_log') === 'INNODB') {
	$orphans = count_query($m, 'SELECT COUNT(*) AS c FROM user_point_log upl LEFT JOIN `user` u ON u.id = upl.user_id WHERE u.id IS NULL');
	if ($orphans > 0) {
		$m->query('DELETE upl FROM user_point_log upl LEFT JOIN `user` u ON u.id = upl.user_id WHERE u.id IS NULL');
		echo "OK: removed {$orphans} orphan user_point_log row(s)\n";
	}
	if (!fk_exists($m, 'user_point_log', 'fk_user_point_log_user')) {
		run_sql_or_skip($m,
			'ALTER TABLE `user_point_log` ADD CONSTRAINT `fk_user_point_log_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE',
			'FK user_point_log.user_id → user.id');
	}
}

if (table_exists($m, 'voucher_use') && table_engine($m, 'voucher_use') === 'INNODB') {
	$orphans = count_query($m, 'SELECT COUNT(*) AS c FROM voucher_use vu LEFT JOIN voucher v ON v.id = vu.voucher_id WHERE v.id IS NULL');
	if ($orphans > 0) {
		$m->query('DELETE vu FROM voucher_use vu LEFT JOIN voucher v ON v.id = vu.voucher_id WHERE v.id IS NULL');
		echo "OK: removed {$orphans} orphan voucher_use row(s)\n";
	}
	if (!fk_exists($m, 'voucher_use', 'fk_voucher_use_voucher')) {
		run_sql_or_skip($m,
			'ALTER TABLE `voucher_use` ADD CONSTRAINT `fk_voucher_use_voucher` FOREIGN KEY (`voucher_id`) REFERENCES `voucher` (`id`) ON DELETE CASCADE ON UPDATE CASCADE',
			'FK voucher_use.voucher_id → voucher.id');
	}
	if (!fk_exists($m, 'voucher_use', 'fk_voucher_use_transaction')) {
		run_sql_or_skip($m,
			'ALTER TABLE `voucher_use` ADD CONSTRAINT `fk_voucher_use_transaction` FOREIGN KEY (`transaction_id`) REFERENCES `transaction` (`id`) ON DELETE CASCADE ON UPDATE CASCADE',
			'FK voucher_use.transaction_id → transaction.id');
	}
}

if (table_exists($m, 'ai_message') && table_engine($m, 'ai_message') === 'INNODB') {
	$orphans = count_query($m, 'SELECT COUNT(*) AS c FROM ai_message am LEFT JOIN ai_conversation ac ON ac.id = am.conversation_id WHERE ac.id IS NULL');
	if ($orphans > 0) {
		$m->query('DELETE am FROM ai_message am LEFT JOIN ai_conversation ac ON ac.id = am.conversation_id WHERE ac.id IS NULL');
		echo "OK: removed {$orphans} orphan ai_message row(s)\n";
	}
	if (!fk_exists($m, 'ai_message', 'fk_ai_message_conversation')) {
		run_sql_or_skip($m,
			'ALTER TABLE `ai_message` ADD CONSTRAINT `fk_ai_message_conversation` FOREIGN KEY (`conversation_id`) REFERENCES `ai_conversation` (`id`) ON DELETE CASCADE ON UPDATE CASCADE',
			'FK ai_message.conversation_id → ai_conversation.id');
	}
}

// --- Thuộc tính sản phẩm động (màu / size) ---
$masterColorSql = <<<'SQL'
CREATE TABLE IF NOT EXISTS `master_colors` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(64) COLLATE utf8mb3_unicode_ci NOT NULL,
  `hex_code` varchar(16) COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT '#cccccc',
  `sort_order` int NOT NULL DEFAULT '0',
  `status` tinyint NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_master_colors_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
SQL;
if (!$m->query($masterColorSql)) {
	fwrite(STDERR, 'master_colors: ' . $m->error . PHP_EOL);
	exit(1);
}
echo (table_exists($m, 'master_colors') ? 'OK' : 'FAIL') . ": table `master_colors`" . PHP_EOL;

$masterSizeSql = <<<'SQL'
CREATE TABLE IF NOT EXISTS `master_sizes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(32) COLLATE utf8mb3_unicode_ci NOT NULL,
  `sort_order` int NOT NULL DEFAULT '0',
  `status` tinyint NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_master_sizes_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
SQL;
if (!$m->query($masterSizeSql)) {
	fwrite(STDERR, 'master_sizes: ' . $m->error . PHP_EOL);
	exit(1);
}
echo (table_exists($m, 'master_sizes') ? 'OK' : 'FAIL') . ": table `master_sizes`" . PHP_EOL;

if (table_exists($m, 'master_colors')) {
	$r = $m->query('SELECT COUNT(*) AS c FROM `master_colors`');
	$row = $r ? $r->fetch_assoc() : null;
	if ($row && (int) $row['c'] === 0) {
		$seedColors = array(
			array('Đen', '#000000', 1),
			array('Trắng', '#ffffff', 2),
			array('Đỏ', '#dd021b', 3),
			array('Vàng', '#fcd535', 4),
			array('Xanh dương', '#2196f3', 5),
			array('Xanh lá', '#4caf50', 6),
			array('Hồng', '#ffb6c1', 7),
			array('Xám', '#808080', 8),
			array('Nâu', '#8b5a2b', 9),
			array('Kem', '#f5f5dc', 10),
			array('Cam', '#ff9800', 11),
			array('Tím', '#9c27b0', 12),
		);
		foreach ($seedColors as $c) {
			$name = $m->real_escape_string($c[0]);
			$hex = $m->real_escape_string($c[1]);
			$sort = (int) $c[2];
			$m->query("INSERT INTO `master_colors` (`name`,`hex_code`,`sort_order`,`status`) VALUES ('{$name}','{$hex}',{$sort},1)");
		}
		echo "OK: seeded master_colors\n";
	} else {
		echo "SKIP: master_colors already has data\n";
	}
}

if (table_exists($m, 'master_sizes')) {
	$r = $m->query('SELECT COUNT(*) AS c FROM `master_sizes`');
	$row = $r ? $r->fetch_assoc() : null;
	if ($row && (int) $row['c'] === 0) {
		$seedSizes = array('S', 'M', 'L', 'XL', 'XXL');
		$sort = 0;
		foreach ($seedSizes as $sz) {
			$sort++;
			$name = $m->real_escape_string($sz);
			$m->query("INSERT INTO `master_sizes` (`name`,`sort_order`,`status`) VALUES ('{$name}',{$sort},1)");
		}
		echo "OK: seeded master_sizes\n";
	} else {
		echo "SKIP: master_sizes already has data\n";
	}
}

echo "Done.\n";
