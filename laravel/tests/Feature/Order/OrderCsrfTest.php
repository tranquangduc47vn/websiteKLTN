<?php

namespace Tests\Feature\Order;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;

class OrderCsrfTest extends OrderTestCase
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

    public function test_checkout_post_without_token_is_rejected(): void
    {
        $user = $this->makeUser();
        $product = $this->standardProduct();
        $this->actingAs($user, 'web');
        $this->session(['custom_cart' => $this->cartRow($product)]);

        $this->post('/thanh-toan', $this->checkoutPayload($user))->assertStatus(419);
        $this->post('/thanh-toan/apply_voucher', ['voucher_code' => 'X'])->assertStatus(419);
        $this->post('/thanh-toan/cancel/1')->assertStatus(419);

        $this->assertSame(0, (int) \Illuminate\Support\Facades\DB::table('transaction')->where('user_id', $user->id)->count());
        $this->assertNotSame([], $this->sessionCart());
    }

    public function test_checkout_post_with_token_is_accepted(): void
    {
        $user = $this->makeUser();
        $product = $this->standardProduct();
        $this->actingAs($user, 'web');
        $this->session(['custom_cart' => $this->cartRow($product)]);

        $this->get('/thanh-toan')->assertOk();
        $token = csrf_token();

        $this->post('/thanh-toan', $this->checkoutPayload($user) + ['_token' => $token])
            ->assertRedirect(url('gio-hang'));

        $this->assertSame(1, (int) \Illuminate\Support\Facades\DB::table('transaction')->where('user_id', $user->id)->count());
    }
}
