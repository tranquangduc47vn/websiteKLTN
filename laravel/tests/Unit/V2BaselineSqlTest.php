<?php

namespace Tests\Unit;

use App\Database\V2SchemaBaseline;
use PHPUnit\Framework\TestCase;

class V2BaselineSqlTest extends TestCase
{
    public function test_baseline_sql_is_schema_only_and_lists_v2_tables(): void
    {
        $sql = (string) file_get_contents(dirname(__DIR__, 2) . '/database/schema/v2-baseline.sql');
        V2SchemaBaseline::assertSafeSql($sql);

        preg_match_all('/^CREATE TABLE `([^`]+)`/m', $sql, $matches);
        $tables = $matches[1];
        sort($tables);
        $expected = V2SchemaBaseline::TABLES;
        sort($expected);

        $this->assertSame($expected, $tables);
        $this->assertNotContains('migrations', $tables);
        $this->assertCount(31, $tables);
    }

    public function test_quarantined_migrations_are_not_in_the_active_directory(): void
    {
        foreach (V2SchemaBaseline::TABLES as $ignored) {
            break;
        }
        foreach ([
            '0001_01_01_000000_create_users_table.php',
            '0001_01_01_000001_create_cache_table.php',
            '0001_01_01_000002_create_jobs_table.php',
            '2026_10_20_120000_add_bank_transfer_payment_columns_to_transaction_table.php',
        ] as $name) {
            $this->assertFileDoesNotExist(dirname(__DIR__, 2) . '/database/migrations/' . $name);
            $this->assertFileExists(dirname(__DIR__, 2) . '/database/migrations_quarantine/' . $name);
        }
    }
}
