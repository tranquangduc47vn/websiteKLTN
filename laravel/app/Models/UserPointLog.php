<?php

namespace App\Models;

use App\Models\Concerns\LegacyTimestamps;
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
    use LegacyTimestamps;

    protected $table = 'user_point_log';

    public const TYPE_EARN = 'earn';
    public const TYPE_REDEEM = 'redeem';
    public const TYPE_ADJUST = 'adjust';
    public const TYPE_REVERSE = 'reverse';

    protected function casts(): array
    {
        return [
            'id' => 'int',
            'user_id' => 'int',
            'points' => 'int',
            'transaction_id' => 'int',
            'created' => 'int',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }

    public function createdAt(): ?\Illuminate\Support\Carbon
    {
        return $this->legacyTimestamp('created');
    }
}
