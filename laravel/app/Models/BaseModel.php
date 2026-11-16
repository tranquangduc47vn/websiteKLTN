<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Base model.
 *
 * Mặc định tắt timestamps của Laravel. Model có cột DATETIME thì tự bật
 * `$timestamps`, hoặc khai báo `CREATED_AT` / `UPDATED_AT` khi schema chỉ có một cột.
 */
abstract class BaseModel extends Model
{
    /**
     * Bật ở từng model khi bảng có created_at và/hoặc updated_at DATETIME.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * Tên bảng thật trong DB (mặc định Eloquent tự suy ra, override khi cần).
     *
     * @var string
     */
    protected $table = '';

    /**
     * Khóa chính.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * Không dùng auto-increment cho các bảng có khóa tự nhiên / composite.
     *
     * @var bool
     */
    public $incrementing = true;

    /**
     * Kiểu khóa chính.
     *
     * @var string
     */
    protected $keyType = 'int';

    /**
     * Model chưa chuyển whitelist vẫn mở mass assignment.
     * User, Product, Transaction, OrderItem và Payment tự khai báo $fillable.
     *
     * @var array<int, string>
     */
    protected $guarded = [];

    /**
     * Cast chung cho các cột kiểu int/decimal/tinyint.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'int',
        ];
    }
}
