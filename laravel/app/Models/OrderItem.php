<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Dòng đơn. Bảng V2: order_items.
 *
 * @property int $id
 * @property int $order_id
 * @property int $product_id
 * @property int $variant_id
 * @property string $product_name
 * @property string $sku
 * @property string|null $color_name
 * @property string|null $size_name
 * @property int $qty
 * @property string $unit_price
 * @property string $line_subtotal
 * @property string $cost_price
 */
class OrderItem extends BaseModel
{
    protected $table = 'order_items';

    public $timestamps = true;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'order_id',
        'product_id',
        'variant_id',
        'product_name',
        'sku',
        'color_name',
        'size_name',
        'qty',
        'unit_price',
        'line_subtotal',
        'cost_price',
    ];

    /** @var list<string> */
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'id' => 'int',
            'order_id' => 'int',
            'product_id' => 'int',
            'variant_id' => 'int',
            'qty' => 'int',
            'unit_price' => 'decimal:2',
            'line_subtotal' => 'decimal:2',
            'cost_price' => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'order_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->order();
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function getAmountAttribute(): string
    {
        return (string) ($this->attributes['line_subtotal'] ?? '0');
    }

    public function getColorAttribute(): ?string
    {
        return $this->attributes['color_name'] ?? null;
    }

    public function getSizeAttribute(): ?string
    {
        return $this->attributes['size_name'] ?? null;
    }

    public function getTransactionIdAttribute(): int
    {
        return (int) ($this->attributes['order_id'] ?? 0);
    }
}
