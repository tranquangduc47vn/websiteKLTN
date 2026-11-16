<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\Voucher;
use App\Support\LegacyPassword;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class VoucherTest extends AdminTestCase
{
    /** @var list<int> */
    private array $voucherIds = [];

    /** @var list<int> */
    private array $userIds = [];

    /** @var list<int> */
    private array $productIds = [];

    /** @var list<int> */
    private array $orderIds = [];

    protected function tearDown(): void
    {
        if ($this->orderIds !== []) {
            DB::table('voucher_uses')->whereIn('order_id', $this->orderIds)->delete();
            DB::table('stock_movements')->where('reference_type', 'order')->whereIn('reference_id', $this->orderIds)->delete();
            DB::table('order_items')->whereIn('order_id', $this->orderIds)->delete();
            DB::table('orders')->whereIn('id', $this->orderIds)->delete();
        }
        if ($this->voucherIds !== []) {
            DB::table('voucher_uses')->whereIn('voucher_id', $this->voucherIds)->delete();
            DB::table('vouchers')->whereIn('id', $this->voucherIds)->delete();
        }
        if ($this->productIds !== []) {
            $variantIds = DB::table('product_variants')->whereIn('product_id', $this->productIds)->pluck('id');
            if ($variantIds->isNotEmpty()) {
                DB::table('stock_movements')->whereIn('variant_id', $variantIds)->delete();
            }
            DB::table('product_variants')->whereIn('product_id', $this->productIds)->delete();
            DB::table('products')->whereIn('id', $this->productIds)->delete();
        }
        if ($this->userIds !== []) {
            DB::table('user_saved_carts')->whereIn('user_id', $this->userIds)->delete();
            DB::table('users')->whereIn('id', $this->userIds)->delete();
        }
        parent::tearDown();
    }

    public function test_guest_mod_and_fake_session_cannot_manage_vouchers(): void
    {
        $this->get('/admin/voucher')->assertRedirect(route('admin.login'));

        $customer = User::query()->create([
            'name' => 'B12 Guard',
            'email' => 'b12guard.' . Str::lower(Str::random(6)) . '@example.test',
            'password_hash' => LegacyPassword::hash('CustomerPass1'),
            'created_at' => now(),
        ]);
        $this->userIds[] = (int) $customer->id;
        $this->actingAs($customer, 'web');
        $this->get('/admin/voucher/add')->assertRedirect(route('admin.login'));
        $this->assertFalse(Auth::guard('admin')->check());

        $this->withSession(['login' => ['id' => 1, 'name' => 'Fake', 'email' => 'fake@example.test', 'level' => 0]]);
        $this->get('/admin/voucher/edit/1')->assertRedirect(route('admin.login'));

        $this->actingAs($this->makeAdmin(Admin::ROLE_MOD), 'admin');
        $this->get('/admin/voucher?level=0')->assertRedirect(route('admin.home'));
        $this->post('/admin/voucher/add', ['code' => 'B12NOPE', 'discount_value' => 1, 'is_active' => 1])
            ->assertRedirect(route('admin.home'));

        $this->actingAs($this->makeAdmin(Admin::ROLE_USER), 'admin');
        $this->get('/admin/voucher')->assertRedirect(route('admin.home'));
    }

    public function test_admin_crud_normalizes_code_and_rejects_duplicates(): void
    {
        $this->actingAs($this->makeAdmin(Admin::ROLE_ADMIN), 'admin');
        $code = 'B12' . Str::upper(Str::random(5));

        $this->post('/admin/voucher/add', [
            'code' => '  ' . strtolower($code) . '  ',
            'name' => 'Voucher ' . $code,
            'description' => 'Mo ta',
            'discount_type' => 'percent',
            'discount_value' => 10,
            'max_discount' => 15000,
            'min_order_amount' => 50000,
            'tier_min' => 'member',
            'user_id' => 0,
            'usage_limit' => 0,
            'per_user_limit' => 0,
            'is_active' => 1,
        ])->assertRedirect(url('admin/voucher'))->assertSessionHas('message_success', 'Tạo voucher thành công.');

        $row = Voucher::query()->where('code', $code)->first();
        $this->assertNotNull($row);
        $this->voucherIds[] = (int) $row->id;
        $this->assertSame(1, (int) $row->per_user_limit);
        $this->assertSame(0, (int) $row->usage_limit);
        $this->assertSame(0, (int) $row->used_count);

        $this->get('/admin/voucher')->assertOk()->assertSee($code)->assertSee('10%')->assertSee('Đang bật');

        $this->post('/admin/voucher/add', [
            'code' => $code,
            'discount_type' => 'fixed',
            'discount_value' => 1000,
            'is_active' => 1,
        ])->assertRedirect(url('admin/voucher/add'))->assertSessionHas('message_fail', 'Mã voucher đã tồn tại.');

        $this->post('/admin/voucher/add', ['code' => '   ', 'discount_value' => 1])
            ->assertSessionHas('message_fail', 'Mã voucher không hợp lệ.');

        $this->post('/admin/voucher/edit/' . $row->id, [
            'code' => $code,
            'name' => 'Da sua',
            'discount_type' => 'fixed',
            'discount_value' => 20000,
            'min_order_amount' => 0,
            'max_discount' => 0,
            'tier_min' => 'member',
            'user_id' => 0,
            'usage_limit' => 5,
            'per_user_limit' => 2,
            'is_active' => 1,
            'valid_to' => date('Y-m-d', strtotime('-1 day')),
        ])->assertSessionHas('message_success', 'Cập nhật voucher thành công.');
        $row->refresh();
        $this->assertSame(20000, (int) $row->discount_value);
        $this->assertSame('fixed', $row->discount_type);
        $this->assertNotNull($row->ends_at);
        $this->assertSame(0, (int) $row->used_count);

        $this->post('/admin/voucher/edit/99999999', [
            'code' => 'B12MISSING',
            'discount_value' => 1,
            'is_active' => 1,
        ])->assertSessionHas('message_fail', 'Voucher không tồn tại.');

        $this->post('/admin/voucher/toggle_active/' . $row->id)->assertSessionHas('message_success', 'Đã tắt voucher.');
        $this->assertSame(0, (int) $row->fresh()->is_active);
        $this->get('/admin/voucher/delete/' . $row->id)->assertNotFound();
        $this->post('/admin/voucher/delete/' . $row->id)->assertSessionHas('message_success', 'Đã xóa voucher.');
        $this->assertNull(Voucher::query()->find($row->id));
        array_pop($this->voucherIds);
    }

    public function test_checkout_uses_admin_voucher_and_cancel_releases_once(): void
    {
        $admin = $this->makeAdmin(Admin::ROLE_ADMIN);
        $this->actingAs($admin, 'admin');
        $code = 'B12C' . Str::upper(Str::random(4));
        $this->post('/admin/voucher/add', [
            'code' => $code,
            'name' => 'Checkout ' . $code,
            'discount_type' => 'percent',
            'discount_value' => 10,
            'max_discount' => 15000,
            'min_order_amount' => 50000,
            'tier_min' => 'member',
            'usage_limit' => 1,
            'per_user_limit' => 1,
            'is_active' => 1,
        ]);
        $voucherId = (int) Voucher::query()->where('code', $code)->value('id');
        $this->voucherIds[] = $voucherId;

        $buyer = $this->makeBuyer();
        $product = $this->makeProduct();
        $this->actingAs($buyer, 'web');
        $this->post('/gio-hang/add/' . $product->id, ['qty' => 2, 'size' => 'M', 'color' => 'Đen']);
        $this->post('/thanh-toan/apply_voucher', ['voucher_code' => strtolower($code)])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('discount', 15000);

        $this->post('/thanh-toan', $this->payload($buyer) + ['voucher_code' => $code])->assertRedirect();
        $orderId = (int) DB::table('orders')->where('user_id', $buyer->id)->value('id');
        $this->orderIds[] = $orderId;
        $this->assertEquals(185000, (float) DB::table('orders')->where('id', $orderId)->value('total'));
        $this->assertSame(1, (int) Voucher::query()->find($voucherId)->used_count);

        $this->post('/gio-hang/add/' . $product->id, ['qty' => 2, 'size' => 'M', 'color' => 'Đen']);
        $this->post('/thanh-toan/apply_voucher', ['voucher_code' => $code])
            ->assertJsonPath('message', 'Mã voucher đã hết lượt sử dụng.');
        $this->post('/thanh-toan', $this->payload($buyer) + ['voucher_code' => $code]);
        $this->assertSame(1, DB::table('orders')->where('user_id', $buyer->id)->count());
        $this->assertSame(1, (int) Voucher::query()->find($voucherId)->used_count);

        $this->actingAs($admin, 'admin');
        $this->post('/admin/orders/cancel/' . $orderId, ['reason' => 'B12'])->assertSessionHas('message_success');
        $this->assertSame(0, (int) Voucher::query()->find($voucherId)->used_count);
        $this->assertSame(0, DB::table('voucher_uses')->where('order_id', $orderId)->count());
        $this->post('/admin/orders/cancel/' . $orderId, ['reason' => 'B12 lan 2']);
        $this->assertSame(0, (int) Voucher::query()->find($voucherId)->used_count);

        Auth::guard('admin')->logout();
        $this->actingAs($buyer, 'web');
        $this->post('/gio-hang/add/' . $product->id, ['qty' => 1, 'size' => 'M', 'color' => 'Đen']);
        $this->post('/admin/voucher/edit/' . $voucherId, [])->assertRedirect(route('admin.login'));
        $this->actingAs($admin, 'admin');
        $this->post('/admin/voucher/edit/' . $voucherId, [
            'code' => $code,
            'discount_type' => 'fixed',
            'discount_value' => 1000,
            'min_order_amount' => 99999999,
            'tier_min' => 'member',
            'usage_limit' => 0,
            'per_user_limit' => 1,
            'is_active' => 1,
        ]);
        $this->actingAs($buyer, 'web');
        $this->post('/thanh-toan/apply_voucher', ['voucher_code' => $code])
            ->assertJsonPath('ok', false);
    }

    public function test_inactive_voucher_is_rejected_at_checkout(): void
    {
        $this->actingAs($this->makeAdmin(Admin::ROLE_ADMIN), 'admin');
        $code = 'B12OFF' . Str::upper(Str::random(3));
        $this->post('/admin/voucher/add', [
            'code' => $code,
            'discount_type' => 'fixed',
            'discount_value' => 1000,
            'tier_min' => 'member',
            'is_active' => 1,
        ]);
        $id = (int) Voucher::query()->where('code', $code)->value('id');
        $this->voucherIds[] = $id;
        $this->post('/admin/voucher/toggle_active/' . $id, [], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('is_active', 0);

        $buyer = $this->makeBuyer();
        $product = $this->makeProduct();
        $this->actingAs($buyer, 'web');
        $this->post('/gio-hang/add/' . $product->id, ['qty' => 1, 'size' => 'M', 'color' => 'Đen']);
        $this->post('/thanh-toan/apply_voucher', ['voucher_code' => $code])
            ->assertJsonPath('message', 'Mã voucher đang tạm khóa.');
    }

    public function test_posts_without_csrf_are_rejected(): void
    {
        $this->app->bind(ValidateCsrfToken::class, fn ($app) => new class($app, $app['encrypter']) extends ValidateCsrfToken
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });
        $this->actingAs($this->makeAdmin(Admin::ROLE_ADMIN), 'admin');
        $this->post('/admin/voucher/add', ['code' => 'B12CSRF', 'discount_value' => 1, 'is_active' => 1])->assertStatus(419);
        $this->post('/admin/voucher/delete/1')->assertStatus(419);
        $this->post('/admin/voucher/toggle_active/1')->assertStatus(419);
        $this->assertNull(Voucher::query()->where('code', 'B12CSRF')->first());
    }

    private function makeBuyer(): User
    {
        $user = User::query()->create([
            'name' => 'B12 Buyer',
            'email' => 'b12buyer.' . Str::lower(Str::random(6)) . '@example.test',
            'password_hash' => LegacyPassword::hash('BuyerPass1'),
            'loyalty_tier' => 'member',
            'created_at' => now(),
        ]);
        $this->userIds[] = (int) $user->id;

        return $user;
    }

    /** @return array<string, string> */
    private function payload(User $user): array
    {
        return [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => '0900111222',
            'address' => '123 Duong Test',
            'payment' => 'COD',
        ];
    }

    private function makeProduct(): Product
    {
        $product = Product::query()->create([
            'category_id' => (int) DB::table('categories')->orderBy('id')->value('id'),
            'name' => 'B12 Product ' . Str::lower(Str::random(4)),
            'content' => '',
            'list_price' => 100000,
            'discount_amount' => 0,
            'code' => 'B12' . strtoupper(Str::random(6)),
            'status' => 'active',
            'view_count' => 0,
            'sold_count' => 0,
            'rating_total' => 0,
            'rating_count' => 0,
        ]);
        ProductVariant::query()->create([
            'product_id' => $product->id,
            'sku' => 'B12-' . $product->id,
            'color_name' => 'Đen',
            'size_name' => 'M',
            'cost_price' => 0,
            'stock' => 10,
            'min_stock' => 1,
            'status' => 'active',
        ]);
        $this->productIds[] = (int) $product->id;

        return $product;
    }
}
