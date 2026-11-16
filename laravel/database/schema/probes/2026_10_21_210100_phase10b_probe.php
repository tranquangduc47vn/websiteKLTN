<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Probe chỉ chạy khi gọi artisan với --path=database/schema/probes.
 * Không nằm trong database/migrations nên migrate thường không thấy file này.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('phase10b_probe', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('phase10b_probe');
    }
};
