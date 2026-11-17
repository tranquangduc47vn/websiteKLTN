<?php

namespace App\Support\Storefront;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\VariantStockService;
use App\Support\Storefront\Product as ProductPresenter;

/**
 * Dữ liệu mua hàng cho widget chat (biến thể thật, nút theo ngữ cảnh).
 */
final class AiChatCommerce
{
    /**
     * @return array<string, mixed>
     */
    public static function productOptions(Product $product): array
    {
        $product->loadMissing([
            'variants' => function ($q): void {
                $q->where('status', ProductVariant::STATUS_ACTIVE)
                    ->orderBy('color_name')
                    ->orderBy('size_name');
            },
        ]);

        /** @var VariantStockService $stock */
        $stock = app(VariantStockService::class);
        $attrs = $stock->attributes($product);

        $variants = [];
        $defaultColor = '';
        $defaultSize = '';

        foreach ($product->variants as $row) {
            if ((string) $row->status !== ProductVariant::STATUS_ACTIVE) {
                continue;
            }
            $color = (string) $row->color_name;
            $size = (string) $row->size_name;
            $inStock = (int) $row->stock > 0;
            $variants[] = [
                'color' => $color,
                'size' => $size,
                'stock' => (int) $row->stock,
                'in_stock' => $inStock,
            ];
            if ($inStock && $defaultColor === '') {
                $defaultColor = $color;
                $defaultSize = $size;
            }
        }

        if ($defaultColor === '' && $variants !== []) {
            $defaultColor = (string) $variants[0]['color'];
            $defaultSize = (string) $variants[0]['size'];
        }

        if ($defaultColor === '' && $attrs['colors'] !== []) {
            $defaultColor = (string) $attrs['colors'][0];
        }
        if ($defaultSize === '' && $attrs['sizes'] !== []) {
            $defaultSize = (string) $attrs['sizes'][0];
        }

        return [
            'product_id' => (int) $product->id,
            'name' => ProductPresenter::displayName((string) $product->name),
            'url' => Urls::product($product),
            'colors' => $attrs['colors'],
            'sizes' => $attrs['sizes'],
            'variants' => $variants,
            'default_color' => $defaultColor,
            'default_size' => $defaultSize,
        ];
    }

    /**
     * Nút hành động theo ý định tin nhắn (không hiển thị dư thừa).
     *
     * @param  list<array<string, mixed>>  $products
     * @return list<array<string, string>>
     */
    public static function actionsForMessage(string $message, array $products): array
    {
        $m = mb_strtolower(trim($message), 'UTF-8');

        if (self::isOrderIntent($m)) {
            return [
                ['kind' => 'link', 'label' => 'Tra cứu đơn hàng', 'url' => url('user')],
            ];
        }

        if (self::isCheckoutIntent($m)) {
            return [
                ['kind' => 'link', 'label' => 'Xem giỏ hàng', 'url' => Urls::cart()],
                ['kind' => 'link', 'label' => 'Tiến hành thanh toán', 'url' => Urls::checkout()],
            ];
        }

        if (self::isSupportIntent($m)) {
            return [
                ['kind' => 'send', 'label' => 'Gặp nhân viên', 'text' => 'Gặp nhân viên'],
            ];
        }

        if ($products !== [] && self::isProductBrowseIntent($m)) {
            return [
                ['kind' => 'send', 'label' => 'Tìm thêm sản phẩm', 'text' => 'Tìm sản phẩm'],
            ];
        }

        return [];
    }

    /**
     * @return list<array<string, string>>
     */
    public static function afterCartAddActions(): array
    {
        return [
            ['kind' => 'link', 'label' => 'Xem giỏ hàng', 'url' => Urls::cart()],
            ['kind' => 'link', 'label' => 'Tiến hành thanh toán', 'url' => Urls::checkout()],
        ];
    }

    private static function isOrderIntent(string $m): bool
    {
        foreach (['tra cứu đơn', 'đơn hàng', 'mã đơn', 'theo dõi đơn', 'đơn của tôi', 'trạng thái đơn'] as $n) {
            if (mb_strpos($m, $n) !== false) {
                return true;
            }
        }

        return false;
    }

    private static function isCheckoutIntent(string $m): bool
    {
        foreach (['thanh toán', 'tiến hành thanh toán', 'checkout', 'cod', 'chuyển khoản', 'quét qr', 'trả tiền'] as $n) {
            if (mb_strpos($m, $n) !== false) {
                return true;
            }
        }

        return false;
    }

    private static function isSupportIntent(string $m): bool
    {
        foreach (['cần hỗ trợ', 'hỗ trợ trực tiếp', 'nói chuyện với nhân viên', 'liên hệ nhân viên'] as $n) {
            if (mb_strpos($m, $n) !== false) {
                return true;
            }
        }

        return false;
    }

    private static function isProductBrowseIntent(string $m): bool
    {
        foreach (['tìm sản phẩm', 'tìm kiếm', 'có gì', 'bán chạy', 'khuyến mãi'] as $n) {
            if (mb_strpos($m, $n) !== false) {
                return true;
            }
        }

        return false;
    }
}