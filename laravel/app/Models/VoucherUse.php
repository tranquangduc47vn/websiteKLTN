<?php

namespace App\Models;

use App\Models\Concerns\LegacyTimestamps;
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
    use LegacyTimestamps;

    protected $table = 'voucher_use';

    protected function casts(): array
    {
        return [
            'id' => 'int',
            'voucher_id' => 'int',
            'user_id' => 'int',
            'transaction_id' => 'int',
            'discount_amount' => 'int',
            'created' => 'int',
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
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }

    public function createdAt(): ?\Illuminate\Support\Carbon
    {
        return $this->legacyTimestamp('created');
    }
}
