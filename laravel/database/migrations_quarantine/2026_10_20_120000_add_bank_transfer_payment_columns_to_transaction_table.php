<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Thêm cột theo dõi chuyển khoản. Không đổi dữ liệu đơn cũ.
 * Các cột mới đều NULL nên bản ghi hiện có giữ nguyên.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaction', function (Blueprint $table) {
            $table->string('payment_ref', 32)->nullable()->unique('transaction_payment_ref_unique');
            $table->string('payment_status', 16)->nullable();
            $table->string('payment_gateway_id', 64)->nullable()->unique('transaction_payment_gateway_unique');
            $table->unsignedInteger('payment_paid_at')->nullable();
            $table->decimal('payment_received_amount', 15, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('transaction', function (Blueprint $table) {
            $table->dropUnique('transaction_payment_ref_unique');
            $table->dropUnique('transaction_payment_gateway_unique');
            $table->dropColumn([
                'payment_ref',
                'payment_status',
                'payment_gateway_id',
                'payment_paid_at',
                'payment_received_amount',
            ]);
        });
    }
};
