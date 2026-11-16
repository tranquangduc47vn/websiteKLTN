<?php

use App\Database\MigrationBlockedException;
use App\Database\V2SchemaBaseline;
use Illuminate\Database\Migrations\Migration;

/**
 * Database trống: tạo 31 bảng V2 từ database/schema/v2-baseline.sql.
 * Database đã có orders: chỉ kiểm tra đủ bảng, không ALTER và không ghi dữ liệu nghiệp vụ.
 * Rollback của baseline không xóa bảng. Khôi phục live bằng file backup SQL.
 */
return new class extends Migration
{
    public function up(): void
    {
        V2SchemaBaseline::install();
    }

    public function down(): void
    {
        throw new MigrationBlockedException(
            'Không rollback baseline V2 bằng migrate:rollback. Khôi phục từ backup SQL đã được kiểm chứng, không DROP bảng live.'
        );
    }
};
