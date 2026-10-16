<?php

namespace App\Models;

use App\Models\Concerns\LegacyTimestamps;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Model cho bảng `voucher`.
 *
 * Hỗ trợ hai loại giảm: percent / fixed. `discount_value` là % hoặc số tiền.
 * `tier_min` là hạng tối thiểu (member/silver/gold/diamond).
 * `user_id > 0` nếu voucher riêng cho 1 khách.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string $description
 * @property string $discount_type // percent|fixed
 * @property int $discount_value
 * @property int $min_order_amount
 * @property int $max_discount
 * @property string $tier_min
 * @property int $user_id
 * @property int $usage_limit
 * @property int $used_count
 * @property int $per_user_limit
 * @property int $valid_from
 * @property int $valid_to
 * @property int $is_active
 * @property int $created
 */
class Voucher extends BaseModel
{
    use LegacyTimestamps;

    protected $table = 'voucher';

    public const TYPE_PERCENT = 'percent';
    public const TYPE_FIXED = 'fixed';

    protected function casts(): array
    {
        return [
            'id' => 'int',
            'discount_value' => 'int',
            'min_order_amount' => 'int',
            'max_discount' => 'int',
            'user_id' => 'int',
            'usage_limit' => 'int',
            'used_count' => 'int',
            'per_user_limit' => 'int',
            'valid_from' => 'int',
            'valid_to' => 'int',
            'is_active' => 'bool',
            'created' => 'int',
        ];
    }

    public function uses(): HasMany
    {
        return $this->hasMany(VoucherUse::class, 'voucher_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Tính số tiền được giảm theo đúng công thức cũ.
     */
    public function calculateDiscount(float $subtotal): float
    {
        if ($this->discount_type === self::TYPE_PERCENT) {
            $discount = $subtotal * ((float) $this->discount_value / 100);
        } else {
            $discount = (float) $this->discount_value;
        }

        if ((int) $this->max_discount > 0) {
            $discount = min($discount, (float) $this->max_discount);
        }

        return max(0.0, round($discount, 0));
    }

    /**
     * Voucher còn hiệu lực theo thời gian + còn lượt dùng.
     */
    public function isUsable(): bool
    {
        if (! (bool) $this->is_active) {
            return false;
        }

        $now = now()->timestamp;

        if ((int) $this->valid_from > 0 && $now < (int) $this->valid_from) {
            return false;
        }

        if ((int) $this->valid_to > 0 && $now > (int) $this->valid_to) {
            return false;
        }

        if ((int) $this->usage_limit > 0 && (int) $this->used_count >= (int) $this->usage_limit) {
            return false;
        }

        return true;
    }
}
