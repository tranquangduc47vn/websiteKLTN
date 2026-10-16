<?php

namespace App\Services\Cart;

use App\Models\Product;

/**
 * Kết quả một thao tác giỏ hàng (add / updateOptions / changeQuantity / remove).
 *
 * `data` là phần "extra" trong payload JSON của CI3 (action, rowid, qty, subtotal...)
 * và được controller nối nguyên vẹn vào response.
 */
final class CartActionResult
{
    public const OK = 'ok';
    public const PRODUCT_MISSING = 'product_missing';
    public const PRODUCT_INACTIVE = 'product_inactive';
    public const OUT_OF_STOCK = 'out_of_stock';
    public const NOT_FOUND = 'not_found';
    public const INVALID = 'invalid';

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, array<string, mixed>>  $cart  giỏ hàng sau thao tác
     */
    private function __construct(
        public readonly bool $ok,
        public readonly string $code,
        public readonly string $message,
        public readonly array $data,
        public readonly array $cart,
        public readonly ?Product $product,
    ) {
    }

    /**
     * @param  array<string, array<string, mixed>>  $cart
     * @param  array<string, mixed>  $data
     */
    public static function success(array $cart, string $message = '', array $data = [], ?Product $product = null): self
    {
        return new self(true, self::OK, $message, $data, $cart, $product);
    }

    /**
     * @param  array<string, array<string, mixed>>  $cart
     * @param  array<string, mixed>  $data
     */
    public static function failure(string $code, string $message, array $cart, array $data = [], ?Product $product = null): self
    {
        return new self(false, $code, $message, $data, $cart, $product);
    }
}
