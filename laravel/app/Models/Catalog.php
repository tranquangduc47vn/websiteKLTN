<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Model cho bảng `catalog`.
 *
 * Danh mục cây: `parent_id` (0 = root). Thứ tự `sort_order`.
 * Timestamp `created` dạng DATETIME (khác với các bảng khác dùng INT).
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property int $parent_id
 * @property int $sort_order
 * @property string|null $created  // DATETIME
 */
class Catalog extends BaseModel
{
    protected $table = 'catalog';

    protected function casts(): array
    {
        return [
            'id' => 'int',
            'parent_id' => 'int',
            'sort_order' => 'int',
            'created' => 'datetime',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    /**
     * Sản phẩm thuộc danh mục này.
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'catalog_id');
    }

    public function scopeRoots($query)
    {
        return $query->where('parent_id', 0);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
