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
            'password' => LegacyPassword::hash(self::PASSWORD),
            'phone' => '0900000000',
            'address' => 'Test',
            'created' => time(),
        ]);
        $this->createdUserIds[] = (int) $user->id;

        return $user;
    }

    protected function tearDown(): void
    {
        // user_address / user_phone là MyISAM — DatabaseTransactions không rollback.
        if ($this->createdUserIds !== []) {
            DB::table('user_address')->whereIn('user_id', $this->createdUserIds)->delete();
            DB::table('user_phone')->whereIn('user_id', $this->createdUserIds)->delete();
        }

        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<array{0: string, 1: string, 2: int, 3?: int}>  $variants  [color, size, stock, status]
     */
    protected function makeProduct(array $attributes = [], array $variants = []): Product
    {
        $product = Product::query()->create(array_merge([
            'catalog_id' => (int) DB::table('catalog')->orderBy('id')->value('id'),
            'name' => 'B5 Test Áo Cart ' . Str::random(6),
            'content' => '',
            'color' => 'Đen,Trắng',
            'size' => 'S,M',
            'price' => 300000,
            'discount' => 50000,
            'discount_type' => 'percent',
            'discount_percent' => 17,
            'image_link' => 'b5-test.jpg',
            'image_list' => '[]',
            'view' => 0,
            'buyed' => 0,
            'quantity' => 0,
            'created' => time(),
            'code' => '',
            'status' => 1,
        ], $attributes));

        foreach ($variants as $i => $variant) {
            ProductVariant::query()->create([
                'product_id' => $product->id,
                'sku' => 'B5T-' . $product->id . '-' . $i . '-' . Str::random(4),
                'color' => $variant[0],
                'size' => $variant[1],
                'price' => $product->price,
                'cost_price' => 0,
                'stock' => $variant[2],
                'min_stock' => 5,
                'image' => '',
                'status' => $variant[3] ?? 1,
                'created' => time(),
                'updated' => time(),
            ]);
        }

        return $product->refresh();
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
            ->where('color', $color)
            ->where('size', $size)
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
        $json = DB::table('user_saved_cart')->where('user_id', $user->id)->value('cart_json');

        return $json === null ? null : json_decode((string) $json, true);
    }

    protected function addToCart(Product $product, array $input = []): \Illuminate\Testing\TestResponse
    {
        return $this->post('/gio-hang/them/' . $product->id, $input);
    }
}
