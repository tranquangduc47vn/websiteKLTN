<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Support\Admin\ProductImageStore;
use App\Support\HtmlSanitizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Admin sản phẩm: port admin/Product + Product_service::sync_variants.
 * Tồn kho biến thể không sửa ở đây (phiếu nhập thuộc B11).
 */
class ProductAdminService
{
    public const PER_PAGE = 10;

    public function __construct(private readonly ProductImageStore $images)
    {
    }

    /**
     * @param  array{name?: string, catalog_id?: int|string, status?: string}  $filters
     * @return array{total: int, rows: list<object>, offset: int}
     */
    public function list(array $filters, int $offset): array
    {
        $offset = max(0, $offset);
        $query = DB::table('products')
            ->join('categories', 'categories.id', '=', 'products.category_id');

        $name = trim((string) ($filters['name'] ?? ''));
        if ($name !== '') {
            $like = '%' . addcslashes($name, '%_\\') . '%';
            $query->where('products.name', 'like', $like);
        }
        $catalogId = (int) ($filters['catalog_id'] ?? 0);
        if ($catalogId > 0) {
            $query->where('products.category_id', $catalogId);
        }
        $status = (string) ($filters['status'] ?? '');
        if (in_array($status, ['0', 'inactive'], true)) {
            $query->where('products.status', Product::STATUS_INACTIVE);
        } elseif (in_array($status, ['1', 'active'], true)) {
            $query->where('products.status', Product::STATUS_ACTIVE);
        }

        $total = (int) (clone $query)->count();
        $rows = $query
            ->orderByDesc('products.id')
            ->offset($offset)
            ->limit(self::PER_PAGE)
            ->get([
                'products.id',
                'products.name',
                'products.list_price as price',
                'products.discount_amount as discount',
                'products.status',
                'products.view_count as view',
                'products.sold_count as buyed',
                'categories.name as namecatalog',
                DB::raw('(SELECT path FROM product_images WHERE product_id = products.id AND variant_id IS NULL AND is_primary = 1 ORDER BY id LIMIT 1) as image_link'),
                DB::raw("(SELECT COALESCE(SUM(stock),0) FROM product_variants WHERE product_id = products.id AND status = 'active') as quantity"),
            ])
            ->all();

        return ['total' => $total, 'rows' => $rows, 'offset' => $offset];
    }

    /** @return list<object> Nhóm parent_id = 1, kèm con. */
    public function catalogTree(): array
    {
        $parents = DB::table('categories')->where('parent_id', 1)->orderBy('sort_order')->orderBy('id')->get();
        if ($parents->isEmpty()) {
            return [];
        }
        $ids = $parents->pluck('id')->all();
        $children = DB::table('categories')->whereIn('parent_id', $ids)->orderBy('sort_order')->orderBy('id')->get()->groupBy('parent_id');
        foreach ($parents as $parent) {
            $parent->sub = ($children[$parent->id] ?? collect())->values()->all();
        }

        return $parents->all();
    }

    /** @return array<string, string> name => hex */
    public function colors(array $extra = []): array
    {
        $rows = DB::table('colors')->where('status', 'active')->orderBy('sort_order')->orderBy('name')->get(['name', 'hex_code']);
        $colors = [];
        if ($rows->isEmpty()) {
            $colors = [
                'Đen' => '#000000', 'Trắng' => '#ffffff', 'Đỏ' => '#dd021b', 'Vàng' => '#fcd535',
                'Xanh dương' => '#2196f3', 'Xanh lá' => '#4caf50', 'Hồng' => '#ffb6c1', 'Xám' => '#808080',
                'Nâu' => '#8b5a2b', 'Kem' => '#f5f5dc', 'Cam' => '#ff9800', 'Tím' => '#9c27b0',
            ];
        } else {
            foreach ($rows as $row) {
                $colors[(string) $row->name] = (string) $row->hex_code;
            }
        }
        foreach ($extra as $name) {
            $name = trim((string) $name);
            if ($name !== '' && ! isset($colors[$name])) {
                $colors[$name] = '#cccccc';
            }
        }

        return $colors;
    }

    /** @return list<string> */
    public function sizes(array $extra = []): array
    {
        $rows = DB::table('sizes')->where('status', 'active')->orderBy('sort_order')->orderBy('name')->pluck('name');
        $sizes = $rows->isEmpty() ? ['S', 'M', 'L', 'XL', 'XXL'] : $rows->map(fn ($name) => (string) $name)->all();
        foreach ($extra as $name) {
            $name = trim((string) $name);
            if ($name !== '' && ! in_array($name, $sizes, true)) {
                $sizes[] = $name;
            }
        }

        return $sizes;
    }

    public static function parsePrice(mixed $raw): int
    {
        return (int) str_replace(',', '', (string) $raw);
    }

    /**
     * @return array{discount: int, discount_type: string, discount_percent: int}
     */
    public function discountFields(int $price, mixed $raw): array
    {
        $raw = str_replace(',', '', (string) $raw);
        if ($raw === '' || ! is_numeric($raw)) {
            return ['discount' => 0, 'discount_type' => 'percent', 'discount_percent' => 0];
        }
        $percent = max(0, min(100, (float) $raw));
        $amount = $price > 0 ? (int) round($price * $percent / 100) : 0;

        return [
            'discount' => $amount,
            'discount_type' => 'percent',
            'discount_percent' => (int) round($percent),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  list<UploadedFile>  $gallery
     * @return array{product: Product, warning: string}
     */
    public function create(array $input, UploadedFile $image, array $gallery = []): array
    {
        $main = $this->images->store($image);
        $stored = [$main];
        $galleryNames = [];
        $galleryWarning = '';
        foreach ($gallery as $file) {
            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                continue;
            }
            try {
                $name = $this->images->store($file);
                $stored[] = $name;
                if ($name !== $main) {
                    $galleryNames[] = $name;
                }
            } catch (RuntimeException $e) {
                $galleryWarning = ' Ảnh kèm theo: ' . $e->getMessage();
            }
        }

        try {
            $price = (int) $input['price_value'];
            $discount = $this->discountFields($price, $input['discount'] ?? '');
            $colors = $this->normalizeList($input['color'] ?? []);
            $sizes = $this->normalizeList($input['size'] ?? []);

            $product = DB::transaction(function () use ($input, $main, $galleryNames, $price, $discount, $colors, $sizes) {
                $product = Product::query()->create([
                    'name' => (string) $input['name'],
                    'content' => HtmlSanitizer::clean((string) ($input['content'] ?? '')),
                    'category_id' => (int) $input['catalog_id'],
                    'list_price' => $price,
                    'discount_amount' => $discount['discount'],
                    'status' => Product::STATUS_ACTIVE,
                    'view_count' => 0,
                    'sold_count' => 0,
                    'rating_total' => 0,
                    'rating_count' => 0,
                    'code' => 'TMP' . bin2hex(random_bytes(6)),
                ]);
                $code = 'SP' . str_pad((string) $product->id, 5, '0', STR_PAD_LEFT);
                $product->update(['code' => $code]);
                $this->replaceImages((int) $product->id, $main, $galleryNames);
                $this->syncVariants($product->id, $colors, $sizes, 0);

                return $product->fresh();
            });
        } catch (\Throwable $e) {
            foreach ($stored as $name) {
                $this->images->delete($name);
            }
            throw $e;
        }

        return ['product' => $product, 'warning' => $galleryWarning];
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  list<UploadedFile>  $gallery
     */
    public function update(Product $product, array $input, ?UploadedFile $image, array $gallery = []): string
    {
        $oldPrice = (float) $product->price;
        $price = (int) $input['price_value'];
        $discount = $this->discountFields($price, $input['discount'] ?? '');
        $colors = $this->normalizeList($input['color'] ?? []);
        $sizes = $this->normalizeList($input['size'] ?? []);

        $main = $this->images->sanitize($input['product_image_main'] ?? $product->image_link);
        $list = $input['product_image_list'] ?? null;
        if (is_string($list)) {
            $list = json_decode($list, true);
        }
        if (! is_array($list)) {
            $list = $product->imageList();
        }
        $list = array_values(array_unique(array_filter(array_map(fn ($name) => $this->images->sanitize($name), $list))));
        $list = array_values(array_filter($list, fn ($name) => $name !== '' && $name !== $main));

        $removed = $input['product_images_remove'] ?? [];
        if (! is_array($removed)) {
            $removed = [];
        }
        $removed = array_values(array_unique(array_filter(array_map(fn ($name) => $this->images->sanitize($name), $removed))));

        $newFiles = [];
        if ($image instanceof UploadedFile && $image->isValid()) {
            $uploaded = $this->images->store($image);
            $newFiles[] = $uploaded;
            if ($main !== '' && $main !== $uploaded) {
                $removed[] = $main;
            }
            $main = $uploaded;
        }

        $galleryWarning = '';
        foreach ($gallery as $file) {
            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                continue;
            }
            try {
                $name = $this->images->store($file);
                $newFiles[] = $name;
                if ($name !== '' && $name !== $main) {
                    $list[] = $name;
                }
            } catch (RuntimeException $e) {
                $galleryWarning = ' Ảnh kèm theo: ' . $e->getMessage();
            }
        }
        $list = array_values(array_unique($list));

        $data = [
            'name' => (string) $input['name'],
            'category_id' => (int) $input['catalog_id'],
            'list_price' => $price,
            'discount_amount' => $discount['discount'],
            'content' => HtmlSanitizer::clean((string) ($input['content'] ?? '')),
            'status' => in_array((string) ($input['status'] ?? '1'), ['0', 'inactive'], true)
                ? Product::STATUS_INACTIVE
                : Product::STATUS_ACTIVE,
        ];

        try {
            DB::transaction(function () use ($product, $data, $colors, $sizes, $main, $list, $input) {
                $product->update($data);
                $this->replaceImages((int) $product->id, $main, $list);
                $this->syncVariants($product->id, $colors, $sizes, 0);
                $this->saveVariantEdits($product->id, $input['variant'] ?? []);
            });
        } catch (\Throwable $e) {
            foreach ($newFiles as $name) {
                $this->images->delete($name);
            }
            throw $e;
        }

        $still = $list;
        if ($main !== '') {
            $still[] = $main;
        }
        foreach (array_unique($removed) as $filename) {
            if (in_array($filename, $still, true) || $this->imageStillUsed($filename, $product->id)) {
                continue;
            }
            $this->images->delete($filename);
        }

        return $galleryWarning;
    }

    /** @param  list<int|string>  $ids */
    public function deleteByIds(array $ids): int
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        $deleted = 0;
        $files = [];

        DB::transaction(function () use ($ids, &$deleted, &$files) {
            foreach ($ids as $id) {
                if ($id <= 0) {
                    continue;
                }
                $product = Product::query()->with('images')->find($id);
                if (! $product) {
                    continue;
                }
                foreach ($product->images as $image) {
                    $files[] = (string) $image->path;
                }
                DB::table('order_items')->where('product_id', $id)->delete();
                DB::table('product_reviews')->where('product_id', $id)->delete();
                DB::table('product_images')->where('product_id', $id)->delete();
                DB::table('product_variants')->where('product_id', $id)->delete();
                $product->delete();
                $deleted++;
            }
        });

        foreach (array_unique($files) as $filename) {
            if ($filename !== '' && ! $this->imageStillUsed($filename, 0)) {
                $this->images->delete($filename);
            }
        }

        return $deleted;
    }

    /**
     * @param  list<string>  $colors
     * @param  list<string>  $sizes
     */
    public function syncVariants(int $productId, array $colors, array $sizes, float $costPrice): void
    {
        $colors = $this->normalizeList($colors);
        $sizes = $this->normalizeList($sizes);
        if ($colors === []) {
            $colors = [''];
        }
        if ($sizes === []) {
            $sizes = [''];
        }

        $minStock = (int) config('stock.default_min_stock', 5);
        $valid = [];

        foreach ($colors as $color) {
            foreach ($sizes as $size) {
                $key = $color . '||' . $size;
                $valid[$key] = true;
                $existing = ProductVariant::query()
                    ->where('product_id', $productId)
                    ->where('color_name', $color)
                    ->where('size_name', $size)
                    ->first();
                if ($existing) {
                    if ((string) $existing->status !== ProductVariant::STATUS_ACTIVE) {
                        $existing->update(['status' => ProductVariant::STATUS_ACTIVE]);
                    }
                    continue;
                }
                ProductVariant::query()->create([
                    'product_id' => $productId,
                    'sku' => $this->generateSku($productId, $color, $size),
                    'color_name' => $color,
                    'size_name' => $size,
                    'cost_price' => max(0, $costPrice),
                    'stock' => 0,
                    'min_stock' => $minStock,
                    'status' => ProductVariant::STATUS_ACTIVE,
                ]);
            }
        }

        $rows = ProductVariant::query()->where('product_id', $productId)->get();
        foreach ($rows as $row) {
            $key = (string) $row->color_name . '||' . (string) $row->size_name;
            if (! isset($valid[$key]) && (string) $row->status === ProductVariant::STATUS_ACTIVE) {
                $row->update(['status' => ProductVariant::STATUS_INACTIVE]);
            }
        }
    }

    /** @param  array<int|string, mixed>  $raw */
    private function saveVariantEdits(int $productId, array $raw): void
    {
        foreach ($raw as $variantId => $fields) {
            $variantId = (int) $variantId;
            if ($variantId <= 0 || ! is_array($fields)) {
                continue;
            }
            $variant = ProductVariant::query()->find($variantId);
            if (! $variant || (int) $variant->product_id !== $productId) {
                continue;
            }
            $update = [];
            if (isset($fields['cost_price'])) {
                $cost = self::parsePrice($fields['cost_price']);
                if ($cost >= 0) {
                    $update['cost_price'] = $cost;
                }
            }
            if (isset($fields['min_stock'])) {
                $update['min_stock'] = max(0, (int) $fields['min_stock']);
            }
            if (isset($fields['status'])) {
                $status = (string) $fields['status'];
                $update['status'] = in_array($status, ['0', 'inactive'], true)
                    ? ProductVariant::STATUS_INACTIVE
                    : ProductVariant::STATUS_ACTIVE;
            }
            if ($update !== []) {
                $variant->update($update);
            }
        }
    }

    private function generateSku(int $productId, string $color, string $size): string
    {
        $slug = function (string $text): string {
            $text = trim($text);
            if ($text === '') {
                return 'DF';
            }
            $text = preg_replace('/[^A-Za-z0-9]+/', '-', $text) ?? '';
            $text = strtoupper(trim($text, '-'));

            return $text !== '' ? substr($text, 0, 12) : 'DF';
        };
        $base = 'SP' . str_pad((string) $productId, 5, '0', STR_PAD_LEFT) . '-' . $slug($color) . '-' . $slug($size);
        $sku = $base;
        $n = 1;
        while (ProductVariant::query()->where('sku', $sku)->exists()) {
            $n++;
            $sku = $base . '-' . $n;
        }

        return $sku;
    }

    /** @param  mixed  $values @return list<string> */
    private function normalizeList(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }
        $out = [];
        foreach ($values as $value) {
            $value = trim((string) $value);
            if ($value !== '' && ! in_array($value, $out, true)) {
                $out[] = $value;
            }
        }

        return $out;
    }

    /** @param  list<string>  $values */
    private function csv(array $values): ?string
    {
        $values = array_values(array_filter($values, fn ($value) => $value !== ''));

        return $values === [] ? null : implode(',', $values);
    }

    private function imageStillUsed(string $filename, int $exceptProductId): bool
    {
        $filename = $this->images->sanitize($filename);
        if ($filename === '') {
            return false;
        }

        return DB::table('product_images')
            ->where('product_id', '!=', $exceptProductId)
            ->where('path', $filename)
            ->exists();
    }

    /**
     * @param  list<string>  $gallery
     */
    private function replaceImages(int $productId, string $main, array $gallery): void
    {
        ProductImage::query()->where('product_id', $productId)->whereNull('variant_id')->delete();
        $sort = 0;
        if ($main !== '') {
            ProductImage::query()->create([
                'product_id' => $productId,
                'variant_id' => null,
                'path' => $main,
                'sort_order' => $sort,
                'is_primary' => 1,
            ]);
            $sort++;
        }
        foreach ($gallery as $path) {
            $path = (string) $path;
            if ($path === '' || $path === $main) {
                continue;
            }
            ProductImage::query()->create([
                'product_id' => $productId,
                'variant_id' => null,
                'path' => $path,
                'sort_order' => $sort,
                'is_primary' => 0,
            ]);
            $sort++;
        }
    }
}
