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
        $base = ['amount' => 100000, 'payment' => 'cod', 'message' => '', 'user_address' => 'Test', 'created' => time()];
        $insert = fn (int $uid, int $status) => \Illuminate\Support\Facades\DB::table('transaction')->insert(array_merge($base, [
            'user_id' => $uid,
            'user_name' => 'B5',
            'user_email' => 'b5badge@example.test',
            'user_phone' => '0900000000',
            'status' => $status,
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
