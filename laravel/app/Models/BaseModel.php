<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Base model cho toàn bộ model kế thừa schema CodeIgniter cũ.
 *
 * Quy ước dùng chung cho DB `webshop` (xem databaseaddphpmyadmin/webshop.sql):
 *  - Không có cột `created_at` / `updated_at` kiểu datetime; timestamp lưu dạng INT Unix.
 *  - Cột boolean lưu dạng TINYINT(1) (0/1).
 *  - Cột tiền tệ lưu kiểu DECIMAL(15,2).
 *  - Một số bảng lưu `created` dạng INT, số khác lưu `created` dạng DATETIME
 *    (catalog, slider, content_pages). Model tự khai báo phù hợp với bảng của mình.
 */
abstract class BaseModel extends Model
{
    /**
     * Schema cũ không dùng created_at/updated_at chuẩn của Laravel.
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
     * Cho phép gán mass-assignment cho toàn bộ cột (schema cũ không dùng $fillable).
     * Trong các module sau (B3+) sẽ thay bằng $fillable/$guarded chặt chẽ hơn.
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

    /**
     * Cache Carbon cho LegacyTimestamps. Khai báo ở BaseModel (không phải attribute)
     * để newInstance() không ghi nhầm vào INSERT của model không dùng trait (vd. `order`).
     *
     * @var array<string, \Illuminate\Support\Carbon|null>
     */
    protected array $legacyTimestampCache = [];

    /**
     * Reset cache timestamp khi hydrate bản ghi mới (Eloquent tái sử dụng instance).
     */
    public function newInstance($attributes = [], $exists = false)
    {
        $model = parent::newInstance($attributes, $exists);

        if ($model instanceof self) {
            $model->legacyTimestampCache = [];
        }

        return $model;
    }
}
