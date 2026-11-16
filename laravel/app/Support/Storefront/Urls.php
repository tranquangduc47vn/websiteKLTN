<?php

namespace App\Support\Storefront;

use App\Models\Catalog;

/**
 * B4 - Sinh URL SEO, port nguyên văn từ CI3 seo_url_helper.php:
 *   build_category_url(), build_product_url(), build_search_url(),
 *   build_news_index_url(), build_news_post_url(), build_cart_url(),
 *   build_checkout_url(), build_login_url(), build_register_url()
 *
 * Tất cả giữ đúng path + phân đoạn để URL cũ không vỡ link/SEO.
 */
class Urls
{
    /**
     * Slug danh mục trùng route chức năng. Dữ liệu tên trong DB không đổi.
     * URL danh mục của các slug này là /danh-muc/{slug}.
     *
     * @var list<string>
     */
    public const RESERVED_CATALOG_SLUGS = [
        'ban-chay',
        'khuyen-mai',
        'tin-tuc',
        'gio-hang',
        'moi',
        'tim-kiem',
        'thanh-toan',
        'dang-nhap',
        'dang-ky',
        'quen-mat-khau',
        'dat-lai-mat-khau',
        'user',
        'admin',
        'cart',
        'order',
        'news',
        'posts',
        'product',
        'tich-diem',
        'danh-muc',
    ];

    public static function isReservedCatalogSlug(string $slug): bool
    {
        return in_array($slug, self::RESERVED_CATALOG_SLUGS, true);
    }

    /** CI3 build_cart_url(). */
    public static function cart(): string
    {
        return url('gio-hang');
    }

    /** CI3 build_checkout_url(). */
    public static function checkout(): string
    {
        return url('thanh-toan');
    }

    /** CI3 build_checkout_qr_url(). */
    public static function checkoutQr(int $transactionId): string
    {
        return url('thanh-toan/checkout_qr/' . $transactionId);
    }

    /** Trang tài khoản CI3 `user` (B3 còn stub 501). */
    public static function account(): string
    {
        return url('user');
    }

    /** CI3 build_login_url(). */
    public static function login(): string
    {
        return url('dang-nhap');
    }

    /** CI3 build_register_url(). */
    public static function register(): string
    {
        return url('dang-ky');
    }

    /** CI3 seo_catalog_slug(). */
    protected static function catalogSlug(mixed $catalog): string
    {
        if (is_object($catalog) && isset($catalog->name)) {
            return Slug::make($catalog->name);
        }
        if (is_array($catalog) && isset($catalog['name'])) {
            return Slug::make($catalog['name']);
        }

        return '';
    }

    /** CI3 build_category_url(): /<slug>[/<price_slug>][/<page>]. */
    public static function category(mixed $catalog, mixed $priceRange = null, int $page = 0): string
    {
        if (is_numeric($catalog)) {
            $catalog = Catalog::query()->find((int) $catalog);
        }

        if (empty($catalog) || ! is_object($catalog)) {
            return url('/');
        }

        $slug = self::catalogSlug($catalog);
        if ($slug === '') {
            return url('product/catalog/' . (int) $catalog->id);
        }

        $parts = [];
        if (self::isReservedCatalogSlug($slug)) {
            $parts[] = 'danh-muc';
        }
        $parts[] = $slug;

        if ($priceRange !== null && $priceRange !== '') {
            $ps = self::priceRangeToSlug($priceRange);
            if ($ps === '' && isset(PriceFilters::map()[$priceRange])) {
                $ps = $priceRange;
            }
            if ($ps !== '') {
                $parts[] = $ps;
            }
        }

        if ($page > 1) {
            $parts[] = $page;
        }

        return url(implode('/', $parts));
    }

    /** CI3 build_product_url(): /<slug>-p<id>. */
    public static function product(mixed $product): string
    {
        if (is_numeric($product)) {
            $product = \App\Models\Product::query()->find((int) $product);
        }

        if (empty($product) || ! is_object($product)) {
            return url('/');
        }

        $slug = Slug::make($product->name);

        return url(($slug !== '' ? $slug : 'san-pham') . '-p' . (int) $product->id);
    }

    /** CI3 build_search_url(): /tim-kiem[/<slug>[/<page>]]. */
    public static function search(string $keyword, int $page = 0): string
    {
        $keyword = trim($keyword);

        if ($keyword === '') {
            return url('tim-kiem');
        }

        $slug = Slug::make($keyword);
        $path = 'tim-kiem/' . ($slug !== '' ? $slug : 'tim-kiem');

        if ($page > 1) {
            $path .= '/' . $page;
        }

        return url($path);
    }

    /** CI3 seo_search_keyword_from_slug(): slug -> tu khoa. */
    public static function keywordFromSlug(string $slug): string
    {
        $slug = rawurldecode((string) $slug);

        return trim(str_replace('-', ' ', $slug));
    }

    /** CI3 build_news_index_url(): /tin-tuc[/<page>]. */
    public static function newsIndex(int $page = 0): string
    {
        return url($page > 1 ? 'tin-tuc/' . $page : 'tin-tuc');
    }

    /** CI3 build_news_post_url(): /tin-tuc/<slug>-n<id>. */
    public static function newsPost(mixed $post): string
    {
        if (is_numeric($post)) {
            $post = \App\Models\ContentPage::query()->find((int) $post);
        }

        if (empty($post) || ! is_object($post)) {
            return url('tin-tuc');
        }

        $title = isset($post->title) ? $post->title : (isset($post->name) ? $post->name : '');
        $slug = Slug::make($title);

        return url('tin-tuc/' . ($slug !== '' ? $slug : 'bai-viet') . '-n' . (int) $post->id);
    }

    protected static function priceRangeToSlug(mixed $range): string
    {
        if ($range === null || $range === '') {
            return '';
        }

        return (string) array_search($range, PriceFilters::map(), true) ?: '';
    }
}
