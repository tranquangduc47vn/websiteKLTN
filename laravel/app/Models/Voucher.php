<?php

namespace App\Models;

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

    protected $table = 'vouchers';

    public $timestamps = true;

    public const TYPE_PERCENT = 'percent';
    public const TYPE_FIXED = 'fixed';

    protected function casts(): array
    {
        return [
            'id' => 'int',
            'discount_value' => 'decimal:2',
            'min_order_amount' => 'decimal:2',
            'max_discount' => 'decimal:2',
            'user_id' => 'int',
            'usage_limit' => 'int',
            'used_count' => 'int',
            'per_user_limit' => 'int',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'bool',
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

        if ($this->starts_at !== null && now()->lt($this->starts_at)) {
            return false;
        }

        if ($this->ends_at !== null && now()->gt($this->ends_at)) {
            return false;
        }

        if ((int) $this->usage_limit > 0 && (int) $this->used_count >= (int) $this->usage_limit) {
            return false;
        }

        return true;
    }

    public function getValidFromAttribute(): mixed
    {
        return $this->starts_at;
    }

    public function getValidToAttribute(): mixed
    {
        return $this->ends_at;
    }

    public function setValidFromAttribute(mixed $value): void
    {
        $this->attributes['starts_at'] = $this->dateOrNull($value);
        unset($this->attributes['valid_from']);
    }

    public function setValidToAttribute(mixed $value): void
    {
        $this->attributes['ends_at'] = $this->dateOrNull($value);
        unset($this->attributes['valid_to']);
    }

    public function setUserIdAttribute(mixed $value): void
    {
        $id = (int) $value;
        $this->attributes['user_id'] = $id > 0 ? $id : null;
    }

    private function dateOrNull(mixed $value): mixed
    {
        if ($value === null || $value === '' || $value === 0 || $value === '0') {
            return null;
        }
        if (is_numeric($value)) {
            return \Illuminate\Support\Carbon::createFromTimestamp((int) $value);
        }

        return $value;
    }
}
