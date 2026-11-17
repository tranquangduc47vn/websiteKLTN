<?php

namespace App\Support\Storefront;

use App\Models\Product;
use App\Support\Storefront\Product as ProductPresenter;

/**
 * Dữ liệu thẻ sản phẩm cho widget chat (ảnh và giá thật từ DB).
 */
final class AiProductCards
{
    /** @var list<string> */
    private const TRIGGERS = [
        'tìm sản phẩm', 'tìm kiếm', 'tìm', 'sản phẩm', 'áo', 'quần', 'đầm', 'váy',
        'giày', 'túi', 'mua', 'khuyến mãi', 'bán chạy', 'giá', 'có gì',
    ];

    /**
     * @return list<array<string, mixed>>
     */
    public static function searchForChat(string $message, int $limit = 4): array
    {
        if (! self::shouldSuggestProducts($message)) {
            return [];
        }

        $limit = max(1, min(6, $limit));
        $keyword = self::extractKeyword($message);

        $query = Product::query()->active()->with([
            'images' => function ($q): void {
                $q->whereNull('variant_id')->orderByDesc('is_primary')->orderBy('sort_order');
            },
            'variants' => function ($q): void {
                $q->where('status', 'active')->orderBy('color_name')->orderBy('size_name');
            },
        ]);

        if ($keyword !== '') {
            $query->search($keyword, true);
        } else {
            $query->hot();
        }

        $rows = $query->limit($limit)->get();

        return $rows->map(fn (Product $product) => self::cardFromProduct($product))->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    public static function cardFromProduct(Product $product): array
    {
        $list = (float) $product->list_price;
        $final = $product->finalPrice();
        $hasDiscount = $final < $list && $list > 0;
        $stock = $product->totalStock();

        $colors = $product->variants
            ->pluck('color_name')
            ->filter(fn ($c) => trim((string) $c) !== '')
            ->unique()
            ->values()
            ->all();
        $sizes = $product->variants
            ->pluck('size_name')
            ->filter(fn ($s) => trim((string) $s) !== '')
            ->unique()
            ->values()
            ->all();

        $imageUrl = ProductPresenter::imageUrl($product->image_link);

        return [
            'id' => (int) $product->id,
            'name' => ProductPresenter::displayName((string) $product->name),
            'url' => Urls::product($product),
            'image_url' => $imageUrl,
            'price_fmt' => self::formatMoney($final),
            'list_price_fmt' => $hasDiscount ? self::formatMoney($list) : '',
            'colors' => implode(', ', $colors),
            'sizes' => implode(', ', $sizes),
            'in_stock' => $stock > 0,
            'buyable' => $stock > 0,
            'stock_label' => $stock > 0 ? 'Còn hàng' : 'Hết hàng',
        ];
    }

    private static function shouldSuggestProducts(string $message): bool
    {
        $haystack = mb_strtolower(trim($message), 'UTF-8');
        if ($haystack === '') {
            return false;
        }
        foreach (self::TRIGGERS as $needle) {
            if (mb_strpos($haystack, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    private static function extractKeyword(string $message): string
    {
        $text = trim($message);
        $lower = mb_strtolower($text, 'UTF-8');
        foreach (['tìm sản phẩm', 'tìm kiếm', 'tìm'] as $prefix) {
            if (mb_strpos($lower, $prefix) === 0) {
                $text = trim(mb_substr($text, mb_strlen($prefix)));
                break;
            }
        }
        $stop = ['sản phẩm', 'giúp', 'cho', 'mình', 'với', 'là', 'có', 'không', 'ạ', 'nhé', '?', '!'];
        $words = preg_split('/\s+/u', $text) ?: [];
        $filtered = [];
        foreach ($words as $word) {
            $w = mb_strtolower(trim($word, ".,;:!?\"'"), 'UTF-8');
            if ($w === '' || in_array($w, $stop, true) || mb_strlen($w) < 2) {
                continue;
            }
            $filtered[] = $word;
        }

        return trim(implode(' ', array_slice($filtered, 0, 8)));
    }

    private static function formatMoney(float $amount): string
    {
        return number_format(max(0, $amount), 0, ',', '.') . 'đ';
    }
}