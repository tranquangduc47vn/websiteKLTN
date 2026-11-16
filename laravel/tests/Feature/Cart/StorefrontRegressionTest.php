<?php

namespace Tests\Feature\Cart;

use App\Models\Catalog;
use App\Support\Storefront\Urls;

/**
 * Regression B5: các trang đã migrate (Home / Product / Auth / Cart) vẫn render với cả khách
 * và user đăng nhập (layout chạy badge đơn hàng + giỏ hàng từ custom_cart).
 */
class StorefrontRegressionTest extends CartTestCase
{
    /**
     * @return list<string>
     */
    private function pages(): array
    {
        $product = $this->standardProduct();
        $catalog = Catalog::query()->findOrFail($product->catalog_id);

        return [
            '/',
            '/ban-chay',
            '/moi',
            '/khuyen-mai',
            '/tim-kiem/ao',
            Urls::category($catalog),
            Urls::product($product),
        ];
    }

    public function test_storefront_pages_render_for_guest(): void
    {
        foreach ($this->pages() as $url) {
            $this->get($url)->assertOk();
        }

        $this->get('/dang-nhap')->assertOk()->assertSee('name="_token"', false);
        $this->get('/dang-ky')->assertOk()->assertSee('name="_token"', false);

        $this->withSession([
            'user' => (object) ['id' => 10, 'name' => 'Fake Legacy User', 'email' => 'fake-legacy@example.test'],
        ])->get('/')->assertOk()->assertDontSee('Fake Legacy User');
        $this->assertFalse(auth('web')->check());

        $this->get('/')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeaderMissing('Content-Security-Policy')
            ->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_storefront_pages_render_for_logged_in_user_with_cart(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user, 'web');
        $product = $this->standardProduct();
        $this->addToCart($product, ['size' => 'S', 'color' => 'Đen', 'qty' => 2])->assertRedirect(url('gio-hang'));

        foreach ([...$this->pages(), '/gio-hang', '/user'] as $url) {
            $this->get($url)->assertOk();
        }

        $this->assertSame(2, array_sum(array_column($this->sessionCart(), 'qty')));
    }

    public function test_order_badge_counts_only_open_orders_of_current_user(): void
    {
        $user = $this->makeUser();
        $other = $this->makeUser();
        $base = ['subtotal' => 100000, 'discount_amount' => 0, 'shipping_fee' => 0, 'total' => 100000, 'note' => '', 'shipping_address' => 'Test', 'created_at' => now(), 'updated_at' => now()];
        $labels = [0 => 'pending', 1 => 'confirmed', 2 => 'packed', 3 => 'completed', 4 => 'cancelled'];
        $insert = fn (int $uid, int $status) => \Illuminate\Support\Facades\DB::table('orders')->insert(array_merge($base, [
            'user_id' => $uid,
            'customer_name' => 'B5',
            'customer_email' => 'b5badge@example.test',
            'customer_phone' => '0900000000',
            'status' => $labels[$status] ?? 'pending',
        ]));
        $insert($user->id, 0);
        $insert($user->id, 2);
        $insert($user->id, 3);
        $insert($other->id, 1);

        $this->actingAs($user, 'web');
        $this->get('/')
            ->assertOk()
            ->assertSee('Tài khoản — 2 đơn đang xử lý', false)
            ->assertSee('<span class="jm-icon-dot wf-chrome-order-badge">2</span>', false);
    }
}
