<?php

namespace App\Models;

use App\Models\Concerns\LegacyTimestamps;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Model cho bảng `product_variants`.
 *
 * Mỗi dòng = 1 SKU (màu + size). Đây là nơi lưu tồn kho thật và giá bán theo biến thể.
 * `min_stock` mặc định lấy từ config('stock.default_min_stock') = 5.
 *
 * @property int $id
 * @property int $product_id
 * @property string $sku
 * @property string $color
 * @property string $size
 * @property string $price
 * @property string $cost_price
 * @property int $stock
 * @property int $min_stock
 * @property string $image
 * @property int $status
 * @property int $created
 * @property int $updated
 */
class ProductVariant extends BaseModel
{
    use LegacyTimestamps;

    protected $table = 'product_variants';

    protected function casts(): array
    {
        return [
            'id' => 'int',
            'product_id' => 'int',
            'price' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'stock' => 'int',
            'min_stock' => 'int',
            'status' => 'int',
            'created' => 'int',
            'updated' => 'int',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'variant_id');
    }

    public function receiptItems(): HasMany
    {
        return $this->hasMany(StockReceiptItem::class, 'variant_id');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'variant_id');
    }

    /**
     * Biến thể còn bán và còn tồn.
     */
    public function scopeAvailable($query): Builder
    {
        return $query->where('status', 1)->where('stock', '>', 0);
    }

    /**
     * Biến thể dưới ngưỡng tồn tối thiểu (dùng cho cảnh báo admin / sản phẩm hết hàng).
     */
    public function scopeLowStock($query): Builder
    {
        return $query->whereColumn('stock', '<=', 'min_stock')->where('status', 1);
    }

    public function isAvailable(): bool
    {
        return (int) $this->status === 1 && (int) $this->stock > 0;
    }

    public function isLowStock(): bool
    {
        return (int) $this->stock <= (int) $this->min_stock;
    }

    public function createdAt(): ?\Illuminate\Support\Carbon
    {
        return $this->legacyTimestamp('created');
    }

    public function updatedAt(): ?\Illuminate\Support\Carbon
    {
        return $this->legacyTimestamp('updated');
    }
}
