<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $product_id
 * @property int|null $variant_id
 * @property string $path
 * @property int $sort_order
 * @property bool $is_primary
 */
class ProductImage extends BaseModel
{
    protected $table = 'product_images';

    public const UPDATED_AT = null;

    public $timestamps = true;

    protected function casts(): array
    {
        return [
            'id' => 'int',
            'product_id' => 'int',
            'variant_id' => 'int',
            'sort_order' => 'int',
            'is_primary' => 'bool',
        ];
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
