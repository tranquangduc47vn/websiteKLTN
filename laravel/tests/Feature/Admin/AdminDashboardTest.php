<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\User;
use App\Services\AdminDashboardService;
use App\Support\LegacyPassword;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdminDashboardTest extends AdminTestCase
{
    public function test_guest_and_customer_cannot_open_dashboard(): void
    {
        $this->get('/admin')->assertRedirect(route('admin.login'));
        $this->get('/admin/home')->assertRedirect(route('admin.login'));

        $customer = User::query()->create([
            'name' => 'B8 Customer',
            'email' => 'b8cust.' . Str::lower(Str::random(6)) . '@example.test',
            'password_hash' => LegacyPassword::hash('CustomerPass1'),
            'created_at' => now(),
        ]);
        $this->actingAs($customer, 'web');
        $this->get('/admin')->assertRedirect(route('admin.login'));
        $this->assertFalse(Auth::guard('admin')->check());
        $this->assertTrue(Auth::guard('web')->check());

        $this->get('/admin/logout')->assertRedirect(route('admin.login'));
        $this->assertTrue(Auth::guard('web')->check());
        $this->assertFalse(Auth::guard('admin')->check());
    }

    public function test_admin_login_uses_guard_and_does_not_store_login_session(): void
    {
        $admin = $this->makeAdmin(Admin::ROLE_ADMIN);

        $this->post('/admin/login', [
            'email' => $admin->email,
            'password' => self::PASSWORD,
        ])->assertRedirect(route('admin.home'));

        $this->get('/admin')->assertOk();
        $this->assertNull(session('login'));
        $this->assertArrayNotHasKey('password', session()->all());
        $this->assertTrue(Auth::guard('admin')->check());

        $this->get('/admin')
            ->assertOk()
            ->assertSee('Tổng quan kinh doanh')
            ->assertSee('Bán hàng')
            ->assertSee('Doanh thu')
            ->assertSee('Đơn đặt hàng')
            ->assertSee('Voucher');
    }

    public function test_fake_session_login_cannot_bypass_guard(): void
    {
        $this->withSession([
            'login' => ['id' => 1, 'name' => 'Fake', 'email' => 'fake@example.test', 'level' => 0],
        ]);

        $this->get('/admin')->assertRedirect(route('admin.login'));
        $this->assertFalse(Auth::guard('admin')->check());
    }

    public function test_role_user_is_blocked_from_staff_routes_and_hides_revenue(): void
    {
        $user = $this->makeAdmin(Admin::ROLE_USER);
        $this->actingAs($user, 'admin');

        $this->get('/admin')
            ->assertOk()
            ->assertSee('Tổng quan kinh doanh')
            ->assertSee('Tồn kho')
            ->assertDontSee('Doanh thu')
            ->assertDontSee('Đơn đặt hàng')
            ->assertDontSee('fa-ticket', false);

        $this->get('/admin/products')
            ->assertRedirect(route('admin.home'))
            ->assertSessionHas('message_fail', 'Bạn không có quyền truy cập khu vực này!');
        $this->get('/admin/voucher')->assertRedirect(route('admin.home'));
        $this->get('/admin/orders')->assertRedirect(route('admin.home'));

        $this->get('/admin/products?level=0')->assertRedirect(route('admin.home'));
    }

    public function test_mod_cannot_open_voucher_but_admin_passes_permission_gate(): void
    {
        $this->actingAs($this->makeAdmin(Admin::ROLE_MOD), 'admin');
        $this->get('/admin/voucher')->assertRedirect(route('admin.home'));
        $this->get('/admin/orders')->assertOk()->assertSee('Danh sách đơn');

        $this->actingAs($this->makeAdmin(Admin::ROLE_ADMIN), 'admin');
        $this->get('/admin/voucher')->assertOk()->assertSee('Thêm voucher');
        $this->get('/admin/products')->assertOk()->assertSee('Quản lý sản phẩm');
    }

    public function test_dashboard_revenue_includes_completed_orders_in_period(): void
    {
        $admin = $this->makeAdmin(Admin::ROLE_ADMIN);
        $this->actingAs($admin, 'admin');
        $service = app(AdminDashboardService::class);
        $before = $service->data($admin, 'today');
        $beforeRevenue = 0;
        foreach ($before['kpi_groups'][0]['items'] as $kpi) {
            if ($kpi['key'] === 'revenue_today') {
                $beforeRevenue = (float) $kpi['value'];
            }
        }

        DB::table('orders')->insert([
            'status' => 'completed',
            'user_id' => null,
            'customer_name' => 'B8 Buyer',
            'customer_email' => 'b8buyer@example.test',
            'customer_phone' => '0900111222',
            'shipping_address' => 'HN',
            'note' => '',
            'subtotal' => 123000,
            'discount_amount' => 0,
            'shipping_fee' => 0,
            'total' => 123000,
            'voucher_code' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $after = $service->data($admin, 'today');
        $afterRevenue = 0;
        foreach ($after['kpi_groups'][0]['items'] as $kpi) {
            if ($kpi['key'] === 'revenue_today') {
                $afterRevenue = (float) $kpi['value'];
            }
        }
        $this->assertEquals($beforeRevenue + 123000, $afterRevenue);

        $this->get('/admin/home?period=today')
            ->assertOk()
            ->assertSee(number_format($afterRevenue, 0, ',', '.') . ' ₫');
    }

    public function test_admin_logout_clears_guard_and_keeps_customer_cart(): void
    {
        $admin = $this->makeAdmin();
        $this->actingAs($admin, 'admin');
        $cart = ['row' => ['rowid' => 'row', 'id' => 1, 'qty' => 1, 'price' => 10, 'name' => 'A', 'subtotal' => 10, 'options' => []]];
        $this->withSession(['custom_cart' => $cart]);

        $this->get('/admin')->assertOk()
            ->assertSee('method="post"', false)
            ->assertSee('name="_token"', false)
            ->assertDontSee('href="' . route('admin.logout') . '"', false);

        $this->get('/admin/logout')->assertRedirect(route('admin.login'));
        $this->assertTrue(Auth::guard('admin')->check());

        $this->post('/admin/logout')->assertRedirect(route('admin.login'));
        $this->assertFalse(Auth::guard('admin')->check());
        $this->assertNull(session('login'));
        $this->get('/admin')->assertRedirect(route('admin.login'));
        $this->assertSame($cart, session('custom_cart'));
    }

    public function test_admin_logout_post_without_csrf_is_rejected(): void
    {
        $this->app->bind(ValidateCsrfToken::class, fn ($app) => new class($app, $app['encrypter']) extends ValidateCsrfToken
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });

        $admin = $this->makeAdmin();
        $this->actingAs($admin, 'admin');
        $this->post('/admin/logout')->assertStatus(419);
        $this->assertTrue(Auth::guard('admin')->check());
    }

    public function test_admin_login_post_without_csrf_is_rejected(): void
    {
        $this->app->bind(ValidateCsrfToken::class, fn ($app) => new class($app, $app['encrypter']) extends ValidateCsrfToken
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });

        $admin = $this->makeAdmin();
        $this->post('/admin/login', [
            'email' => $admin->email,
            'password' => self::PASSWORD,
        ])->assertStatus(419);
        $this->assertFalse(Auth::guard('admin')->check());
    }
}
