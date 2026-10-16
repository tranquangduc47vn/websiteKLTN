<?php

namespace App\Models;

use App\Models\Concerns\LegacyTimestamps;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Model cho bảng `product`.
 *
 * Lưu ý schema:
 *  - `color` / `size` là CSV string (vd "Đen,Xanh dương,Hồng" / "S,M,L") — dữ liệu danh mục.
 *  - Biến thể thật nằm ở bảng `product_variants` (sku, stock, price theo màu/size).
 *  - `image_link` là ảnh chính, `image_list` là JSON string danh sách ảnh phụ.
 *  - `discount` + `discount_type` (amount|percent) + `discount_percent` — logic giá ở Product_service.php.
 *  - `quantity` là tồn tổng (cũ), `product_variants.stock` mới là tồn chính xác.
 *
 * @property int $id
 * @property int $catalog_id
 * @property string $name
 * @property string $content
 * @property string|null $color
 * @property string|null $size
 * @property string $price          // DECIMAL(15,2)
 * @property int|null $discount
 * @property string $discount_type  // amount|percent
 * @property int $discount_percent
 * @property string $image_link
 * @property string $image_list     // JSON
 * @property int $view
 * @property int $buyed
 * @property int $quantity
 * @property int $rate_total
 * @property int $rate_count
 * @property int $created          // INT Unix
 * @property string $code           // SP00001
 * @property int $status
 */
class Product extends BaseModel
{
    use LegacyTimestamps;

    protected $table = 'product';

    /**
     * Khoảng giá dùng cho URL SEO (`/{slug}/duoi-200k`, `200k-500k`, `500k-1tr`, `tren-1tr`).
     * Giữ đúng tên slug để URL cũ không hỏng.
     */
    public const PRICE_FILTERS = [
        'duoi-200k'    => [0, 200000],
        '200k-500k'    => [200000, 500000],
        '500k-1tr'     => [500000, 1000000],
        'tren-1tr'     => [1000000, null],
    ];

    protected function casts(): array
    {
        return [
            'id' => 'int',
            'catalog_id' => 'int',
            'price' => 'decimal:2',
            'discount' => 'int',
            'discount_percent' => 'int',
            'view' => 'int',
            'buyed' => 'int',
            'quantity' => 'int',
            'rate_total' => 'int',
            'rate_count' => 'int',
            'created' => 'int',
            'status' => 'int',
        ];
    }

    public function catalog(): BelongsTo
    {
        return $this->belongsTo(Catalog::class, 'catalog_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class, 'product_id');
    }

    /**
     * Chỉ biến thể còn bán (status=1) và còn tồn > 0.
     */
    public function activeVariants(): HasMany
    {
        return $this->hasMany(ProductVariant::class, 'product_id')
            ->where('status', 1)
            ->orderBy('color')
            ->orderBy('size');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class, 'product_id');
    }

    public function approvedReviews(): HasMany
    {
        return $this->hasMany(ProductReview::class, 'product_id')->where('status', 1);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'product_id');
    }

    /**
     * Tổng tồn thực tế = SUM(product_variants.stock) — nguồn sự thật cho checkout.
     */
    public function totalStock(): int
    {
        return (int) $this->variants()->where('status', 1)->sum('stock');
    }

    public function createdAt(): ?\Illuminate\Support\Carbon
    {
        return $this->legacyTimestamp('created');
    }

    public function setCreatedAtAttribute(mixed $value): void
    {
        $this->setLegacyTimestamp('created', $value);
    }

    /**
     * Giá sau giảm — đúng công thức Product_service.php.
     */
    public function finalPrice(): float
    {
        $price = (float) $this->price;

        if ($this->discount_type === 'percent') {
            return round($price * (1 - ((float) $this->discount_percent / 100)), 2);
        }

        $amount = (float) ($this->discount ?: 0);

        return $amount > 0 ? max(0.0, round($price - $amount, 2)) : $price;
    }

    /**
     * Ảnh phụ dạng mảng (parse an toàn image_list).
     *
     * @return array<int, string>
     */
    public function imageList(): array
    {
        $decoded = json_decode((string) $this->image_list, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Danh sách màu (CSV → mảng).
     *
     * @return array<int, string>
     */
    public function colorList(): array
    {
        return $this->splitCsv($this->color);
    }

    /**
     * Danh sách size (CSV → mảng).
     *
     * @return array<int, string>
     */
    public function sizeList(): array
    {
        return $this->splitCsv($this->size);
    }

    /**
     * @return array<int, string>
     */
    protected function splitCsv(?string $value): array
    {
        if ($value === null || trim($value) === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $value)), fn ($v) => $v !== ''));
    }

    public function scopeActive($query): Builder
    {
        return $query->where('status', 1);
    }

    /**
     * Tìm kiếm sản phẩm — đồng bộ product_apply_search_keyword() trong
     * application/helpers/product_helper.php: khớp name, content, code và catalog.name.
     */
    public function scopeSearch($query, ?string $keyword, bool $includeCatalogName = true): Builder
    {
        if ($keyword === null || trim($keyword) === '') {
            return $query;
        }

        $like = '%' . str_replace(['%', '_'], ['\%', '\_'], trim($keyword)) . '%';

        return $query->where(function (Builder $q) use ($like, $includeCatalogName) {
            $q->where('product.name', 'LIKE', $like)
                ->orWhere('product.content', 'LIKE', $like)
                ->orWhere('product.code', 'LIKE', $like);

            if ($includeCatalogName) {
                $q->orWhereIn('product.catalog_id', function ($sub) use ($like) {
                    $sub->select('id')->from('catalog')->where('name', 'LIKE', $like);
                });
            }
        });
    }

    /**
     * Lọc theo slug giá của URL SEO. Trả về query đã áp điều kiện.
     */
    public function scopePriceFilter($query, ?string $slug): Builder
    {
        if ($slug === null || !isset(self::PRICE_FILTERS[$slug])) {
            return $query;
        }

        [$min, $max] = self::PRICE_FILTERS[$slug];

        return $query->where('price', '>=', $min)
            ->when($max !== null, fn (Builder $q) => $q->where('price', '<', $max));
    }

    public function scopeHot($query): Builder
    {
        return $query->orderByDesc('buyed')->orderByDesc('view');
    }

    public function scopeDiscounted($query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->where('discount_type', 'percent')->where('discount_percent', '>', 0)
                ->orWhere('discount', '>', 0);
        });
    }

    public function getRouteKeyName(): string
    {
        return 'id';
    }
}
