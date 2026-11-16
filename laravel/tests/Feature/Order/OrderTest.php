<?php

namespace Tests\Feature\Order;

use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class OrderTest extends OrderTestCase
{
    public function test_order_routes_keep_ci3_urls(): void
    {
        $this->get('/thanh-toan')->assertOk();
        $this->post('/thanh-toan/apply_voucher')->assertOk()->assertJson(['ok' => false]);
        $this->get('/thanh-toan/khong-ton-tai')->assertNotFound();
        $this->get('/thanh-toan/cancel/1')->assertNotFound();
        $this->get('/order')->assertStatus(301)->assertRedirect(url('thanh-toan'));
        $this->get('/order/checkout_qr/1')->assertStatus(301)->assertRedirect(url('thanh-toan/checkout_qr/1'));
    }

    public function test_guest_can_view_checkout_form(): void
    {
        $this->get('/thanh-toan')
            ->assertOk()
            ->assertSee('Thông tin thanh toán')
            ->assertSee('Đăng nhập')
            ->assertSee('name="_token"', false)
            ->assertDontSee('jm-sidebar', false);
    }

    public function test_guest_post_empty_cart_redirects_toward_login(): void
    {
        $this->post('/thanh-toan', [
            'name' => 'Khách',
            'email' => 'guest@example.test',
            'phone' => '0900000000',
            'address' => 'HN',
            'payment' => 'COD',
        ])->assertRedirect(url('gio-hang'));

        $this->get('/gio-hang')->assertRedirect(url('dang-nhap'));
    }

    public function test_guest_with_cart_cannot_checkout_and_login_keeps_cart(): void
    {
        $user = $this->makeUser();
        $product = $this->standardProduct();
        $variantId = $this->variantId($product, 'Đen', 'S');
        $stock = $this->variantStock($variantId);
        $orders = DB::table('orders')->count();
        $payments = DB::table('payments')->count();
        $items = DB::table('order_items')->count();

        $this->withSession(['custom_cart' => $this->cartRow($product, 'S', 'Đen', 2)])
            ->post('/thanh-toan', $this->checkoutPayload($user) + [
                'payment' => 'Chuyển khoản',
                'price' => 1,
                'total' => 1,
            ])
            ->assertRedirect(url('dang-nhap'));

        $this->assertSame($orders, DB::table('orders')->count());
        $this->assertSame($payments, DB::table('payments')->count());
        $this->assertSame($items, DB::table('order_items')->count());
        $this->assertSame($stock, $this->variantStock($variantId));

        $this->post('/dang-nhap', [
            'email' => $user->email,
            'password' => self::PASSWORD,
        ])->assertRedirect(url('thanh-toan'));

        $this->assertSame(2, (int) ($this->sessionCart()[$this->rowId($product, 'S', 'Đen')]['qty'] ?? 0));
    }

    public function test_empty_cart_checkout_is_rejected(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user, 'web');

        $this->post('/thanh-toan', $this->checkoutPayload($user))
            ->assertRedirect(url('gio-hang'));

        $this->assertSame(0, DB::table('orders')->where('user_id', $user->id)->count());
    }

    public function test_cod_checkout_creates_order_and_clears_cart(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user, 'web');
        $product = $this->standardProduct();
        $variantId = $this->variantId($product, 'Đen', 'S');
        $stockBefore = $this->variantStock($variantId);

        $this->addToCart($product, ['size' => 'S', 'color' => 'Đen', 'qty' => 2])->assertRedirect(url('gio-hang'));

        $this->post('/thanh-toan', $this->checkoutPayload($user) + [
            'price' => 1,
            'subtotal' => 1,
            'total' => 1,
            'discount_amount' => 999999,
            'stock' => 999,
        ])->assertRedirect(url('gio-hang'));

        $txId = $this->lastTransactionId($user);
        $tx = DB::table('orders')->where('id', $txId)->first();
        $this->assertNotNull($tx);
        $this->assertSame('cod', DB::table('payments')->where('order_id', $txId)->value('method'));
        $this->assertSame('pending', $tx->status);
        $this->assertSame($user->id, (int) $tx->user_id);
        $this->assertEquals(500000, (float) $tx->total);
        $this->assertTrue($tx->voucher_code === null || $tx->voucher_code === '');
        $this->assertSame(0, (int) $tx->discount_amount);
        $this->assertSame('Ghi chú B6', $tx->note);

        $line = DB::table('order_items')->where('order_id', $txId)->first();
        $this->assertSame($product->id, (int) $line->product_id);
        $this->assertSame($variantId, (int) $line->variant_id);
        $this->assertSame(2, (int) $line->qty);
        $this->assertSame('S', $line->size_name);
        $this->assertSame('Đen', $line->color_name);
        $this->assertEquals(500000, (float) $line->line_subtotal);

        $this->assertSame($stockBefore - 2, $this->variantStock($variantId));
        $this->assertSame(4, (int) DB::table('product_variants')->where('product_id', $product->id)->where('status', 'active')->sum('stock'));
        $this->assertSame([], $this->sessionCart());
        $this->assertSame([], $this->savedCart($user));
        $this->assertSame(1, DB::table('stock_movements')->where('reference_id', $txId)->where('movement_type', 'out')->count());
    }

    public function test_multiple_items_and_bank_transfer_redirects_to_qr(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user, 'web');
        $a = $this->standardProduct();
        $b = $this->standardProduct();
        $this->addToCart($a, ['size' => 'S', 'color' => 'Đen']);
        $this->addToCart($b, ['size' => 'M', 'color' => 'Đen']);

        $this->post('/thanh-toan', $this->checkoutPayload($user, ['payment' => 'Chuyển khoản']))
            ->assertRedirect();

        $txId = $this->lastTransactionId($user);
        $this->assertSame('bank_transfer', DB::table('payments')->where('order_id', $txId)->value('method'));
        $this->assertSame(2, DB::table('order_items')->where('order_id', $txId)->count());
        $this->assertSame([], $this->sessionCart());

        $this->get('/thanh-toan/checkout_qr/' . $txId)
            ->assertOk()
            ->assertSee('Thanh toán chuyển khoản')
            ->assertSee('#' . $txId)
            ->assertSee('img.vietqr.io', false);
    }

    public function test_over_stock_does_not_create_order(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user, 'web');
        $product = $this->standardProduct();
        $variantId = $this->variantId($product, 'Đen', 'M');
        $this->addToCart($product, ['size' => 'M', 'color' => 'Đen']);
        DB::table('product_variants')->where('id', $variantId)->update(['stock' => 0]);

        $beforeTx = DB::table('orders')->count();
        $this->post('/thanh-toan', $this->checkoutPayload($user))->assertRedirect();
        $this->assertSame($beforeTx, DB::table('orders')->count());
        $this->assertSame(0, $this->variantStock($variantId));
    }

    public function test_voucher_valid_invalid_expired_and_minimum(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user, 'web');
        $product = $this->standardProduct();
        $this->addToCart($product, ['size' => 'S', 'color' => 'Đen']);

        $this->withHeaders(self::AJAX)->post('/thanh-toan/apply_voucher', ['voucher_code' => 'NOPE'])
            ->assertOk()
            ->assertJson(['ok' => false]);

        $expired = $this->makeVoucher(['valid_to' => time() - 60]);
        $this->withHeaders(self::AJAX)->post('/thanh-toan/apply_voucher', ['voucher_code' => $expired->code])
            ->assertJsonFragment(['ok' => false, 'message' => 'Mã voucher đã hết hạn.']);

        $min = $this->makeVoucher(['min_order_amount' => 999999999]);
        $this->withHeaders(self::AJAX)->post('/thanh-toan/apply_voucher', ['voucher_code' => $min->code])
            ->assertJsonPath('ok', false);

        $ok = $this->makeVoucher(['discount_type' => 'percent', 'discount_value' => 10]);
        $this->withHeaders(self::AJAX)->post('/thanh-toan/apply_voucher', ['voucher_code' => $ok->code])
            ->assertJsonPath('ok', true)
            ->assertJsonPath('discount', 25000)
            ->assertJsonPath('final', 225000);

        $this->post('/thanh-toan', $this->checkoutPayload($user, ['voucher_code' => $ok->code]))
            ->assertRedirect(url('gio-hang'));

        $tx = DB::table('orders')->where('user_id', $user->id)->orderByDesc('id')->first();
        $this->assertSame($ok->code, $tx->voucher_code);
        $this->assertSame(25000, (int) $tx->discount_amount);
        $this->assertEquals(225000, (float) $tx->total);
        $this->assertSame(1, (int) DB::table('vouchers')->where('id', $ok->id)->value('used_count'));
        $this->assertSame(1, DB::table('voucher_uses')->where('order_id', $tx->id)->count());
    }

    public function test_voucher_usage_limit_and_client_discount_are_ignored(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user, 'web');
        $product = $this->standardProduct();
        $this->addToCart($product, ['size' => 'S', 'color' => 'Đen']);
        $voucher = $this->makeVoucher(['usage_limit' => 1, 'used_count' => 1, 'per_user_limit' => 5]);

        $this->post('/thanh-toan', $this->checkoutPayload($user, [
            'voucher_code' => $voucher->code,
            'discount_amount' => 1,
        ]))->assertRedirect(url('thanh-toan'));

        $this->assertSame(0, DB::table('orders')->where('user_id', $user->id)->count());
        $this->assertSame(1, (int) DB::table('vouchers')->where('id', $voucher->id)->value('used_count'));
    }

    public function test_double_submit_does_not_create_second_order(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user, 'web');
        $product = $this->standardProduct();
        $this->addToCart($product, ['size' => 'S', 'color' => 'Đen']);
        $payload = $this->checkoutPayload($user);

        $this->post('/thanh-toan', $payload)->assertRedirect(url('gio-hang'));
        $this->post('/thanh-toan', $payload)->assertRedirect(url('gio-hang'));

        $this->assertSame(1, DB::table('orders')->where('user_id', $user->id)->count());
    }

    public function test_user_cannot_view_or_cancel_another_users_order(): void
    {
        $owner = $this->makeUser();
        $other = $this->makeUser();
        $this->actingAs($owner, 'web');
        $product = $this->standardProduct();
        $this->addToCart($product, ['size' => 'S', 'color' => 'Đen']);
        $this->post('/thanh-toan', $this->checkoutPayload($owner, ['payment' => 'Chuyển khoản']));
        $txId = $this->lastTransactionId($owner);

        $this->actingAs($other, 'web');
        $this->get('/thanh-toan/checkout_qr/' . $txId)->assertRedirect(url('/'));
        $this->post('/thanh-toan/cancel/' . $txId)->assertRedirect(url('/'));
        $this->assertSame('pending', DB::table('orders')->where('id', $txId)->value('status'));
    }

    public function test_owner_can_cancel_new_order_and_stock_is_restored(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user, 'web');
        $product = $this->standardProduct();
        $variantId = $this->variantId($product, 'Đen', 'S');
        $stockBefore = $this->variantStock($variantId);
        $this->addToCart($product, ['size' => 'S', 'color' => 'Đen', 'qty' => 2]);
        $this->post('/thanh-toan', $this->checkoutPayload($user));
        $txId = $this->lastTransactionId($user);
        $this->assertSame($stockBefore - 2, $this->variantStock($variantId));

        $this->post('/thanh-toan/cancel/' . $txId, ['reason' => 'Đổi ý'])
            ->assertRedirect(url('user'));

        $tx = DB::table('orders')->where('id', $txId)->first();
        $this->assertSame('cancelled', $tx->status);
        $this->assertSame('Đổi ý', $tx->cancel_reason);
        $this->assertSame($stockBefore, $this->variantStock($variantId));
        $this->assertSame(0, DB::table('voucher_uses')->where('order_id', $txId)->count());
        $this->assertSame(1, DB::table('stock_movements')->where('reference_id', $txId)->where('movement_type', 'in')->count());
    }

    public function test_completed_order_cannot_be_cancelled_from_storefront(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user, 'web');
        $product = $this->standardProduct();
        $this->addToCart($product, ['size' => 'S', 'color' => 'Đen']);
        $this->post('/thanh-toan', $this->checkoutPayload($user));
        $txId = $this->lastTransactionId($user);
        DB::table('orders')->where('id', $txId)->update(['status' => Transaction::STATUS_COMPLETED]);

        $this->post('/thanh-toan/cancel/' . $txId)->assertRedirect(url('user'));
        $this->assertSame('completed', DB::table('orders')->where('id', $txId)->value('status'));
    }

    public function test_unknown_payment_falls_back_to_cod(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user, 'web');
        $product = $this->standardProduct();
        $this->addToCart($product, ['size' => 'S', 'color' => 'Đen']);
        $this->post('/thanh-toan', $this->checkoutPayload($user, ['payment' => 'paypal']));
        $this->assertSame('cod', DB::table('payments')->where('order_id', DB::table('orders')->where('user_id', $user->id)->value('id'))->value('method'));
    }

    public function test_validation_errors_redisplay_checkout(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user, 'web');
        $product = $this->standardProduct();
        $this->addToCart($product, ['size' => 'S', 'color' => 'Đen']);

        $this->from('/thanh-toan')->post('/thanh-toan', [
            'name' => '',
            'email' => 'not-an-email',
            'phone' => '',
            'address' => '',
        ])->assertRedirect(url('thanh-toan'))->assertSessionHasErrors(['name', 'email', 'phone', 'address']);

        $this->assertSame(0, DB::table('orders')->where('user_id', $user->id)->count());
    }

    public function test_missing_qr_transaction_redirects_home(): void
    {
        $this->actingAs($this->makeUser(), 'web');
        $this->get('/thanh-toan/checkout_qr/99999999')->assertRedirect(url('/'));
    }
}
