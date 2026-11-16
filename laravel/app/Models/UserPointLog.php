<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model cho bảng `user_point_log` (lịch sử tích/tiêu điểm loyalty).
 *
 * @property int $id
 * @property int $user_id
 * @property int $points
 * @property string $type  // earn|redeem|adjust
 * @property int $transaction_id
 * @property string $note
 * @property int $created
 */
class UserPointLog extends BaseModel
{
    protected $table = 'point_logs';

    public const UPDATED_AT = null;

    public $timestamps = true;

    public const TYPE_EARN = 'earn';

    public const TYPE_REVERSE = 'reverse';

    protected function casts(): array
    {
        return [
            'id' => 'int',
            'user_id' => 'int',
            'points' => 'int',
            'order_id' => 'int',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'order_id');
    }

    public function getTransactionIdAttribute(): int
    {
        return (int) ($this->attributes['order_id'] ?? 0);
    }
}
