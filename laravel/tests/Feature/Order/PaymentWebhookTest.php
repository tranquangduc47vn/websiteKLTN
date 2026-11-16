<?php

namespace Tests\Feature\Order;

use App\Models\Admin;
use App\Models\Transaction;
use App\Models\User;
use App\Support\LegacyPassword;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PaymentWebhookTest extends OrderTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'sepay.enabled' => true,
            'sepay.webhook_secret' => 'test-secret',
            'sepay.account_number' => '',
            'sepay.bank_code' => '',
            'sepay.account_name' => '',
        ]);
    }

    public function test_transfer_order_stores_reference_and_qr_uses_server_amount(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user, 'web');
        $txId = $this->placeTransfer($user, ['amount' => 1, 'total' => 1, 'price' => 1]);
        $tx = DB::table('orders')->where('id', $txId)->first();
        $pay = $this->pay($txId);

        $this->assertSame('WEB' . $txId, $pay->reference);
        $this->assertSame('pending', $pay->status);
        $this->assertSame('pending', $tx->status);
        $this->assertNull($pay->gateway_id);

        $amount = (int) round((float) $tx->total);
        $this->assertGreaterThan(1, $amount);

        $this->get('/thanh-toan/checkout_qr/' . $txId)
            ->assertOk()
            ->assertSee('Đang chờ thanh toán')
            ->assertSee('WEB' . $txId)
            ->assertSee('amount=' . $amount, false)
            ->assertSee('addInfo=WEB' . $txId, false)
            ->assertSee('img.vietqr.io', false);
    }

    public function test_valid_webhook_marks_payment_paid_without_touching_order_flow(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user, 'web');
        $product = $this->standardProduct();
        $variantId = $this->variantId($product, 'Đen', 'S');
        $voucher = $this->makeVoucher();
        $this->addToCart($product, ['size' => 'S', 'color' => 'Đen']);
        $this->post('/thanh-toan', $this->checkoutPayload($user, [
            'payment' => 'Chuyển khoản',
            'voucher_code' => $voucher->code,
            'total' => 1,
        ]))->assertRedirect();

        $txId = $this->lastTransactionId($user);
        $tx = DB::table('orders')->where('id', $txId)->first();
        $amount = (int) round((float) $tx->total);
        $stock = $this->variantStock($variantId);
        $points = (int) $user->fresh()->loyalty_points;
        $used = (int) DB::table('vouchers')->where('id', $voucher->id)->value('used_count');
        $moves = DB::table('stock_movements')->where('reference_id', $txId)->where('movement_type', 'out')->count();
        $this->assertSame(1, $used);
        $this->assertSame(1, $moves);

        $this->webhook($txId, $amount, ['id' => 77001])->assertOk()->assertJson(['ok' => true, 'result' => 'paid']);

        $paid = $this->pay($txId);
        $this->assertSame('paid', $paid->status);
        $this->assertSame('pending', DB::table('orders')->where('id', $txId)->value('status'));
        $this->assertSame('77001', (string) $paid->gateway_id);
        $this->assertNotNull($paid->paid_at);
        $this->assertSame($stock, $this->variantStock($variantId));
        $this->assertSame($points, (int) $user->fresh()->loyalty_points);
        $this->assertSame($used, (int) DB::table('vouchers')->where('id', $voucher->id)->value('used_count'));
        $this->assertSame($moves, DB::table('stock_movements')->where('reference_id', $txId)->where('movement_type', 'out')->count());

        $this->get('/thanh-toan/checkout_qr/' . $txId)
            ->assertOk()
            ->assertSee('Thanh toán thành công');
    }

    public function test_duplicate_webhook_is_ignored(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user, 'web');
        $txId = $this->placeTransfer($user);
        $amount = $this->amountOf($txId);
        $stock = $this->variantStock($this->variantOfLastOrder($txId));

        $this->webhook($txId, $amount, ['id' => 77002])->assertJson(['result' => 'paid']);
        $paidAt = (string) $this->pay($txId)->paid_at;
        $this->webhook($txId, $amount, ['id' => 77002])->assertOk()->assertJson(['ok' => true, 'result' => 'duplicate']);

        $this->assertSame('paid', $this->pay($txId)->status);
        $this->assertSame($paidAt, (string) $this->pay($txId)->paid_at);
        $this->assertSame('pending', DB::table('orders')->where('id', $txId)->value('status'));
        $this->assertSame($stock, $this->variantStock($this->variantOfLastOrder($txId)));
    }

    public function test_wrong_secret_wrong_amount_unknown_reference_and_bad_payload_do_not_confirm(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user, 'web');
        $txId = $this->placeTransfer($user);
        $amount = $this->amountOf($txId);

        $this->webhook($txId, $amount, ['id' => 77003], 'nope')->assertStatus(401)->assertJson(['ok' => false]);
        $this->assertSame('pending', $this->pay($txId)->status);

        $this->postJson('/payment/webhook/sepay', ['transferType' => 'in'], [
            'Authorization' => 'Apikey test-secret',
        ])->assertStatus(422);
        $this->webhook($txId, $amount + 5000, ['id' => 77004])->assertOk()->assertJson(['result' => 'mismatch']);
        $row = $this->pay($txId);
        $this->assertSame('mismatch', $row->status);
        $this->assertSame('pending', DB::table('orders')->where('id', $txId)->value('status'));
        $this->assertNull($row->paid_at);

        $this->webhook(99999999, $amount, ['id' => 77005])->assertOk()->assertJson(['result' => 'unknown']);
        $this->assertSame('mismatch', $this->pay($txId)->status);

        $this->webhook($txId, $amount, ['id' => 77006, 'transferType' => 'out'])->assertStatus(422);
        $this->assertSame('pending', DB::table('orders')->where('id', $txId)->value('status'));
    }

    public function test_cancelled_order_is_not_marked_paid(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user, 'web');
        $txId = $this->placeTransfer($user);
        $variantId = $this->variantOfLastOrder($txId);
        DB::table('orders')->where('id', $txId)->update(['status' => Transaction::STATUS_CANCELLED]);
        $stock = $this->variantStock($variantId);

        $this->webhook($txId, $this->amountOf($txId), ['id' => 77007])->assertOk()->assertJson(['result' => 'review']);

        $row = DB::table('orders')->where('id', $txId)->first();
        $this->assertSame('cancelled', $row->status);
        $this->assertSame('review', $this->pay($txId)->status);
        $this->assertNull($this->pay($txId)->paid_at);
        $this->assertSame($stock, $this->variantStock($variantId));

        $this->webhook($txId, $this->amountOf($txId), ['id' => 77007])->assertJson(['result' => 'duplicate']);
        $this->assertSame('review', $this->pay($txId)->status);
    }

    public function test_payment_status_is_owner_only(): void
    {
        $owner = $this->makeUser();
        $other = $this->makeUser();
        $this->actingAs($owner, 'web');
        $txId = $this->placeTransfer($owner);

        $this->getJson('/thanh-toan/payment-status/' . $txId)
            ->assertOk()
            ->assertJson(['ok' => true, 'payment_status' => 'pending', 'order_status' => 'pending']);

        $this->actingAs($other, 'web');
        $this->getJson('/thanh-toan/payment-status/' . $txId)->assertStatus(403)->assertJson(['ok' => false]);

        Auth::guard('web')->logout();
        $this->getJson('/thanh-toan/payment-status/' . $txId)->assertStatus(401)->assertJson(['ok' => false]);
        $this->get('/payment/success/' . $txId)->assertNotFound();
    }

    public function test_cod_order_is_unchanged(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user, 'web');
        $product = $this->standardProduct();
        $this->addToCart($product, ['size' => 'S', 'color' => 'Đen', 'qty' => 2]);
        $this->post('/thanh-toan', $this->checkoutPayload($user, ['payment' => 'COD', 'total' => 1]))
            ->assertRedirect(url('gio-hang'));

        $txId = $this->lastTransactionId($user);
        $tx = DB::table('orders')->where('id', $txId)->first();
        $pay = $this->pay($txId);
        $this->assertSame('cod', $pay->method);
        $this->assertSame('pending', $tx->status);
        $this->assertNull($pay->reference);
        $this->assertTrue($pay->status === null || $pay->status === 'cod');
    }

    public function test_admin_detail_shows_webhook_payment_and_log_omits_secret(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user, 'web');
        $txId = $this->placeTransfer($user);
        $logged = '';
        Log::listen(function ($event) use (&$logged) {
            $logged .= json_encode($event->context ?? []);
        });
        $this->webhook($txId, $this->amountOf($txId), ['id' => 77008])->assertJson(['result' => 'paid']);
        $this->assertStringNotContainsString('test-secret', $logged);

        $admin = Admin::query()->create([
            'name' => 'Pay Admin',
            'email' => 'payadmin.' . Str::lower(Str::random(8)) . '@example.test',
            'password_hash' => LegacyPassword::hash('Pay-Admin-Secret#1'),
            'level' => Admin::ROLE_ADMIN,
        ]);

        try {
            $this->actingAs($admin, 'admin');
            $this->get('/admin/orders/detail/' . $txId)
                ->assertOk()
                ->assertSee('Chuyển khoản')
                ->assertSee('Đã thanh toán')
                ->assertSee('WEB' . $txId)
                ->assertSee('77008')
                ->assertSee('Xác nhận bằng webhook');
        } finally {
            DB::table('admins')->where('id', $admin->id)->delete();
        }
    }

    public function test_disabled_or_empty_secret_rejects_webhook(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user, 'web');
        $txId = $this->placeTransfer($user);
        config(['sepay.enabled' => false]);
        $this->webhook($txId, $this->amountOf($txId), ['id' => 77009])->assertStatus(401);
        config(['sepay.enabled' => true, 'sepay.webhook_secret' => '']);
        $this->webhook($txId, $this->amountOf($txId), ['id' => 77010])->assertStatus(401);
        $this->assertSame('pending', $this->pay($txId)->status);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function placeTransfer(User $user, array $extra = []): int
    {
        $product = $this->standardProduct();
        $this->addToCart($product, ['size' => 'S', 'color' => 'Đen']);
        $this->post('/thanh-toan', $this->checkoutPayload($user, array_merge([
            'payment' => 'Chuyển khoản',
        ], $extra)))->assertRedirect();

        return $this->lastTransactionId($user);
    }

    private function pay(int $txId): ?object
    {
        return DB::table('payments')->where('order_id', $txId)->first();
    }

    private function amountOf(int $txId): int
    {
        return (int) round((float) DB::table('orders')->where('id', $txId)->value('total'));
    }

    private function variantOfLastOrder(int $txId): int
    {
        return (int) DB::table('order_items')->where('order_id', $txId)->value('variant_id');
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function webhook(int $txId, int $amount, array $overrides = [], string $secret = 'test-secret')
    {
        $payload = array_merge([
            'id' => $overrides['id'] ?? 77000,
            'gateway' => 'Vietcombank',
            'transactionDate' => '2026-10-20 10:00:00',
            'accountNumber' => '0123499999',
            'content' => 'chuyen khoan WEB' . $txId . ' cam on',
            'transferType' => 'in',
            'transferAmount' => $amount,
        ], $overrides);

        return $this->postJson('/payment/webhook/sepay', $payload, [
            'Authorization' => 'Apikey ' . $secret,
        ]);
    }
}
