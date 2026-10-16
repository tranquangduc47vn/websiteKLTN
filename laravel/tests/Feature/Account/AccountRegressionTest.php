<?php

namespace Tests\Feature\Account;

use App\Support\Storefront\Urls;

class AccountRegressionTest extends AccountTestCase
{
    public function test_b3_auth_pages_and_account_after_login(): void
    {
        $this->get('/dang-nhap')->assertOk()->assertSee('name="_token"', false);
        $this->get('/dang-ky')->assertOk()->assertSee('name="_token"', false);
        $this->get('/quen-mat-khau')->assertOk();
        $this->get('/user')->assertRedirect(url('dang-nhap'));

        $user = $this->makeUser();
        $this->actingAs($user, 'web');
        $this->get('/user')->assertOk()->assertSee('Thông tin tài khoản')->assertSee($user->email);
        $this->get('/dang-nhap')->assertRedirect();
        $this->post('/user/logout')->assertRedirect(route('storefront.home'));
        $this->get('/user')->assertRedirect(url('dang-nhap'));
    }

    public function test_b5_cart_and_b6_checkout_still_work_with_account(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user, 'web');
        $product = $this->standardProduct();

        $this->addToCart($product, ['size' => 'S', 'color' => 'Đen', 'qty' => 1])->assertRedirect(url('gio-hang'));
        $this->get('/gio-hang')->assertOk()->assertSee('1 sản phẩm');
        $this->get('/thanh-toan')->assertOk()->assertSee('Tiến hành thanh toán');
        $this->post('/thanh-toan', $this->checkoutPayload($user))->assertRedirect(url('gio-hang'));

        $txId = $this->lastTransactionId($user);
        $this->get('/user')->assertOk()->assertSee('Hủy đơn')->assertSee('Chi tiết');
        $this->get('/user/order_detail/' . $txId)->assertOk()->assertSee('Đơn hàng #' . $txId);
        $this->get(Urls::product($product))->assertOk();
        $this->assertSame([], $this->sessionCart());
    }
}
