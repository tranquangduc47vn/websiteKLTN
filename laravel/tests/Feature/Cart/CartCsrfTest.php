<?php

namespace Tests\Feature\Cart;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;

/**
 * CSRF thật cho các endpoint giỏ hàng: Laravel mặc định bỏ qua CSRF khi chạy unit test,
 * nên test này thay middleware bằng bản luôn kiểm tra token.
 */
class CartCsrfTest extends CartTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->bind(ValidateCsrfToken::class, fn ($app) => new class($app, $app['encrypter']) extends ValidateCsrfToken
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });
    }

    public function test_cart_post_without_token_is_rejected(): void
    {
        $this->actingAs($this->makeUser(), 'web');
        $product = $this->standardProduct();

        $this->withHeaders(self::AJAX)->post('/gio-hang/update', ['rowid' => 'x', 'action' => 'sum'])->assertStatus(419);
        $this->post('/gio-hang/them/' . $product->id, ['size' => 'S', 'color' => 'Đen'])->assertStatus(419);

        $this->assertSame([], $this->sessionCart());
    }

    public function test_cart_post_with_session_token_is_accepted(): void
    {
        $this->actingAs($this->makeUser(), 'web');
        $product = $this->standardProduct();

        $this->get('/gio-hang')->assertOk();
        $token = csrf_token();

        $this->post('/gio-hang/them/' . $product->id, ['_token' => $token, 'size' => 'S', 'color' => 'Đen'])
            ->assertRedirect(url('gio-hang'));

        $this->withHeaders(self::AJAX)
            ->post('/gio-hang/update', ['_token' => $token, 'rowid' => $this->rowId($product, 'S', 'Đen'), 'action' => 'sum'])
            ->assertOk()
            ->assertJson(['ok' => true, 'qty' => 2, 'csrf_name' => '_token', 'csrf_hash' => $token]);
    }

    public function test_cart_page_bootstraps_csrf_for_ajax(): void
    {
        $this->actingAs($this->makeUser(), 'web');

        $this->get('/gio-hang')
            ->assertOk()
            ->assertSee('window.CI_CSRF', false)
            ->assertSee('"name":"_token"', false);
    }
}
