<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model cho bảng `password_resets`.
 *
 * Token lưu dạng hash SHA-256 (`token_hash`, 64 hex) chứ không phải plain.
 * `expires_at`, `used_at`, `created` là INT Unix.
 *
 * @property int $id
 * @property int $user_id
 * @property string $token_hash
 * @property int $expires_at
 * @property int $used_at
 * @property int $created
 */
class PasswordReset extends BaseModel
{
    protected $table = 'password_resets';

    protected function casts(): array
    {
        return [
            'id' => 'int',
            'user_id' => 'int',
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Token còn hiệu lực và chưa dùng.
     */
    public function isUsable(): bool
    {
        return $this->used_at === null
            && $this->expires_at !== null
            && $this->expires_at->isFuture();
    }

    public function scopeUnused($query)
    {
        return $query->whereNull('used_at');
    }

    public function scopeValid($query)
    {
        return $query->whereNull('used_at')->where('expires_at', '>', now());
    }
}
