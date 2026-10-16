<?php

namespace Tests\Feature\Order;

use App\Models\Catalog;
use App\Support\Storefront\Urls;

class OrderRegressionTest extends OrderTestCase
{
    public function test_storefront_and_checkout_render_after_order(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user, 'web');
        $product = $this->standardProduct();
        $catalog = Catalog::query()->findOrFail($product->catalog_id);

        $this->addToCart($product, ['size' => 'S', 'color' => 'Đen']);
        $this->get('/gio-hang')->assertOk()->assertSee('1 sản phẩm');
        $this->get('/thanh-toan')->assertOk()->assertSee('Tiến hành thanh toán');
        $this->post('/thanh-toan', $this->checkoutPayload($user))->assertRedirect(url('gio-hang'));

        foreach ([
            '/',
            '/ban-chay',
            Urls::product($product),
            Urls::category($catalog),
            '/gio-hang',
            '/thanh-toan',
            '/user',
        ] as $url) {
            $this->get($url)->assertOk();
        }

        $this->assertSame([], $this->sessionCart());
        $this->get('/gio-hang')->assertSee('Giỏ hàng');
    }
}
