<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model cho bảng `stock_receipt_items` (dòng sản phẩm trong phiếu nhập kho).
 *
 * @property int $id
 * @property int $receipt_id
 * @property int $product_id
 * @property string $size
 * @property string $color
 * @property int $qty
 * @property string $unit_cost
 * @property string $note
 * @property int $variant_id
 * @property string $subtotal
 */
class StockReceiptItem extends BaseModel
{
    protected $table = 'stock_receipt_items';

    protected function casts(): array
    {
        return [
            'id' => 'int',
            'receipt_id' => 'int',
            'product_id' => 'int',
            'qty' => 'int',
            'unit_cost' => 'decimal:2',
            'variant_id' => 'int',
            'subtotal' => 'decimal:2',
        ];
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(StockReceipt::class, 'receipt_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function getSubtotalAttribute(): string
    {
        return (string) ($this->attributes['line_total'] ?? '0');
    }

    public function getColorAttribute(): string
    {
        return (string) ($this->attributes['color_name'] ?? '');
    }

    public function getSizeAttribute(): string
    {
        return (string) ($this->attributes['size_name'] ?? '');
    }
}
