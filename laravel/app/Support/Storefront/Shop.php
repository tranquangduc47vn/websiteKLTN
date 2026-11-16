<?php

namespace App\Support\Storefront;

/**
 * B4 - Tiện ích site/URL, port nguyên văn từ CI3
 * application/helpers/common_helper.php + seo_url_helper.php + config/config.php:
 *   site_asset_url(), upload_image_url(), base_url(), site_url()
 *   shop_name(), site_logo_url()
 *
 * Laravel dùng url()/URL::to() => kết quả tương đương CI3 base_url()/site_url()
 * khi APP_URL là root domain (CI3 base_url = protocol://host/scriptname/,
 * site_url = base_url + uri_string). Không còn index.php trong URL.
 */
class Shop
{
    /** Tên shop — CI3: config SHOP_NAME (branding), fallback 'qD Design'. */
    public static function name(bool $upper = false): string
    {
        $name = (string) (config('shop.name') ?: 'qD Design');

        return $upper ? (string) (config('shop.name_upper') ?: mb_strtoupper($name, 'UTF-8')) : $name;
    }

    /** CI3 base_url(). */
    public static function baseUrl(string $path = ''): string
    {
        return url($path);
    }

    /** CI3 site_asset_url(): assets/site/... View nối thêm css/ nên URL rỗng phải có dấu / cuối. */
    public static function assetUrl(string $path = ''): string
    {
        $url = url('assets/site/' . ltrim($path, '/'));
        if ($path === '' && ! str_ends_with($url, '/')) {
            $url .= '/';
        }

        return $url;
    }

    /** CI3 upload_image_url(). */
    public static function uploadImageUrl(mixed $filename, string $subdir = 'product'): string
    {
        $filename = trim((string) $filename);

        if ($filename === '') {
            return self::assetUrl('images/no-product.svg');
        }

        $subdir = trim(str_replace('\\', '/', $subdir), '/');
        $filename = str_replace('\\', '/', $filename);
        $encoded = implode('/', array_map('rawurlencode', explode('/', $filename)));

        return url('upload/' . $subdir . '/' . $encoded);
    }

    /** CI3 product_image_url(). */
    public static function logoUrl(): string
    {
        return url('upload/' . ltrim(str_replace('\\', '/', (string) (config('shop.logo') ?: 'logo.png')), '/'));
    }
}
