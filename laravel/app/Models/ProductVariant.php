<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Biến thể. Tồn kho duy nhất: stock. Không có giá bán riêng.
 *
 * @property int $id
 * @property int $product_id
 * @property string $sku
 * @property string $color_name
 * @property string $size_name
 * @property string $cost_price
 * @property int $stock
 * @property int $min_stock
 * @property string $status
 */
class ProductVariant extends BaseModel
{
    protected $table = 'product_variants';

    public $timestamps = true;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    protected function casts(): array
    {
        return [
            'id' => 'int',
            'product_id' => 'int',
            'cost_price' => 'decimal:2',
            'stock' => 'int',
            'min_stock' => 'int',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class, 'variant_id');
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

    public function scopeAvailable($query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE)->where('stock', '>', 0);
    }

    public function scopeLowStock($query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->whereColumn('stock', '<=', 'min_stock');
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
