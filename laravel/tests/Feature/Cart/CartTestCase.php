<?php

namespace Tests\Feature\Cart;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Support\LegacyPassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\LegacyMysqlTestCase;

/**
 * Fixture cho test giỏ hàng: user / sản phẩm / biến thể được tạo trong transaction của test
 * (rollback sau mỗi test) — không chạm dữ liệu thật.
 */
abstract class CartTestCase extends LegacyMysqlTestCase
{
    protected const PASSWORD = 'B5-Cart-Secret#2026';

    protected const AJAX = ['X-Requested-With' => 'XMLHttpRequest'];

    /** @var list<int> */
    private array $createdUserIds = [];

    protected function makeUser(): User
    {
        $user = User::query()->create([
            'name' => 'B5 Cart Tester',
            'email' => 'b5cart.' . Str::lower(Str::random(10)) . '@example.test',
            'password_hash' => LegacyPassword::hash(self::PASSWORD),
            'status' => 'active',
        ]);
        DB::table('user_phones')->insert([
            'user_id' => $user->id,
            'label' => '',
            'phone_number' => '0900000000',
            'is_default' => 1,
            'created_at' => now(),
        ]);
        DB::table('user_addresses')->insert([
            'user_id' => $user->id,
            'note' => 'Test',
            'province_id' => '01',
            'district_id' => '001',
            'ward_id' => '00001',
            'address_line' => 'Test',
            'is_default' => 1,
            'created_at' => now(),
        ]);
        $this->createdUserIds[] = (int) $user->id;

        return $user;
    }

    protected function tearDown(): void
    {
        // user_address / user_phone là MyISAM — DatabaseTransactions không rollback.
        if ($this->createdUserIds !== []) {
            DB::table('user_addresses')->whereIn('user_id', $this->createdUserIds)->delete();
            DB::table('user_phones')->whereIn('user_id', $this->createdUserIds)->delete();
        }

        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<array{0: string, 1: string, 2: int, 3?: int}>  $variants  [color, size, stock, status]
     */
    protected function makeProduct(array $attributes = [], array $variants = []): Product
    {
        $status = $attributes['status'] ?? 'active';
        $image = (string) ($attributes['image_link'] ?? 'b5-test.jpg');
        $listPrice = $attributes['list_price'] ?? $attributes['price'] ?? 300000;
        $discountAmount = $attributes['discount_amount'] ?? $attributes['discount'] ?? 50000;
        $categoryId = $attributes['category_id'] ?? $attributes['catalog_id'] ?? (int) DB::table('categories')->orderBy('id')->value('id');
        foreach (['status', 'image_link', 'image_list', 'list_price', 'price', 'discount_amount', 'discount', 'discount_type', 'discount_percent', 'category_id', 'catalog_id', 'color', 'size', 'view', 'buyed', 'quantity', 'created', 'rating_total', 'rating_count'] as $drop) {
            unset($attributes[$drop]);
        }

        $product = Product::query()->create(array_merge([
            'category_id' => (int) $categoryId,
            'name' => 'B5 Test Áo Cart ' . Str::random(6),
            'content' => '',
            'list_price' => $listPrice,
            'discount_amount' => $discountAmount,
            'code' => 'B5' . strtoupper(Str::random(8)),
            'status' => $this->activeStatus($status),
            'view_count' => 0,
            'sold_count' => 0,
            'rating_total' => 0,
            'rating_count' => 0,
        ], $attributes));

        if ($image !== '') {
            DB::table('product_images')->insert([
                'product_id' => $product->id,
                'variant_id' => null,
                'path' => $image,
                'sort_order' => 0,
                'is_primary' => 1,
            ]);
        }

        foreach ($variants as $i => $variant) {
            ProductVariant::query()->create([
                'product_id' => $product->id,
                'sku' => 'B5T-' . $product->id . '-' . $i . '-' . Str::random(4),
                'color_name' => $variant[0],
                'size_name' => $variant[1],
                'cost_price' => 0,
                'stock' => $variant[2],
                'min_stock' => 5,
                'status' => $this->activeStatus($variant[3] ?? 'active'),
            ]);
        }

        return $product->refresh();
    }

    private function activeStatus(mixed $status): string
    {
        return ($status === 0 || $status === '0' || $status === 'inactive') ? 'inactive' : 'active';
    }

    /** Sản phẩm chuẩn: Đen/S=5, Đen/M=1, Trắng/S=0, Trắng/M ngừng bán. */
    protected function standardProduct(array $attributes = []): Product
    {
        return $this->makeProduct($attributes, [
            ['Đen', 'S', 5],
            ['Đen', 'M', 1],
            ['Trắng', 'S', 0],
            ['Trắng', 'M', 10, 0],
        ]);
    }

    protected function variantId(Product $product, string $color, string $size): int
    {
        return (int) ProductVariant::query()
            ->where('product_id', $product->id)
            ->where('color_name', $color)
            ->where('size_name', $size)
            ->value('id');
    }

    protected function rowId(Product $product, string $size, string $color): string
    {
        return $product->id . '_' . md5($size . '_' . $color);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function sessionCart(): array
    {
        $cart = session('custom_cart');

        return is_array($cart) ? $cart : [];
    }

    /**
     * @return array<string, array<string, mixed>>|null
     */
    protected function savedCart(User $user): ?array
    {
        $json = DB::table('user_saved_carts')->where('user_id', $user->id)->value('cart_json');

        return $json === null ? null : json_decode((string) $json, true);
    }

    protected function addToCart(Product $product, array $input = []): \Illuminate\Testing\TestResponse
    {
        return $this->post('/gio-hang/them/' . $product->id, $input);
    }
}
