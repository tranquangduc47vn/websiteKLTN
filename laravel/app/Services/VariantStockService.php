<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Collection;

/**
 * Kiểm tra tồn kho theo biến thể (màu × size) cho giỏ hàng.
 *
 * Port từ CI3 application/libraries/Product_service.php:
 *   parse_product_attributes(), resolve_variant_row(), validate_cart_quantity(),
 *   variant_stock_message().
 *
 * Cart CI3 luôn gọi với $auto_create = false nên không port sync_variants().
 * Mảng kết quả giữ đúng shape CI3 (ok, message, variant_id, stock, color, size)
 * vì payload JSON của giỏ hàng trả thẳng các key này cho frontend.
 */
class VariantStockService
{
    public const MSG_VARIANT_UNAVAILABLE = 'Biến thể sản phẩm không tồn tại hoặc đã ngừng kinh doanh.';

    /**
     * CI3 parse_product_attributes(): CSV → danh sách duy nhất, rỗng thì [''].
     *
     * @return array{colors: list<string>, sizes: list<string>}
     */
    public function attributes(Product $product): array
    {
        $colors = $product->colorList();
        $sizes = $product->sizeList();

        return [
            'colors' => $colors !== [] ? $colors : [''],
            'sizes' => $sizes !== [] ? $sizes : [''],
        ];
    }

    /**
     * CI3 resolve_variant_row(): chuẩn hóa màu/size theo danh mục của sản phẩm rồi tìm
     * biến thể đang bán (status = 1).
     *
     * @param  Collection<int, ProductVariant>|null  $preloaded  biến thể của sản phẩm đã nạp sẵn
     * @return array{variant_id: int, stock: int, color: string, size: string, sku: string}|null
     */
    public function resolveVariant(Product $product, string $color, string $size, ?Collection $preloaded = null): ?array
    {
        $attrs = $this->attributes($product);
        $color = trim($color);
        $size = trim($size);

        if ($color === '' || ! in_array($color, $attrs['colors'], true)) {
            $color = $attrs['colors'][0];
        }
        if ($size === '' || ! in_array($size, $attrs['sizes'], true)) {
            $size = $attrs['sizes'][0];
        }

        $row = $preloaded?->first(
            fn (ProductVariant $v) => $v->color === $color && $v->size === $size
        );

        // So khớp chính xác trên bộ nạp sẵn; nếu trượt thì để MySQL so theo collation như CI3.
        $row ??= ProductVariant::query()
            ->where('product_id', (int) $product->id)
            ->where('color_name', $color)
            ->where('size_name', $size)
            ->first();

        if (! $row || (string) $row->status !== ProductVariant::STATUS_ACTIVE) {
            return null;
        }

        return [
            'variant_id' => (int) $row->id,
            'stock' => (int) $row->stock,
            'color' => (string) $row->color,
            'size' => (string) $row->size,
            'sku' => (string) $row->sku,
        ];
    }

    /**
     * CI3 validate_cart_quantity() khi biến thể đã được resolve.
     *
     * @param  array{variant_id: int, stock: int, color: string, size: string}|null  $variant
     * @return array{ok: bool, message: string, variant_id: int, stock: int, color: string, size: string}
     */
    public function checkQuantity(?Product $product, ?array $variant, string $color, string $size, int $qty, int $existingInCart = 0): array
    {
        $qty = max(0, $qty);
        $existingInCart = max(0, $existingInCart);

        if (! $variant) {
            return [
                'ok' => false,
                'message' => self::MSG_VARIANT_UNAVAILABLE,
                'variant_id' => 0,
                'stock' => 0,
                'color' => trim($color),
                'size' => trim($size),
            ];
        }

        if ($existingInCart + $qty > $variant['stock']) {
            return [
                'ok' => false,
                'message' => $this->stockMessage(
                    $product ? (string) $product->name : 'Sản phẩm',
                    $variant['size'],
                    $variant['color'],
                    $variant['stock']
                ),
                'variant_id' => $variant['variant_id'],
                'stock' => $variant['stock'],
                'color' => $variant['color'],
                'size' => $variant['size'],
            ];
        }

        return [
            'ok' => true,
            'message' => '',
            'variant_id' => $variant['variant_id'],
            'stock' => $variant['stock'],
            'color' => $variant['color'],
            'size' => $variant['size'],
        ];
    }

    /**
     * CI3 validate_cart_quantity($product_id, $color, $size, $qty, $existing, false).
     *
     * @param  Collection<int, ProductVariant>|null  $preloaded
     * @return array{ok: bool, message: string, variant_id: int, stock: int, color: string, size: string}
     */
    public function validateCartQuantity(?Product $product, string $color, string $size, int $qty, int $existingInCart = 0, ?Collection $preloaded = null): array
    {
        $variant = $product ? $this->resolveVariant($product, $color, $size, $preloaded) : null;

        return $this->checkQuantity($product, $variant, $color, $size, $qty, $existingInCart);
    }

    /** CI3 variant_stock_message(). */
    public function stockMessage(string $productName, string $size, string $color, int $stock): string
    {
        return trim($productName) . ' size ' . trim($size) . ' màu ' . trim($color)
            . ' chỉ còn ' . $stock . ' sản phẩm trong kho.';
    }

    /**
     * CI3 normalize_list(): trim, bỏ rỗng, bỏ trùng (giữ thứ tự).
     *
     * @param  array<int, mixed>  $values
     * @return list<string>
     */
    public static function normalizeList(array $values): array
    {
        $out = [];

        foreach ($values as $value) {
            $value = trim((string) $value);
            if ($value !== '' && ! in_array($value, $out, true)) {
                $out[] = $value;
            }
        }

        return $out;
    }
}
