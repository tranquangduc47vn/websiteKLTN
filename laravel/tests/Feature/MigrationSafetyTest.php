<?php

namespace Tests\Feature;

use App\Database\MigrationSafetyGuard;
use App\Database\V2SchemaBaseline;
use Illuminate\Support\Facades\DB;
use Tests\LegacyMysqlTestCase;

class MigrationSafetyTest extends LegacyMysqlTestCase
{
    public function test_guard_blocks_schema_commands_on_v2_database_without_baseline(): void
    {
        $guard = app(MigrationSafetyGuard::class);
        $orders = (int) DB::table('orders')->count();
        $migrationRows = DB::table('migrations')->count();

        $reason = $guard->blockReason('migrate');
        $this->assertNotNull($reason);
        $this->assertStringContainsString('webshop_phase2_audit', $reason);
        $this->assertStringContainsString(V2SchemaBaseline::MIGRATION, $reason);
        $this->assertNotNull($guard->blockReason('migrate:fresh'));
        $this->assertNotNull($guard->blockReason('migrate:rollback'));
        $this->assertNotNull($guard->blockReason('db:wipe'));
        $this->assertNull($guard->blockReason('about'));
        $this->assertSame('webshop_phase2_audit', $guard->actualDatabase());
        $this->assertSame([], $guard->dangerousFilesInUse());
        $this->assertFalse($guard->baselineRecorded());
        $this->assertSame($orders, (int) DB::table('orders')->count());
        $this->assertSame($migrationRows, DB::table('migrations')->count());
    }
}
