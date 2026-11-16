<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model cho bảng `voucher_use` (lần sử dụng voucher theo đơn hàng).
 *
 * @property int $id
 * @property int $voucher_id
 * @property int $user_id
 * @property int $transaction_id
 * @property int $discount_amount
 * @property int $created
 */
class VoucherUse extends BaseModel
{
    protected $table = 'voucher_uses';

    public const UPDATED_AT = null;

    public $timestamps = true;

    protected function casts(): array
    {
        return [
            'id' => 'int',
            'voucher_id' => 'int',
            'user_id' => 'int',
            'order_id' => 'int',
            'discount_amount' => 'decimal:2',
        ];
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class, 'voucher_id');
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
