<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Transaction;
use App\Models\User;
use App\Support\LegacyPassword;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderCustomerTest extends AdminTestCase
{
    /** @var list<int> */
    private array $userIds = [];

    /** @var list<int> */
    private array $productIds = [];

    /** @var list<int> */
    private array $orderIds = [];

    protected function tearDown(): void
    {
        if ($this->orderIds !== []) {
            DB::table('stock_movements')->where('reference_type', 'order')->whereIn('reference_id', $this->orderIds)->delete();
            DB::table('point_logs')->whereIn('order_id', $this->orderIds)->delete();
            DB::table('order_items')->whereIn('order_id', $this->orderIds)->delete();
            DB::table('orders')->whereIn('id', $this->orderIds)->delete();
        }
        if ($this->productIds !== []) {
            DB::table('product_variants')->whereIn('product_id', $this->productIds)->delete();
            DB::table('products')->whereIn('id', $this->productIds)->delete();
        }
        if ($this->userIds !== []) {
            DB::table('user_addresses')->whereIn('user_id', $this->userIds)->delete();
            DB::table('user_phones')->whereIn('user_id', $this->userIds)->delete();
            DB::table('users')->whereIn('id', $this->userIds)->delete();
        }
        parent::tearDown();
    }

    public function test_guest_customer_and_level_user_are_blocked_from_orders(): void
    {
        $this->get('/admin/orders')->assertRedirect(route('admin.login'));
        $this->get('/admin/users')->assertRedirect(route('admin.login'));

        $customer = User::query()->create([
            'name' => 'B10 Customer Guard',
            'email' => 'b10guard.' . Str::lower(Str::random(6)) . '@example.test',
            'password_hash' => LegacyPassword::hash('CustomerPass1'),
            'created_at' => now(),
        ]);
        $this->userIds[] = (int) $customer->id;
        $this->actingAs($customer, 'web');
        $this->get('/admin/orders')->assertRedirect(route('admin.login'));
        $this->get('/admin/users')->assertRedirect(route('admin.login'));
        $this->assertFalse(Auth::guard('admin')->check());

        $this->withSession([
            'login' => ['id' => 1, 'name' => 'Fake', 'email' => 'fake@example.test', 'level' => 0],
        ]);
        $this->get('/admin/orders/detail/1')->assertRedirect(route('admin.login'));

        $this->actingAs($this->makeAdmin(Admin::ROLE_USER), 'admin');
        $this->get('/admin/orders')->assertRedirect(route('admin.home'))
            ->assertSessionHas('message_fail', 'Bạn không có quyền truy cập khu vực này!');
        $this->get('/admin/orders/detail/1?level=0')->assertRedirect(route('admin.home'));
        $this->post('/admin/orders/cancel/1', ['reason' => 'nope'])->assertRedirect(route('admin.home'));
        $this->get('/admin/orders/pending_count')->assertRedirect(route('admin.home'));
        $this->get('/admin/users')->assertOk()->assertSee('Khách hàng');
    }

    public function test_order_list_filter_pagination_detail_snapshot_and_status_rules(): void
    {
        $this->actingAs($this->makeAdmin(Admin::ROLE_MOD), 'admin');
        $buyer = $this->makeBuyer();
        $product = $this->makeProduct(5, 999999);
        $token = 'B10 Buyer ' . Str::lower(Str::random(4));

        $first = $this->makeOrder($buyer, $product, [
            'user_name' => $token,
            'user_phone' => '0912345678',
            'amount' => 50000,
            'status' => 0,
            'payment' => 'COD',
            'qty' => 2,
            'line_amount' => 50000,
        ]);

        $product->update(['list_price' => 1]);
        $this->get('/admin/orders/detail/' . $first)
            ->assertOk()
            ->assertSee('50.000')
            ->assertSee($token)
            ->assertDontSee('999.999');

        $this->get('/admin/orders?q=0912345678&status=0&payment=cod')
            ->assertOk()
            ->assertSee($token);

        $this->get('/admin/orders/accept/' . $first)->assertRedirect(url('admin/orders/detail/' . $first));
        $this->assertSame('confirmed', Transaction::query()->find($first)->status);
        $this->get('/admin/orders/accept/' . $first)
            ->assertRedirect(url('admin/orders/detail/' . $first))
            ->assertSessionHas('message_fail', 'Chỉ có thể xác nhận đơn đang ở trạng thái chờ xử lý.');

        $this->get('/admin/orders/complete/' . $first)->assertSessionHas('message_success');
        $product->refresh();
        $buyer->refresh();
        $this->assertSame('completed', Transaction::query()->find($first)->status);
        $this->assertSame(2, (int) $product->buyed);
        $this->assertSame(5, (int) $buyer->loyalty_points);
        $this->assertSame(1, (int) $buyer->loyalty_completed_orders);

        $this->post('/admin/orders/cancel/' . $first, ['reason' => 'muon huy'])
            ->assertSessionHas('message_fail', 'Không thể hủy đơn hàng đã bàn giao cho shipper hoặc đã hoàn thành.');
        $this->get('/admin/orders/cancel/' . $first)->assertNotFound();
        $this->post('/admin/orders/del')->assertSessionHas('message_fail');
        $this->assertNotNull(Transaction::query()->find($first));

        $open = $this->makeOrder($buyer, $product, [
            'user_name' => $token . ' open',
            'amount' => 20000,
            'status' => 0,
            'qty' => 1,
            'line_amount' => 20000,
        ]);
        $stockBefore = (int) ProductVariant::query()->where('product_id', $product->id)->value('stock');
        $this->post('/admin/orders/cancel/' . $open, ['reason' => ''])->assertSessionHas('message_success');
        $this->assertSame('cancelled', Transaction::query()->find($open)->status);
        $this->assertSame($stockBefore + 1, (int) ProductVariant::query()->where('product_id', $product->id)->value('stock'));

        $lineOrder = $this->makeOrder($buyer, $product, [
            'user_name' => $token . ' line',
            'amount' => 20000,
            'status' => 0,
            'qty' => 1,
            'line_amount' => 20000,
        ]);
        $lineId = (int) DB::table('order_items')->where('order_id', $lineOrder)->value('id');
        $stockBeforeLine = (int) ProductVariant::query()->where('product_id', $product->id)->value('stock');
        $this->post('/admin/orders/deldetail/' . $lineId)->assertSessionHas('message_success', 'Xóa thành công');
        $this->assertNull(DB::table('order_items')->where('id', $lineId)->first());
        $this->assertSame(0.0, (float) Transaction::query()->find($lineOrder)->amount);
        $this->assertSame($stockBeforeLine + 1, (int) ProductVariant::query()->where('product_id', $product->id)->value('stock'));
        $this->get('/admin/orders/deldetail/' . $lineId)->assertNotFound();
        $this->get('/admin/orders/pending_count')->assertOk()->assertJson(['ok' => true]);

        for ($i = 1; $i <= 11; $i++) {
            $this->makeOrder($buyer, $product, [
                'user_name' => 'B10 Page ' . $i,
                'user_phone' => '090000' . $i,
                'amount' => 1000,
                'status' => 1,
                'qty' => 0,
                'line_amount' => 0,
                'skip_line' => true,
            ]);
        }
        $this->get('/admin/orders?q=' . urlencode('B10 Page'))
            ->assertOk()
            ->assertSee('11 đơn')
            ->assertSee('B10 Page 11');
        $this->get('/admin/orders/10?q=' . urlencode('B10 Page'))
            ->assertOk()
            ->assertSee('B10 Page 1');
    }

    public function test_customer_profile_orders_and_delete_and_csrf(): void
    {
        $admin = $this->makeAdmin(Admin::ROLE_ADMIN);
        $this->actingAs($admin, 'admin');
        $buyer = $this->makeBuyer();
        $product = $this->makeProduct(3, 1000);
        $orderId = $this->makeOrder($buyer, $product, [
            'user_name' => $buyer->name,
            'amount' => 30000,
            'status' => 0,
            'qty' => 1,
            'line_amount' => 30000,
        ]);
        DB::table('user_addresses')->insert([
            'user_id' => $buyer->id,
            'note' => 'Nha B10',
            'province_id' => '01',
            'district_id' => '001',
            'ward_id' => '00001',
            'address_line' => '123 Duong B10',
            'is_default' => 1,
            'created_at' => now(),
        ]);
        DB::table('user_phones')->insert([
            'user_id' => $buyer->id,
            'label' => 'Di dong',
            'phone_number' => '0988111222',
            'is_default' => 1,
            'created_at' => now(),
        ]);

        $this->get('/admin/users?q=' . urlencode($buyer->email))
            ->assertOk()
            ->assertSee($buyer->name);
        $this->get('/admin/users/view/' . $buyer->id)
            ->assertOk()
            ->assertSee($buyer->email)
            ->assertSee('0988111222')
            ->assertSee('123 Duong B10')
            ->assertSee('Thành viên');
        $this->get('/admin/users/order/' . $buyer->id)->assertOk()->assertSee((string) $orderId);
        $this->get('/admin/users/detail/' . $orderId)
            ->assertOk()
            ->assertSee('30.000')
            ->assertSee('Chỉ xem đối chiếu');

        $this->actingAs($this->makeAdmin(Admin::ROLE_USER), 'admin');
        $this->get('/admin/users/view/' . $buyer->id)->assertOk()->assertSee($buyer->email);
        $this->get('/admin/orders/detail/' . $orderId)->assertRedirect(route('admin.home'));

        $this->actingAs($admin, 'admin');
        $this->post('/admin/users/del/' . $buyer->id)->assertRedirect(url('admin/users'));
        $this->assertNull(User::query()->find($buyer->id));
    }

    public function test_order_and_customer_post_without_csrf_is_rejected(): void
    {
        $this->app->bind(ValidateCsrfToken::class, fn ($app) => new class($app, $app['encrypter']) extends ValidateCsrfToken
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });
        $this->actingAs($this->makeAdmin(Admin::ROLE_ADMIN), 'admin');
        $buyer = $this->makeBuyer();
        $this->post('/admin/orders/cancel/1', ['reason' => 'x'])->assertStatus(419);
        $this->post('/admin/users/del/' . $buyer->id)->assertStatus(419);
        $this->assertNotNull(User::query()->find($buyer->id));
    }

    private function makeBuyer(): User
    {
        $user = User::query()->create([
            'name' => 'B10 Buyer ' . Str::lower(Str::random(4)),
            'email' => 'b10buyer.' . Str::lower(Str::random(6)) . '@example.test',
            'password_hash' => LegacyPassword::hash('BuyerPass1'),
            'loyalty_points' => 0,
            'loyalty_lifetime_spend' => 0,
            'loyalty_completed_orders' => 0,
            'loyalty_tier' => 'member',
            'created_at' => now(),
        ]);
        $this->userIds[] = (int) $user->id;

        return $user;
    }

    private function makeProduct(int $stock, int $price): Product
    {
        $product = Product::query()->create([
            'category_id' => (int) DB::table('categories')->orderBy('id')->value('id'),
            'name' => 'B10 Product ' . Str::lower(Str::random(5)),
            'content' => '',
            'list_price' => $price,
            'discount_amount' => 0,
            'code' => 'B10' . strtoupper(Str::random(6)),
            'status' => 'active',
            'view_count' => 0,
            'sold_count' => 0,
            'rating_total' => 0,
            'rating_count' => 0,
        ]);
        ProductVariant::query()->create([
            'product_id' => $product->id,
            'sku' => 'B10-' . $product->id . '-' . Str::lower(Str::random(4)),
            'color_name' => 'Đen',
            'size_name' => 'M',
            'cost_price' => 0,
            'stock' => $stock,
            'min_stock' => 1,
            'status' => 'active',
        ]);
        $this->productIds[] = (int) $product->id;

        return $product;
    }

    /** @param  array<string, mixed>  $data */
    private function makeOrder(User $buyer, Product $product, array $data): int
    {
        $enum = [0 => 'pending', 1 => 'confirmed', 2 => 'packed', 3 => 'completed', 4 => 'cancelled'][(int) $data['status']] ?? (string) $data['status'];
        $id = (int) DB::table('orders')->insertGetId([
            'status' => $enum,
            'user_id' => $buyer->id,
            'customer_name' => $data['user_name'],
            'customer_email' => $buyer->email,
            'customer_phone' => $data['user_phone'] ?? '0900111000',
            'shipping_address' => 'Ha Noi',
            'note' => '',
            'subtotal' => $data['amount'],
            'discount_amount' => 0,
            'shipping_fee' => 0,
            'total' => $data['amount'],
            'voucher_code' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->orderIds[] = $id;
        if (empty($data['skip_line'])) {
            $variantId = (int) ProductVariant::query()->where('product_id', $product->id)->value('id');
            $qty = (int) $data['qty'];
            $line = (float) $data['line_amount'];
            DB::table('order_items')->insert([
                'order_id' => $id,
                'product_id' => $product->id,
                'variant_id' => $variantId,
                'product_name' => $product->name,
                'sku' => 'B10-' . $variantId,
                'size_name' => 'M',
                'color_name' => 'Đen',
                'qty' => $qty,
                'unit_price' => $qty > 0 ? $line / $qty : $line,
                'line_subtotal' => $line,
                'cost_price' => 0,
            ]);
        }

        return $id;
    }
}
