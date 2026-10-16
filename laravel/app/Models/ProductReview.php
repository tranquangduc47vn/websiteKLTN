<?php

namespace App\Models;

use App\Models\Concerns\LegacyTimestamps;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model cho bảng `product_review`.
 *
 * Trạng thái duyệt: 0 = pending, 1 = approved, 2 = rejected.
 * Có hỗ trợ khách vãng lai (`user_id = 0`) qua `session_token` chống spam/trùng.
 *
 * @property int $id
 * @property int $product_id
 * @property int $user_id
 * @property string $user_name
 * @property int $stars
 * @property string|null $comment
 * @property int $status
 * @property int $reviewed_at
 * @property int $reviewed_by
 * @property string $session_token
 * @property int $created
 */
class ProductReview extends BaseModel
{
    use LegacyTimestamps;

    protected $table = 'product_review';

    public const STATUS_PENDING = 0;
    public const STATUS_APPROVED = 1;
    public const STATUS_REJECTED = 2;

    protected function casts(): array
    {
        return [
            'id' => 'int',
            'product_id' => 'int',
            'user_id' => 'int',
            'stars' => 'int',
            'status' => 'int',
            'reviewed_at' => 'int',
            'reviewed_by' => 'int',
            'created' => 'int',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'reviewed_by');
    }

    public function scopePending($query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeApproved($query): Builder
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function scopeRejected($query): Builder
    {
        return $query->where('status', self::STATUS_REJECTED);
    }

    public function scopeForProduct($query, int $productId): Builder
    {
        return $query->where('product_id', $productId);
    }

    public function isApproved(): bool
    {
        return (int) $this->status === self::STATUS_APPROVED;
    }

    public function createdAt(): ?\Illuminate\Support\Carbon
    {
        return $this->legacyTimestamp('created');
    }
}
