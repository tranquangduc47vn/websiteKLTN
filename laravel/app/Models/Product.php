<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Sản phẩm. Bảng V2: products.
 * Giá bán = list_price - discount_amount. Tồn kho nằm ở product_variants.stock.
 *
 * @property int $id
 * @property int $category_id
 * @property string $name
 * @property string $content
 * @property string $code
 * @property string $list_price
 * @property string $discount_amount
 * @property string $status
 * @property int $view_count
 * @property int $sold_count
 * @property int $rating_total
 * @property int $rating_count
 */
class Product extends BaseModel
{
    protected $table = 'products';

    public $timestamps = true;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'category_id',
        'name',
        'content',
        'code',
        'list_price',
        'discount_amount',
        'status',
        'view_count',
        'sold_count',
        'rating_total',
        'rating_count',
    ];

    /** @var list<string> */
    protected $guarded = ['*'];

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const PRICE_FILTERS = [
        'duoi-200k' => [0, 200000],
        '200k-500k' => [200000, 500000],
        '500k-1tr' => [500000, 1000000],
        'tren-1tr' => [1000000, null],
    ];

    protected function casts(): array
    {
        return [
            'id' => 'int',
            'category_id' => 'int',
            'list_price' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'view_count' => 'int',
            'sold_count' => 'int',
            'rating_total' => 'int',
            'rating_count' => 'int',
        ];
    }

    public function catalog(): BelongsTo
    {
        return $this->belongsTo(Catalog::class, 'category_id');
    }

    public function category(): BelongsTo
    {
        return $this->catalog();
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class, 'product_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class, 'product_id')->orderBy('sort_order')->orderBy('id');
    }

    public function activeVariants(): HasMany
    {
        return $this->hasMany(ProductVariant::class, 'product_id')
            ->where('status', ProductVariant::STATUS_ACTIVE)
            ->orderBy('color_name')
            ->orderBy('size_name');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class, 'product_id');
    }

    public function approvedReviews(): HasMany
    {
        return $this->hasMany(ProductReview::class, 'product_id')
            ->where('status', ProductReview::STATUS_APPROVED);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'product_id');
    }

    public function totalStock(): int
    {
        return (int) $this->variants()->where('status', ProductVariant::STATUS_ACTIVE)->sum('stock');
    }

    public function finalPrice(): float
    {
        return max(0.0, round((float) $this->list_price - (float) $this->discount_amount, 2));
    }

    public function primaryImagePath(): string
    {
        if ($this->relationLoaded('images')) {
            $primary = $this->images->firstWhere('is_primary', true)
                ?? $this->images->firstWhere('is_primary', 1);

            return $primary ? (string) $primary->path : '';
        }

        return (string) ($this->images()->where('is_primary', 1)->whereNull('variant_id')->value('path') ?? '');
    }

    /**
     * @return array<int, string>
     */
    public function imageList(): array
    {
        $query = $this->relationLoaded('images')
            ? $this->images
            : $this->images()->whereNull('variant_id')->where('is_primary', 0)->orderBy('sort_order')->get();

        return $query
            ->filter(fn ($image) => (int) $image->is_primary !== 1 && $image->variant_id === null)
            ->pluck('path')
            ->map(fn ($path) => (string) $path)
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function colorList(): array
    {
        return $this->variants()
            ->where('color_name', '!=', '')
            ->orderBy('id')
            ->pluck('color_name')
            ->map(fn ($name) => (string) $name)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function sizeList(): array
    {
        return $this->variants()
            ->where('size_name', '!=', '')
            ->orderBy('id')
            ->pluck('size_name')
            ->map(fn ($name) => (string) $name)
            ->unique()
            ->values()
            ->all();
    }

    public function scopeActive($query): Builder
    {
        return $query->where($this->getTable() . '.status', self::STATUS_ACTIVE);
    }

    /** Storefront: có ít nhất một biến thể đang bán với tồn > 0. */
    public function scopeInStock(Builder $query): Builder
    {
        return $query->whereHas('variants', function (Builder $variant): void {
            $variant->where('status', ProductVariant::STATUS_ACTIVE)
                ->where('stock', '>', 0);
        });
    }

    public function scopeSearch($query, ?string $keyword, bool $includeCatalogName = true): Builder
    {
        if ($keyword === null || trim($keyword) === '') {
            return $query;
        }

        $like = '%' . str_replace(['%', '_'], ['\%', '\_'], trim($keyword)) . '%';
        $table = $this->getTable();

        return $query->where(function (Builder $q) use ($like, $includeCatalogName, $table) {
            $q->where($table . '.name', 'LIKE', $like)
                ->orWhere($table . '.content', 'LIKE', $like)
                ->orWhere($table . '.code', 'LIKE', $like);

            if ($includeCatalogName) {
                $q->orWhereIn($table . '.category_id', function ($sub) use ($like) {
                    $sub->select('id')->from('categories')->where('name', 'LIKE', $like);
                });
            }
        });
    }

    public function scopePriceFilter($query, ?string $slug): Builder
    {
        if ($slug === null || ! isset(self::PRICE_FILTERS[$slug])) {
            return $query;
        }

        [$min, $max] = self::PRICE_FILTERS[$slug];
        $expr = '(list_price - discount_amount)';

        return $query->whereRaw($expr . ' >= ?', [$min])
            ->when($max !== null, fn (Builder $q) => $q->whereRaw($expr . ' < ?', [$max]));
    }

    public function scopeHot($query): Builder
    {
        return $query->orderByDesc('sold_count')->orderByDesc('view_count');
    }

    public function scopeDiscounted($query): Builder
    {
        return $query->where('discount_amount', '>', 0);
    }

    public function getRouteKeyName(): string
    {
        return 'id';
    }

    public function getPriceAttribute(): string
    {
        return (string) ($this->attributes['list_price'] ?? '0');
    }

    public function getDiscountAttribute(): string
    {
        return (string) ($this->attributes['discount_amount'] ?? '0');
    }

    public function getBuyedAttribute(): int
    {
        return (int) ($this->attributes['sold_count'] ?? 0);
    }

    public function getViewAttribute(): int
    {
        return (int) ($this->attributes['view_count'] ?? 0);
    }

    public function getRateTotalAttribute(): int
    {
        return (int) ($this->attributes['rating_total'] ?? 0);
    }

    public function getRateCountAttribute(): int
    {
        return (int) ($this->attributes['rating_count'] ?? 0);
    }

    public function getCatalogIdAttribute(): int
    {
        return (int) ($this->attributes['category_id'] ?? 0);
    }

    public function getImageLinkAttribute(): string
    {
        return $this->primaryImagePath();
    }

    public function getQuantityAttribute(): int
    {
        return $this->totalStock();
    }
}
