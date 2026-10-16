<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model cho bảng `order` (chi tiết dòng trong đơn — RESERVED WORD trong SQL).
 *
 * Cột snapshot theo thời điểm mua: `size`, `color`, `amount`, `cost_price`.
 * `variant_id` = 0 nếu đặt từ dữ liệu cũ chưa có biến thể.
 *
 * Quan hệ:
 *   transaction_id -> transaction.id
 *   product_id     -> product.id
 *
 * @property int $id
 * @property int $transaction_id
 * @property int $product_id
 * @property string|null $size
 * @property string|null $color
 * @property int $qty
 * @property string $amount
 * @property int $status
 * @property int $variant_id
 * @property string $cost_price
 */
class OrderItem extends BaseModel
{
    protected $table = 'order';

    protected function casts(): array
    {
        return [
            'id' => 'int',
            'transaction_id' => 'int',
            'product_id' => 'int',
            'qty' => 'int',
            'amount' => 'decimal:2',
            'status' => 'int',
            'variant_id' => 'int',
            'cost_price' => 'decimal:2',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }
}
