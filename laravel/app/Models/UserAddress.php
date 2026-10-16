<?php

namespace App\Models;

use App\Models\Concerns\LegacyTimestamps;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model cho bảng `user_address` (MyISAM — không FK thật, quan hệ khai báo qua user_id).
 *
 * Địa chỉ dạng chuỗi đầy đủ (`address_line`) + mã VN (province_id/district_id/ward_id)
 * dùng cho API địa chỉ ở `vn_address_helper.php`.
 *
 * @property int $id
 * @property int $user_id
 * @property string $address_note
 * @property string $province_id
 * @property string $district_id
 * @property string $ward_id
 * @property string $address_line
 * @property int $is_default
 * @property int $created
 */
class UserAddress extends BaseModel
{
    use LegacyTimestamps;

    protected $table = 'user_address';

    /**
     * Giới hạn số địa chỉ mỗi user (đồng bộ với User_address_model::MAX_PER_USER).
     */
    public const MAX_PER_USER = 3;

    protected function casts(): array
    {
        return [
            'id' => 'int',
            'user_id' => 'int',
            'is_default' => 'bool',
            'created' => 'int',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', 1);
    }

    public function createdAt(): ?\Illuminate\Support\Carbon
    {
        return $this->legacyTimestamp('created');
    }
}
