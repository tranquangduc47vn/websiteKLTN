<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Support\LegacyPassword;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StaffTest extends AdminTestCase
{
    /** @var list<int> */
    private array $extraIds = [];

    protected function tearDown(): void
    {
        if ($this->extraIds !== []) {
            DB::table('admin')->whereIn('id', $this->extraIds)->delete();
        }
        DB::table('admin')->where('email', 'like', 'b16staff.%')->delete();
        parent::tearDown();
    }

    public function test_guest_fake_session_and_lower_levels_cannot_open_staff(): void
    {
        $this->get('/admin/admin')->assertRedirect('/admin/login');
        $this->withSession(['login' => ['id' => 1, 'name' => 'Fake', 'email' => 'fake@example.test', 'level' => 0]])
            ->get('/admin/admin')->assertRedirect('/admin/login');

        $this->actingAs($this->makeAdmin(Admin::ROLE_USER), 'admin');
        $this->get('/admin/admin')->assertRedirect('/admin');
        $this->get('/admin/admin?level=0')->assertRedirect('/admin');

        Auth::guard('admin')->logout();
        $this->actingAs($this->makeAdmin(Admin::ROLE_MOD), 'admin');
        $this->get('/admin/admin?level=0')->assertRedirect('/admin');
        $this->post('/admin/admin/add', $this->payload())->assertRedirect('/admin');
    }

    public function test_admin_creates_edits_and_cannot_delete_a_level_zero_account(): void
    {
        $admin = $this->makeAdmin(Admin::ROLE_ADMIN);
        $this->actingAs($admin, 'admin');
        $this->get('/admin/admin')->assertOk()->assertSee('Thêm mới')->assertDontSee('$2y$');

        $email = 'b16staff.' . Str::lower(Str::random(6)) . '@example.test';
        $this->post('/admin/admin/add', $this->payload(['email' => $email, 'level' => 99]))
            ->assertRedirect()
            ->assertSessionHas('message_fail', 'Phân quyền không hợp lệ.');
        $this->assertNull(DB::table('admin')->where('email', $email)->first());

        $this->post('/admin/admin/add', $this->payload([
            'email' => $email,
            'password' => 'short1',
            're_password' => 'short1',
        ]))->assertSessionHas('message_fail', 'Mật khẩu phải có ít nhất 8 ký tự.');

        $this->post('/admin/admin/add', $this->payload(['email' => $email, 'level' => Admin::ROLE_MOD]))
            ->assertRedirect('/admin/admin')
            ->assertSessionHas('message_success', 'Thêm thành viên thành công');

        $row = DB::table('admin')->where('email', $email)->first();
        $this->assertNotNull($row);
        $this->extraIds[] = (int) $row->id;
        $this->assertSame(Admin::ROLE_MOD, (int) $row->level);
        $this->assertTrue(LegacyPassword::verify('B16-Staff#1', (string) $row->password)['verified']);
        $this->assertStringNotContainsString('B16-Staff#1', (string) $row->password);

        $hash = (string) $row->password;
        $this->post('/admin/admin/edit/' . $row->id, [
            'name' => 'B16 Da sua',
            'email' => $email,
            'password' => '',
            're_password' => '',
            'level' => Admin::ROLE_USER,
        ])->assertRedirect('/admin/admin');
        $updated = DB::table('admin')->where('id', $row->id)->first();
        $this->assertSame('B16 Da sua', $updated->name);
        $this->assertSame(Admin::ROLE_USER, (int) $updated->level);
        $this->assertSame($hash, (string) $updated->password);

        $protected = $this->makeAdmin(Admin::ROLE_ADMIN);
        $this->post('/admin/admin/del/' . $protected->id)
            ->assertRedirect('/admin/admin')
            ->assertSessionHas('message_fail', 'Hệ thống bảo vệ: Không thể xóa tài khoản Quản trị tối cao (ADMIN)!');
        $this->assertNotNull(DB::table('admin')->where('id', $protected->id)->first());

        $this->post('/admin/admin/del/' . $row->id)->assertRedirect('/admin/admin')
            ->assertSessionHas('message_success', 'Xóa admin thành công');
        $this->assertNull(DB::table('admin')->where('id', $row->id)->first());
        $this->get('/admin/admin/del/' . $row->id)->assertNotFound();
        $this->post('/admin/admin/edit/99999999')->assertRedirect()->assertSessionHas('message_fail', 'Thành viên không tồn tại');
    }

    public function test_staff_post_without_csrf_is_rejected(): void
    {
        $this->app->bind(ValidateCsrfToken::class, fn ($app) => new class($app, $app['encrypter']) extends ValidateCsrfToken
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });
        $this->actingAs($this->makeAdmin(Admin::ROLE_ADMIN), 'admin');
        $this->post('/admin/admin/add', $this->payload())->assertStatus(419);
        $this->post('/admin/admin/del/1')->assertStatus(419);
    }

    /** @param  array<string, mixed>  $overrides */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'B16 Staff',
            'email' => 'b16staff.' . Str::lower(Str::random(6)) . '@example.test',
            'password' => 'B16-Staff#1',
            're_password' => 'B16-Staff#1',
            'level' => Admin::ROLE_MOD,
        ], $overrides);
    }
}
