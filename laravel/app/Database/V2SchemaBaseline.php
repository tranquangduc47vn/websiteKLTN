<?php

namespace App\Database;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cài schema V2 vào database trống từ file SQL không có dữ liệu.
 * Database đã có bảng orders chỉ được kiểm tra, không bị ALTER.
 */
final class V2SchemaBaseline
{
    public const MIGRATION = '2026_10_21_210000_establish_v2_schema_baseline';

    /** @var list<string> */
    public const TABLES = [
        'admins',
        'ai_conversations',
        'ai_faqs',
        'ai_messages',
        'ai_settings',
        'banners',
        'categories',
        'colors',
        'content_pages',
        'order_items',
        'orders',
        'password_resets',
        'payments',
        'point_logs',
        'product_images',
        'product_reviews',
        'product_variants',
        'products',
        'sizes',
        'sliders',
        'stock_movements',
        'stock_receipt_items',
        'stock_receipts',
        'stores',
        'suppliers',
        'user_addresses',
        'user_phones',
        'user_saved_carts',
        'users',
        'voucher_uses',
        'vouchers',
    ];

    public static function path(): string
    {
        return database_path('schema/v2-baseline.sql');
    }

    public static function present(): bool
    {
        return Schema::hasTable('orders')
            && Schema::hasTable('payments')
            && Schema::hasTable('product_variants');
    }

    /**
     * @return list<string>
     */
    public static function missingTables(): array
    {
        $missing = [];
        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table)) {
                $missing[] = $table;
            }
        }

        return $missing;
    }

    public static function install(): void
    {
        if (self::present()) {
            $missing = self::missingTables();
            if ($missing !== []) {
                throw new MigrationBlockedException(
                    'Schema V2 đã có bảng orders nhưng thiếu: ' . implode(', ', $missing) . '. Baseline không tạo đè và không sửa bảng hiện có.'
                );
            }

            return;
        }

        $sql = (string) file_get_contents(self::path());
        self::assertSafeSql($sql);
        foreach (self::statements($sql) as $statement) {
            DB::unprepared($statement);
        }

        $missing = self::missingTables();
        if ($missing !== []) {
            throw new MigrationBlockedException(
                'Baseline SQL chạy xong nhưng còn thiếu bảng: ' . implode(', ', $missing)
            );
        }
    }

    public static function assertSafeSql(string $sql): void
    {
        if (preg_match('/\b(DROP\s+TABLE|DROP\s+DATABASE|INSERT\s+INTO|TRUNCATE\s+TABLE|^USE\s+)/im', $sql) === 1) {
            throw new MigrationBlockedException(
                'File baseline chứa DROP, TRUNCATE, INSERT hoặc USE. Không thực thi.'
            );
        }
    }

    /**
     * @return list<string>
     */
    public static function statements(string $sql): array
    {
        $parts = preg_split("/;\\s*(?:\\r\\n|\\n)/", $sql) ?: [];
        $statements = [];
        foreach ($parts as $part) {
            $statement = trim($part);
            if ($statement !== '') {
                $statements[] = $statement;
            }
        }

        return $statements;
    }
}
