<?php

namespace App\Models;

use App\Models\Concerns\LegacyTimestamps;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model cho bảng `user_phone`.
 *
 * @property int $id
 * @property int $user_id
 * @property string $phone_label
 * @property string $phone_number
 * @property int $is_default
 * @property int $created
 */
class UserPhone extends BaseModel
{
    use LegacyTimestamps;

    protected $table = 'user_phone';

    /**
     * Giới hạn số điện thoại mỗi user (đồng bộ với User_phone_model::MAX_PER_USER).
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
