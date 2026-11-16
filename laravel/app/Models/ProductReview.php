<?php

namespace App\Models;

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

    protected $table = 'product_reviews';

    public const UPDATED_AT = null;

    public $timestamps = true;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected function casts(): array
    {
        return [
            'id' => 'int',
            'product_id' => 'int',
            'user_id' => 'int',
            'stars' => 'int',
            'reviewed_at' => 'datetime',
            'reviewed_by' => 'int',
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
        return $this->status === self::STATUS_APPROVED;
    }

    public function getCreatedAttribute(): int
    {
        $value = $this->created_at;

        return $value ? $value->getTimestamp() : 0;
    }
}
