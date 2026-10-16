<?php

namespace App\Support\Storefront;

/**
 * B4 - Hàm hiển thị sản phẩm, port nguyên văn từ CI3
 * application/helpers/common_helper.php:
 *   product_display_name(), product_image_url(), product_has_discount(),
 *   product_sale_price(), product_discount_percent(), product_show_discount_badge(),
 *   product_discount_badge_html(), product_is_in_stock(),
 *   product_out_of_stock_badge_html(), product_rating_average(),
 *   product_catalog_stars_html()
 *
 * Nhận mixed (App\Models\Product hoặc stdClass từ query builder) để tương thích cả hai.
 */
class Product
{
    /** product_display_name(): Title-case tên sản phẩm (UTF-8). */
    public static function displayName(mixed $name): string
    {
        $name = trim((string) $name);

        if ($name === '') {
            return '';
        }

        $parts = preg_split('/(\s+)/u', $name, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
        $out = '';

        foreach ($parts as $part) {
            if ($part === '' || preg_match('/^\s+$/u', $part)) {
                $out .= $part;
                continue;
            }

            $first = mb_strtoupper(mb_substr($part, 0, 1, 'UTF-8'), 'UTF-8');
            $rest = mb_strlen($part, 'UTF-8') > 1
                ? mb_strtolower(mb_substr($part, 1, null, 'UTF-8'), 'UTF-8')
                : '';
            $out .= $first . $rest;
        }

        return $out;
    }

    /** product_image_url(): ảnh sản phẩm → upload/product/<rawurlencode>. */
    public static function imageUrl(mixed $filename): string
    {
        return Shop::uploadImageUrl($filename, 'product');
    }

    /** product_has_discount(). */
    public static function hasDiscount(mixed $item): bool
    {
        if (! $item || ! isset($item->price, $item->discount)) {
            return false;
        }

        return (int) $item->price > 0 && (int) $item->discount > 0;
    }

    /** product_sale_price(). */
    public static function salePrice(mixed $item): int
    {
        if (! $item) {
            return 0;
        }

        return max(0, (int) $item->price - (int) $item->discount);
    }

    /** product_discount_percent(): % giảm (hỗ trợ amount + percent). */
    public static function discountPercent(mixed $item): int
    {
        if (! self::hasDiscount($item)) {
            return 0;
        }

        if (isset($item->discount_type) && $item->discount_type === 'percent') {
            $pct = isset($item->discount_percent) ? (int) $item->discount_percent : 0;
            if ($pct > 0) {
                return min(99, $pct);
            }
        }

        return min(99, max(1, (int) round(((int) $item->discount / (int) $item->price) * 100)));
    }

    /** product_show_discount_badge(). */
    public static function showDiscountBadge(mixed $item): bool
    {
        return self::discountPercent($item) >= 1;
    }

    /** product_is_in_stock(): dựa trên product.quantity (cache tổng tồn). */
    public static function isInStock(mixed $item): bool
    {
        if (empty($item)) {
            return false;
        }

        return (int) (isset($item->quantity) ? $item->quantity : 0) > 0;
    }

    /** product_rating_average(). */
    public static function ratingAverage(mixed $item): float
    {
        if (! $item) {
            return 0.0;
        }

        $count = isset($item->rate_count) ? (int) $item->rate_count : 0;
        $total = isset($item->rate_total) ? (int) $item->rate_total : 0;

        return $count <= 0 ? 0.0 : round($total / $count, 1);
    }

    /** product_catalog_stars_html(): khối sao đánh giá (2 lớp ★ xếp chồng + % width). */
    public static function catalogStarsHtml(mixed $item): string
    {
        $count = ($item && isset($item->rate_count)) ? (int) $item->rate_count : 0;
        $avg = self::ratingAverage($item);
        $pct = ($count > 0) ? min(100, max(0, ($avg / 5) * 100)) : 0;
        $label = ($count > 0)
            ? ('Đánh giá trung bình ' . number_format($avg, 1, '.', '') . ' / 5')
            : 'Chưa có đánh giá';

        return '<span class="jm-stars-wrap" aria-label="' . e($label) . '">'
            . '<span class="jm-stars jm-stars--bg" aria-hidden="true">★★★★★</span>'
            . '<span class="jm-stars jm-stars--fg" style="width:' . $pct . '%" aria-hidden="true">★★★★★</span>'
            . '</span>';
    }

    /** product_discount_badge_html(): badge "−X%". */
    public static function discountBadgeHtml(mixed $item, string $class = 'jm-badge-discount'): string
    {
        if (! self::showDiscountBadge($item)) {
            return '';
        }

        $label = '−' . self::discountPercent($item) . '%';

        return '<span class="' . e($class) . '">' . $label . '</span>';
    }

    /** product_out_of_stock_badge_html(). */
    public static function outOfStockBadgeHtml(string $class = 'jm-badge-out-of-stock'): string
    {
        return '<span class="' . e($class) . '">Hết hàng</span>';
    }
}
