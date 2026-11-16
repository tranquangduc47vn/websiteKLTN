<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Supplier;
use App\Models\User;
use App\Support\LegacyPassword;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InventoryTest extends AdminTestCase
{
    /** @var list<int> */
    private array $userIds = [];

    /** @var list<int> */
    private array $productIds = [];

    /** @var list<int> */
    private array $supplierIds = [];

    /** @var list<int> */
    private array $receiptIds = [];

    /** @var list<int> */
    private array $orderIds = [];

    protected function tearDown(): void
    {
        if ($this->receiptIds !== []) {
            DB::table('stock_receipt_items')->whereIn('receipt_id', $this->receiptIds)->delete();
            DB::table('stock_receipts')->whereIn('id', $this->receiptIds)->delete();
        }
        if ($this->orderIds !== []) {
            DB::table('stock_movements')->where('reference_type', 'order')->whereIn('reference_id', $this->orderIds)->delete();
            DB::table('order_items')->whereIn('order_id', $this->orderIds)->delete();
            DB::table('orders')->whereIn('id', $this->orderIds)->delete();
        }
        if ($this->productIds !== []) {
            $variantIds = DB::table('product_variants')->whereIn('product_id', $this->productIds)->pluck('id');
            DB::table('stock_movements')->whereIn('variant_id', $variantIds)->delete();
            DB::table('product_variants')->whereIn('product_id', $this->productIds)->delete();
            DB::table('products')->whereIn('id', $this->productIds)->delete();
        }
        if ($this->supplierIds !== []) {
            DB::table('suppliers')->whereIn('id', $this->supplierIds)->delete();
        }
        if ($this->userIds !== []) {
            DB::table('user_saved_carts')->whereIn('user_id', $this->userIds)->delete();
            DB::table('users')->whereIn('id', $this->userIds)->delete();
        }
        parent::tearDown();
    }

    public function test_guest_customer_and_permissions(): void
    {
        $this->get('/admin/inventory')->assertRedirect(route('admin.login'));
        $this->get('/admin/suppliers')->assertRedirect(route('admin.login'));
        $this->get('/admin/receipts')->assertRedirect(route('admin.login'));

        $customer = User::query()->create([
            'name' => 'B11 Guard',
            'email' => 'b11guard.' . Str::lower(Str::random(6)) . '@example.test',
            'password_hash' => LegacyPassword::hash('CustomerPass1'),
            'created_at' => now(),
        ]);
        $this->userIds[] = (int) $customer->id;
        $this->actingAs($customer, 'web');
        $this->get('/admin/inventory/adjust/1')->assertRedirect(route('admin.login'));
        $this->assertFalse(Auth::guard('admin')->check());

        $this->withSession(['login' => ['id' => 1, 'name' => 'Fake', 'email' => 'fake@example.test', 'level' => 0]]);
        $this->get('/admin/stock-movements')->assertRedirect(route('admin.login'));

        $this->actingAs($this->makeAdmin(Admin::ROLE_USER), 'admin');
        $this->get('/admin/inventory')->assertOk()->assertSee('Tồn kho theo biến thể');
        $this->get('/admin/suppliers')->assertOk();
        $this->get('/admin/suppliers/add')->assertRedirect(url('admin/suppliers'));
        $this->post('/admin/stock-receipts/add', ['supplier_id' => 1, 'variant_id' => [1], 'qty' => [1], 'unit_cost' => [10]])
            ->assertRedirect(url('admin/stock-receipts'));
        $this->post('/admin/inventory/adjust/1?level=0', [
            'variant_id' => 1,
            'new_qty' => 99,
            'note' => 'nope',
        ])->assertRedirect(url('admin/inventory'));
        $this->get('/admin/inventory/adjust/1')->assertRedirect(url('admin/inventory'))
            ->assertSessionHas('message_fail', 'Bạn không có quyền kiểm kê tồn kho.');
    }

    public function test_inventory_filter_pagination_low_stock_and_adjust(): void
    {
        $admin = $this->makeAdmin(Admin::ROLE_ADMIN);
        $this->actingAs($admin, 'admin');
        $token = 'B11' . Str::upper(Str::random(4));
        $product = $this->makeProduct($token . '-BASE', 4, 5);
        $variant = ProductVariant::query()->where('product_id', $product->id)->first();
        $low = $this->addVariant($product, $token . '-LOW', 2, 5, 'S');
        $out = $this->addVariant($product, $token . '-OUT', 0, 5, 'L');
        $ok = $this->addVariant($product, $token . '-OK', 20, 5, 'XL');

        $this->get('/admin/inventory?sku=' . $token . '-LOW&stock=low')
            ->assertOk()
            ->assertSee($token . '-LOW')
            ->assertDontSee($token . '-OUT')
            ->assertDontSee($token . '-OK');
        $this->get('/admin/inventory/low-stock?sku=' . $token . '-LOW')
            ->assertOk()
            ->assertSee($token . '-LOW');
        $this->get('/admin/inventory?sku=' . $token . '-OUT&stock=out')
            ->assertOk()
            ->assertSee($token . '-OUT');
        $this->get('/admin/receipts/search_variants?q=' . $token . '-LOW')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonFragment(['sku' => $token . '-LOW']);

        for ($i = 1; $i <= 21; $i++) {
            $this->addVariant($product, $token . '-P' . str_pad((string) $i, 2, '0', STR_PAD_LEFT), 3, 5, 'P' . $i);
        }
        $this->get('/admin/inventory?sku=' . $token . '-P')
            ->assertOk()
            ->assertSee($token . '-P21');
        $this->get('/admin/inventory/index/20?sku=' . $token . '-P')
            ->assertOk()
            ->assertSee($token . '-P01');

        $this->post('/admin/inventory/adjust/' . $variant->id, [
            'variant_id' => $variant->id,
            'new_qty' => 9,
            'note' => 'Kiem ke B11',
        ])->assertRedirect(url('admin/inventory'))->assertSessionHas('message_success');
        $variant->refresh();
        $product->refresh();
        $this->assertSame(9, (int) $variant->stock);
        $this->assertSame(9 + 2 + 0 + 20 + (21 * 3), (int) $product->quantity);
        $movement = DB::table('stock_movements')->where('variant_id', $variant->id)->where('movement_type', 'adjust')->first();
        $this->assertNotNull($movement);
        $this->assertSame(5, (int) $movement->qty_change);
        $this->assertSame(4, (int) $movement->before_qty);
        $this->assertSame(9, (int) $movement->after_qty);
        $this->assertSame('adjustment', $movement->reference_type);

        $this->post('/admin/inventory/adjust/' . $variant->id, [
            'variant_id' => $variant->id,
            'new_qty' => 9,
            'note' => 'Khong doi',
        ])->assertSessionHas('message_fail', 'Tồn kho không thay đổi.');
        $this->assertSame(9, (int) $variant->fresh()->stock);

        $this->actingAs($this->makeAdmin(Admin::ROLE_MOD), 'admin');
        $this->get('/admin/inventory/adjust/' . $low->id)->assertOk()->assertSee('Bạn chỉ được xem kiểm kê');
        $this->post('/admin/inventory/adjust/' . $low->id, [
            'variant_id' => $low->id,
            'new_qty' => 1,
            'note' => 'Mod khong duoc',
        ])->assertSessionHas('message_fail', 'Chỉ Admin mới được điều chỉnh số lượng tồn. Mod chỉ xem kiểm kê.');
        $this->assertSame(2, (int) $low->fresh()->stock);
        $this->assertSame(0, (int) $out->stock);
    }

    public function test_supplier_and_receipt_change_stock_once(): void
    {
        $admin = $this->makeAdmin(Admin::ROLE_ADMIN);
        $this->actingAs($admin, 'admin');
        $token = 'B11S' . Str::upper(Str::random(4));
        $product = $this->makeProduct($token, 4, 5);
        $variant = ProductVariant::query()->where('product_id', $product->id)->first();

        $this->post('/admin/suppliers/add', [
            'code' => $token,
            'name' => 'NCC ' . $token,
            'contact_name' => 'Lan',
            'phone' => '0900111222',
            'email' => 'b11sup@example.test',
            'address' => 'Ha Noi',
            'note' => 'test',
        ])->assertRedirect(url('admin/suppliers'))->assertSessionHas('message_success', 'Đã thêm nhà cung cấp.');
        $supplierId = (int) Supplier::query()->where('code', $token)->value('id');
        $this->supplierIds[] = $supplierId;
        $this->get('/admin/suppliers')->assertOk()->assertSee('NCC ' . $token)->assertSee('0900111222');

        $this->post('/admin/suppliers/add', ['code' => '', 'name' => ''])
            ->assertSessionHas('message_fail', 'Vui lòng nhập mã nhà cung cấp.');

        $this->post('/admin/stock-receipts/add', [
            'supplier_id' => $supplierId,
            'note' => 'Nhap B11',
            'variant_id' => [$variant->id, $variant->id],
            'qty' => [2, 1],
            'unit_cost' => [12000, 12000],
        ])->assertSessionHas('message_success');

        $receipt = DB::table('stock_receipts')->where('created_by', $admin->id)->orderByDesc('id')->first();
        $this->assertNotNull($receipt);
        $this->receiptIds[] = (int) $receipt->id;
        $this->assertSame('confirmed', $receipt->status);
        $this->assertSame(3, (int) $receipt->total_qty);
        $variant->refresh();
        $product->refresh();
        $this->assertSame(7, (int) $variant->stock);
        $this->assertEquals(12000, (float) $variant->cost_price);
        $this->assertSame(7, (int) $product->quantity);
        $in = DB::table('stock_movements')->where('reference_type', 'receipt')->where('reference_id', $receipt->id)->first();
        $this->assertSame(3, (int) $in->qty_change);
        $this->assertSame(4, (int) $in->before_qty);
        $this->assertSame(7, (int) $in->after_qty);

        $this->post('/admin/stock-receipts/confirm', ['receipt_id' => $receipt->id])
            ->assertSessionHas('message_fail', 'Phiếu đã xác nhận hoặc đã hủy, không thể xác nhận lại.');
        $this->assertSame(7, (int) $variant->fresh()->stock);
        $this->post('/admin/stock-receipts/cancel', ['receipt_id' => $receipt->id])
            ->assertSessionHas('message_fail', 'Chỉ hủy được phiếu nháp.');
        $this->assertSame(7, (int) $variant->fresh()->stock);

        $this->get('/admin/receipts?code=' . $receipt->receipt_code)->assertOk()->assertSee($receipt->receipt_code);
        $this->get('/admin/stock-receipts/view/' . $receipt->id)->assertOk()->assertSee('12.000');
        $this->get('/admin/stock-movements?sku=' . $token . '&type=in')->assertOk()->assertSee('Nhập kho ' . $receipt->receipt_code);

        $draftId = (int) DB::table('stock_receipts')->insertGetId([
            'receipt_code' => 'B11DRAFT' . Str::upper(Str::random(4)),
            'supplier_id' => $supplierId,
            'status' => 'draft',
            'note' => '',
            'total_qty' => 1,
            'total_amount' => 5000,
            'receipt_date' => date('Y-m-d'),
            'created_by' => $admin->id,
            'confirmed_by' => null,
            'confirmed_at' => null,
            'cancelled_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->receiptIds[] = $draftId;
        DB::table('stock_receipt_items')->insert([
            'receipt_id' => $draftId,
            'variant_id' => $variant->id,
            'sku' => (string) $variant->sku,
            'size_name' => 'M',
            'color_name' => 'Đen',
            'qty' => 1,
            'unit_cost' => 5000,
            'line_total' => 5000,
        ]);
        $this->post('/admin/stock-receipts/confirm', ['receipt_id' => $draftId])->assertSessionHas('message_success');
        $this->assertSame(8, (int) $variant->fresh()->stock);
        $this->post('/admin/stock-receipts/confirm', ['receipt_id' => $draftId])->assertSessionHas('message_fail');
        $this->assertSame(8, (int) $variant->fresh()->stock);

        $this->post('/admin/stock-receipts/add', [
            'supplier_id' => '',
            'variant_id' => [$variant->id],
            'qty' => [1],
            'unit_cost' => [1000],
        ])->assertSessionHas('message_fail', 'Vui lòng chọn nhà cung cấp trước khi nhập kho.');
        $this->post('/admin/stock-receipts/add', [
            'supplier_id' => $supplierId,
            'variant_id' => [$variant->id],
            'qty' => [0],
            'unit_cost' => [1000],
        ])->assertSessionHas('message_fail');

        $this->actingAs($this->makeAdmin(Admin::ROLE_MOD), 'admin');
        $this->get('/admin/stock-receipts/add')->assertOk()->assertSee('Nhập kho');
    }

    public function test_checkout_decreases_once_and_cancel_restores_once(): void
    {
        $buyer = User::query()->create([
            'name' => 'B11 Buyer',
            'email' => 'b11buyer.' . Str::lower(Str::random(6)) . '@example.test',
            'password_hash' => LegacyPassword::hash('BuyerPass1'),
            'created_at' => now(),
        ]);
        $this->userIds[] = (int) $buyer->id;
        $product = $this->makeProduct('B11CHK' . Str::upper(Str::random(3)), 6, 5);
        $variant = ProductVariant::query()->where('product_id', $product->id)->first();

        $this->actingAs($buyer, 'web');
        $this->post('/gio-hang/add/' . $product->id, ['qty' => 2, 'size' => 'M', 'color' => 'Đen'])
            ->assertRedirect();
        $this->post('/thanh-toan', [
            'name' => $buyer->name,
            'email' => $buyer->email,
            'phone' => '0900111222',
            'address' => '123 Duong Test',
            'payment' => 'COD',
            'price' => 1,
            'subtotal' => 1,
            'total' => 1,
        ])->assertRedirect();

        $orderId = (int) DB::table('orders')->where('user_id', $buyer->id)->orderByDesc('id')->value('id');
        $this->orderIds[] = $orderId;
        $this->assertSame(4, (int) $variant->fresh()->stock);
        $this->assertSame(4, (int) $product->fresh()->quantity);
        $outs = DB::table('stock_movements')->where('reference_type', 'order')->where('reference_id', $orderId)->count();
        $this->assertSame(1, $outs);

        $this->post('/thanh-toan', [
            'name' => $buyer->name,
            'email' => $buyer->email,
            'phone' => '0900111222',
            'address' => '123 Duong Test',
            'payment' => 'COD',
        ]);
        $this->assertSame(1, DB::table('orders')->where('user_id', $buyer->id)->count());
        $this->assertSame(4, (int) $variant->fresh()->stock);

        $this->actingAs($this->makeAdmin(Admin::ROLE_ADMIN), 'admin');
        $this->post('/admin/orders/cancel/' . $orderId, ['reason' => 'B11'])->assertSessionHas('message_success');
        $this->assertSame(6, (int) $variant->fresh()->stock);
        $this->assertSame(6, (int) $product->fresh()->quantity);
        $this->post('/admin/orders/cancel/' . $orderId, ['reason' => 'B11 lan 2']);
        $this->assertSame(6, (int) $variant->fresh()->stock);
        $this->assertSame('cancelled', DB::table('orders')->where('id', $orderId)->value('status'));
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
        $this->post('/admin/inventory/adjust/1', ['variant_id' => 1, 'new_qty' => 1, 'note' => 'x'])->assertStatus(419);
        $this->post('/admin/suppliers/add', ['code' => 'X', 'name' => 'Y'])->assertStatus(419);
        $this->post('/admin/stock-receipts/add', ['supplier_id' => 1])->assertStatus(419);
        $this->post('/admin/stock-receipts/confirm', ['receipt_id' => 1])->assertStatus(419);
    }

    private function makeProduct(string $sku, int $stock, int $min): Product
    {
        $product = Product::query()->create([
            'category_id' => (int) DB::table('categories')->orderBy('id')->value('id'),
            'name' => 'B11 Product ' . $sku,
            'content' => '',
            'list_price' => 100000,
            'discount_amount' => 0,
            'code' => 'B11' . strtoupper(Str::random(6)),
            'status' => 'active',
            'view_count' => 0,
            'sold_count' => 0,
            'rating_total' => 0,
            'rating_count' => 0,
        ]);
        $this->addVariant($product, $sku, $stock, $min);
        $this->productIds[] = (int) $product->id;

        return $product;
    }

    private function addVariant(Product $product, string $sku, int $stock, int $min, string $size = 'M'): ProductVariant
    {
        return ProductVariant::query()->create([
            'product_id' => $product->id,
            'sku' => $sku,
            'color_name' => 'Đen',
            'size_name' => $size,
            'cost_price' => 0,
            'stock' => $stock,
            'min_stock' => $min,
            'status' => 'active',
        ]);
    }
}
