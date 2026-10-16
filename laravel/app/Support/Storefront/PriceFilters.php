<?php

namespace App\Support\Storefront;

/**
 * B4 - Map slug khoảng giá SEO, port nguyên văn CI3 seo_url_helper.php:
 *   seo_price_slug_map(), seo_price_slug_to_range(), seo_price_range_to_slug()
 *
 * URL cũ phải giữ đúng 4 slug này.
 */
class PriceFilters
{
    /** @return array<string,string> slug => 'min-max' */
    public static function map(): array
    {
        return [
            'duoi-200k' => '0-200000',
            '200k-500k' => '200000-500000',
            '500k-1tr'  => '500000-1000000',
            'tren-1tr'  => '1000000-999999999',
        ];
    }

    /** CI3 seo_price_slug_to_range(). */
    public static function slugToRange(string $slug): string
    {
        return self::map()[$slug] ?? '';
    }

    /** CI3 seo_price_range_to_slug(). */
    public static function rangeToSlug(mixed $priceRange): string
    {
        if ($priceRange === null || $priceRange === '') {
            return '';
        }

        return (string) (array_search($priceRange, self::map(), true) ?: '');
    }

    /**
     * Parse 'min-max' → [min, max] (int). Trả [null, null] nếu không hợp lệ.
     * CI3: explode('-', $price_range), count == 2.
     *
     * @return array{0:int|null,1:int|null}
     */
    public static function parseRange(mixed $priceRange): array
    {
        if ($priceRange === null || $priceRange === '') {
            return [null, null];
        }

        $parts = explode('-', (string) $priceRange);

        return count($parts) === 2 ? [(int) $parts[0], (int) $parts[1]] : [null, null];
    }
}
