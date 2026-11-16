<?php

namespace App\Console\Commands;

use App\Database\MigrationSafetyGuard;
use App\Database\V2SchemaBaseline;
use Illuminate\Console\Command;

class MigrationStatusCommand extends Command
{
    protected $signature = 'webshop:migration-status';

    protected $description = 'Xem database đích và lý do guard chặn migrate. Không đổi schema.';

    public function handle(MigrationSafetyGuard $guard): int
    {
        try {
            $database = $guard->actualDatabase();
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->line('database=' . $database);
        $this->line('v2_schema=' . (V2SchemaBaseline::present() ? 'yes' : 'no'));
        $this->line('baseline_recorded=' . ($guard->baselineRecorded() ? 'yes' : 'no'));
        $this->line('dangerous_files=' . count($guard->dangerousFilesInUse()));
        $reason = $guard->blockReason('migrate');
        $this->line('migrate=' . ($reason === null ? 'allowed' : 'blocked'));
        if ($reason !== null) {
            $this->warn($reason);
        }

        return self::SUCCESS;
    }
}
