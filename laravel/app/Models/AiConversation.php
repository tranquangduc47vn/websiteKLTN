<?php

namespace App\Models;

use App\Models\Concerns\LegacyTimestamps;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Model cho bảng `ai_conversation` (cuộc trò chuyện AI + nhân viên).
 *
 * Trạng thái: open → staff_joined → closed... (`status` là string).
 * `guest_token` dùng cho khách chưa đăng nhập.
 * `unread_staff`, `unread_customer` là cờ đánh dấu chưa đọc.
 *
 * @property int $id
 * @property int $user_id
 * @property string $guest_token
 * @property int $staff_id
 * @property string $staff_name
 * @property string $status
 * @property int $started
 * @property int $last_message
 * @property int $unread_staff
 * @property int $unread_customer
 */
class AiConversation extends BaseModel
{
    use LegacyTimestamps;

    protected $table = 'ai_conversation';

    public const STATUS_OPEN = 'open';
    public const STATUS_STAFF_JOINED = 'staff_joined';
    public const STATUS_CLOSED = 'closed';

    protected function casts(): array
    {
        return [
            'id' => 'int',
            'user_id' => 'int',
            'staff_id' => 'int',
            'started' => 'int',
            'last_message' => 'int',
            'unread_staff' => 'bool',
            'unread_customer' => 'bool',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'staff_id');
    }

    /**
     * Tin nhắn trong cuộc trò chuyện — đã sort theo id tăng dần để render chat đúng thứ tự.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(AiMessage::class, 'conversation_id')->orderBy('id');
    }

    /**
     * Tin nhắn gần nhất (dùng cho list admin).
     */
    public function latestMessages(): HasMany
    {
        return $this->hasMany(AiMessage::class, 'conversation_id')->orderByDesc('id');
    }

    public function scopeOpen($query)
    {
        return $query->where('status', self::STATUS_OPEN);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForGuest($query, string $token)
    {
        return $query->where('guest_token', $token);
    }

    public function scopeUnreadForStaff($query)
    {
        return $query->where('unread_staff', 1);
    }

    public function startedAt(): ?\Illuminate\Support\Carbon
    {
        return $this->legacyTimestamp('started');
    }

    public function lastMessageAt(): ?\Illuminate\Support\Carbon
    {
        return $this->legacyTimestamp('last_message');
    }
}
