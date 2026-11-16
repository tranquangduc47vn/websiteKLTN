<?php

namespace App\Database;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Chặn lệnh Artisan làm đổi schema khi lịch sử migration không khớp schema V2.
 * Không dùng APP_ENV hay APP_DEBUG để nhận database live.
 */
final class MigrationSafetyGuard
{
    /** @var list<string> */
    public const SCHEMA_COMMANDS = [
        'migrate',
        'migrate:fresh',
        'migrate:install',
        'migrate:refresh',
        'migrate:reset',
        'migrate:rollback',
        'db:wipe',
        'schema:load',
    ];

    /** @var list<string> */
    public const DESTRUCTIVE_COMMANDS = [
        'migrate:fresh',
        'migrate:refresh',
        'migrate:reset',
        'db:wipe',
    ];

    /** @var list<string> */
    public const DANGEROUS_FILES = [
        '0001_01_01_000000_create_users_table.php',
        '0001_01_01_000001_create_cache_table.php',
        '0001_01_01_000002_create_jobs_table.php',
        '2026_10_20_120000_add_bank_transfer_payment_columns_to_transaction_table.php',
    ];

    public function blockReason(string $command): ?string
    {
        $command = strtolower(trim($command));
        if (! in_array($command, self::SCHEMA_COMMANDS, true)) {
            return null;
        }

        $dangerous = $this->dangerousFilesInUse();
        if ($dangerous !== [] && ! $this->flag('WEBSHOP_MIGRATIONS_UNLOCK', 'baseline-verified')) {
            return 'Migration bị chặn: thư mục database/migrations còn file không tương thích V2: '
                . implode(', ', $dangerous)
                . '. Giữ các file đó trong database/migrations_quarantine.';
        }

        try {
            $database = $this->actualDatabase();
        } catch (\Throwable $e) {
            return 'Migration bị chặn: không xác nhận được database đích. ' . $e->getMessage();
        }

        if ($database === 'webshop' && ! $this->liveUnlock()) {
            return 'Migration bị chặn: kết nối đang là database webshop. '
                . 'Lệnh không chạy trên live nếu thiếu WEBSHOP_ALLOW_LIVE_MIGRATE=I_UNDERSTAND_THIS_IS_LIVE '
                . 'và WEBSHOP_MIGRATIONS_UNLOCK=baseline-verified. Không đặt các biến này trong .env.';
        }

        $v2 = V2SchemaBaseline::present();
        $baseline = $this->baselineRecorded();

        if (in_array($command, self::DESTRUCTIVE_COMMANDS, true) && $v2 && ! $this->flag('WEBSHOP_ALLOW_DESTRUCTIVE_MIGRATE', 'I_UNDERSTAND_DATA_LOSS')) {
            return "Migration bị chặn: {$command} xóa bảng trên database {$database} trong khi schema V2 đang có.";
        }

        if ($v2 && ! $baseline && ! $this->flag('WEBSHOP_MIGRATIONS_UNLOCK', 'baseline-verified')) {
            return "Migration bị chặn: database {$database} đã có schema V2 nhưng bảng migrations chưa có baseline "
                . V2SchemaBaseline::MIGRATION
                . '. Không đánh dấu migration cũ là đã chạy và không sửa schema hiện có.';
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public function dangerousFilesInUse(): array
    {
        $found = [];
        foreach (self::DANGEROUS_FILES as $name) {
            if (is_file(database_path('migrations/' . $name))) {
                $found[] = $name;
            }
        }

        return $found;
    }

    public function actualDatabase(): string
    {
        $row = DB::selectOne('select database() as name');
        $actual = (string) ($row->name ?? '');
        $configured = (string) DB::connection()->getDatabaseName();
        if ($actual === '' || $actual !== $configured) {
            throw new MigrationBlockedException(
                "Database cấu hình [{$configured}] không khớp select database() [{$actual}]."
            );
        }

        return $actual;
    }

    public function baselineRecorded(): bool
    {
        if (! Schema::hasTable('migrations')) {
            return false;
        }

        return DB::table('migrations')->where('migration', V2SchemaBaseline::MIGRATION)->exists();
    }

    private function liveUnlock(): bool
    {
        return $this->flag('WEBSHOP_ALLOW_LIVE_MIGRATE', 'I_UNDERSTAND_THIS_IS_LIVE')
            && $this->flag('WEBSHOP_MIGRATIONS_UNLOCK', 'baseline-verified');
    }

    private function flag(string $key, string $expected): bool
    {
        $value = getenv($key);
        if ($value === false || $value === '') {
            $value = $_ENV[$key] ?? $_SERVER[$key] ?? '';
        }

        return hash_equals($expected, (string) $value);
    }
}
