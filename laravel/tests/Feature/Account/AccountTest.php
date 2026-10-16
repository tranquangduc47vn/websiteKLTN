<?php

namespace Tests\Feature\Account;

use App\Models\User;
use App\Models\UserAddress;
use App\Models\UserPhone;
use App\Support\LegacyPassword;
use App\Support\Storefront\Product as ProductDisplay;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AccountTest extends AccountTestCase
{
    public function test_guest_is_redirected_from_account_routes_to_login(): void
    {
        $this->get('/user')->assertRedirect(url('dang-nhap'));
        $this->assertSame(url('user'), session('redirect_back'));
        $this->get('/user/order_detail/1')->assertRedirect(url('dang-nhap'));
        $this->post('/user/profile_update', ['name' => 'X'])->assertRedirect(url('dang-nhap'));
    }

    public function test_authenticated_user_sees_account_dashboard(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user, 'web');

        $this->get('/user')
            ->assertOk()
            ->assertSee('Thông tin tài khoản')
            ->assertSee($user->name)
            ->assertSee($user->email)
            ->assertSee('Lịch sử đơn hàng')
            ->assertSee('Bạn chưa đặt đơn hàng nào')
            ->assertSee('name="_token"', false)
            ->assertSee('window.CI_CSRF', false)
            ->assertDontSee('jm-sidebar', false);
    }

    public function test_profile_updates_name_only_and_rejects_empty(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user, 'web');

        $this->post('/user/profile_update', ['name' => 'Nguyễn Văn B7'])
            ->assertRedirect(url('user') . '#jm-account-profile');

        $user->refresh();
        $this->assertSame('Nguyễn Văn B7', $user->name);
        $this->assertSame('b5cart.', substr($user->email, 0, 7));

        $this->from('/user')->post('/user/profile_update', ['name' => ''])
            ->assertRedirect(url('user') . '#jm-account-profile');
        $this->assertSame('Nguyễn Văn B7', $user->fresh()->name);
    }

    public function test_password_change_requires_current_and_hashes_bcrypt(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user, 'web');

        $this->post('/user/password_change', [
            'old_password' => 'wrong-pass-1',
            'password' => 'NewPass99',
            're_password' => 'NewPass99',
        ])->assertRedirect(url('user') . '#jm-account-profile');
        $this->assertTrue(Hash::check(self::PASSWORD, $user->fresh()->password));

        $this->post('/user/password_change', [
            'old_password' => self::PASSWORD,
            'password' => 'NewPass99',
            're_password' => 'NewPass99',
        ])->assertRedirect(url('user') . '#jm-account-profile');

        $hash = (string) $user->fresh()->password;
        $this->assertTrue(Hash::check('NewPass99', $hash));
        $this->assertFalse(LegacyPassword::isLegacyMd5($hash));
        $this->assertStringStartsWith('$2', $hash);
    }

    public function test_password_change_accepts_legacy_md5_current_password(): void
    {
        $user = $this->makeUser();
        User::query()->whereKey($user->id)->update(['password' => md5('Md5Old99x')]);
        $this->actingAs($user->fresh(), 'web');

        $this->post('/user/password_change', [
            'old_password' => 'Md5Old99x',
            'password' => 'NewPass99',
            're_password' => 'NewPass99',
        ])->assertRedirect(url('user') . '#jm-account-profile');

        $this->assertTrue(Hash::check('NewPass99', $user->fresh()->password));
    }

    public function test_phone_crud_max_three_duplicate_and_idor(): void
    {
        $user = $this->makeUser();
        $other = $this->makeUser();
        $this->actingAs($user, 'web');

        $this->get('/user')->assertOk();
        $this->assertSame(1, UserPhone::query()->where('user_id', $user->id)->count());

        $this->post('/user/phone_add', ['phone_number' => '0911 222 333', 'phone_label' => 'Công ty'])
            ->assertRedirect(url('user') . '#phones');
        $this->assertTrue(UserPhone::query()->where('user_id', $user->id)->where('phone_number', '0911222333')->exists());

        $this->post('/user/phone_add', ['phone_number' => '0911222333'])
            ->assertRedirect(url('user') . '#phones');
        $this->assertSame(2, UserPhone::query()->where('user_id', $user->id)->count());

        $this->post('/user/phone_add', ['phone_number' => '0922333444']);
        $this->post('/user/phone_add', ['phone_number' => '0933444555'])
            ->assertRedirect(url('user') . '#phones');
        $this->assertSame(3, UserPhone::query()->where('user_id', $user->id)->count());

        $otherPhone = UserPhone::query()->create([
            'user_id' => $other->id,
            'phone_label' => '',
            'phone_number' => '0987654321',
            'is_default' => 1,
            'created' => time(),
        ]);

        $this->post('/user/phone_delete/' . $otherPhone->id)->assertRedirect(url('user') . '#phones');
        $this->assertTrue(UserPhone::query()->whereKey($otherPhone->id)->exists());

        $this->post('/user/phone_default/' . $otherPhone->id)->assertRedirect(url('user') . '#phones');
        $this->assertSame(1, (int) $otherPhone->fresh()->is_default);

        $this->get('/user/phone_delete/' . $otherPhone->id)->assertRedirect(url('user'));
    }

    public function test_address_crud_and_idor(): void
    {
        $user = $this->makeUser();
        $other = $this->makeUser();
        $this->actingAs($user, 'web');
        $this->get('/user')->assertOk();

        $this->post('/user/address_add', $this->addressPayload())
            ->assertRedirect(url('user') . '#addresses');

        $row = UserAddress::query()->where('user_id', $user->id)->where('address_note', '123 Test B7')->first();
        $this->assertNotNull($row);
        $this->assertStringContainsString('Phường Phúc Xá', (string) $row->address_line);
        $this->assertSame('Test', $user->fresh()->address);

        $this->post('/user/address_add', $this->addressPayload(['address_note' => '456 Other']))
            ->assertRedirect(url('user') . '#addresses');
        $second = UserAddress::query()->where('user_id', $user->id)->orderByDesc('id')->first();
        $this->post('/user/address_default/' . $second->id)->assertRedirect(url('user') . '#addresses');
        $this->assertSame(1, (int) $second->fresh()->is_default);
        $this->assertSame($second->fresh()->address_line, $user->fresh()->address);

        $foreign = UserAddress::query()->create([
            'user_id' => $other->id,
            'address_note' => 'secret',
            'province_id' => '01',
            'district_id' => '001',
            'ward_id' => '00001',
            'address_line' => 'SECRET-B7-ADDR',
            'is_default' => 1,
            'created' => time(),
        ]);

        $this->post('/user/address_delete/' . $foreign->id)->assertRedirect(url('user') . '#addresses');
        $this->assertTrue(UserAddress::query()->whereKey($foreign->id)->exists());
        $this->post('/user/address_default/' . $foreign->id)->assertRedirect(url('user') . '#addresses');
        $this->get('/user')->assertOk()->assertDontSee('SECRET-B7-ADDR');
    }

    public function test_order_history_shows_only_own_orders_with_filter_and_pagination(): void
    {
        $user = $this->makeUser();
        $other = $this->makeUser();
        $this->actingAs($user, 'web');

        $ownNew = $this->insertOrder($user, ['status' => 0, 'amount' => 111000]);
        $ownDone = $this->insertOrder($user, ['status' => 3, 'amount' => 222000]);
        $this->insertOrder($other, ['status' => 0, 'amount' => 999000, 'user_phone' => '0999000000']);

        $page = $this->get('/user')->assertOk();
        $page->assertSee(number_format(111000) . ' ₫');
        $page->assertSee(number_format(222000) . ' ₫');
        $page->assertDontSee(number_format(999000) . ' ₫');
        $page->assertSee('Chờ xử lý');
        $page->assertSee('Hoàn thành');

        $this->get('/user?order_status=0')->assertOk()
            ->assertSee(number_format(111000) . ' ₫')
            ->assertDontSee(number_format(222000) . ' ₫');

        $this->get('/user/order_detail/' . $ownNew)
            ->assertOk()
            ->assertSee('Đơn hàng #' . $ownNew)
            ->assertDontSee('Đơn hàng #' . $ownDone);

        for ($i = 0; $i < 6; $i++) {
            $this->insertOrder($user, ['status' => 1, 'amount' => 1000 + $i]);
        }

        $this->get('/user')->assertOk()->assertSee('order_page=2', false);
        $this->get('/user?order_page=2')->assertOk()->assertSee('Trang 2/');
    }

    public function test_order_detail_owner_ok_other_user_404(): void
    {
        $owner = $this->makeUser();
        $other = $this->makeUser();
        $this->actingAs($owner, 'web');
        $product = $this->standardProduct();
        $this->addToCart($product, ['size' => 'S', 'color' => 'Đen', 'qty' => 2]);
        $this->post('/thanh-toan', $this->checkoutPayload($owner));
        $txId = $this->lastTransactionId($owner);

        $this->get('/user/order_detail/' . $txId)
            ->assertOk()
            ->assertSee('Đơn hàng #' . $txId)
            ->assertSee(ProductDisplay::displayName($product->name))
            ->assertSee('Số lượng: 2')
            ->assertSee('COD')
            ->assertDontSee('layouts.storefront', false);

        $this->actingAs($other, 'web');
        $this->get('/user/order_detail/' . $txId)->assertNotFound();
        $this->get('/user')->assertOk()->assertDontSee('Đơn hàng #' . $txId);
    }

    public function test_user_cannot_cancel_or_view_qr_of_another_users_order(): void
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
        $this->post('/thanh-toan/cancel/' . $txId, ['reason' => 'hack'])->assertRedirect(url('/'));
        $this->assertSame(0, (int) DB::table('transaction')->where('id', $txId)->value('status'));
        $this->get('/user/order_detail/' . $txId)->assertNotFound();
    }

    public function test_owner_cancels_from_account_flow_and_second_cancel_is_idempotent(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user, 'web');
        $product = $this->standardProduct();
        $variantId = $this->variantId($product, 'Đen', 'S');
        $stockBefore = $this->variantStock($variantId);
        $this->addToCart($product, ['size' => 'S', 'color' => 'Đen', 'qty' => 2]);
        $this->post('/thanh-toan', $this->checkoutPayload($user));
        $txId = $this->lastTransactionId($user);

        $this->get('/user')->assertOk()->assertSee('Hủy đơn');
        $this->post('/thanh-toan/cancel/' . $txId, ['reason' => 'Đổi ý từ tài khoản'])
            ->assertRedirect(url('user'));
        $this->assertSame(4, (int) DB::table('transaction')->where('id', $txId)->value('status'));
        $this->assertSame($stockBefore, $this->variantStock($variantId));

        $inMoves = (int) DB::table('stock_movements')->where('reference_id', $txId)->where('movement_type', 'in')->count();
        $this->post('/thanh-toan/cancel/' . $txId, ['reason' => 'lần 2'])->assertRedirect(url('user'));
        $this->assertSame(4, (int) DB::table('transaction')->where('id', $txId)->value('status'));
        $this->assertSame($inMoves, (int) DB::table('stock_movements')->where('reference_id', $txId)->where('movement_type', 'in')->count());
        $this->assertSame($stockBefore, $this->variantStock($variantId));
    }

    public function test_logout_keeps_saved_cart_and_guest_cannot_see_account(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user, 'web');
        $product = $this->standardProduct();
        $this->addToCart($product, ['size' => 'S', 'color' => 'Đen', 'qty' => 2])->assertRedirect(url('gio-hang'));
        $this->get('/user')->assertOk();
        $this->post('/user/logout')->assertRedirect(route('storefront.home'));
        $this->get('/user')->assertRedirect(url('dang-nhap'));
        $this->assertNotEmpty($this->savedCart($user));
    }

    public function test_handle_path_and_leftover_validation_urls(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user, 'web');
        $this->get('/user/index')->assertOk()->assertSee('Thông tin tài khoản');
        $this->get('/user/password_change')->assertRedirect(url('user'));
        $this->get('/user/check_email')->assertNotFound();
        $this->get('/user/khong-ton-tai')->assertNotFound();
        $this->get('/user/login')->assertRedirect();
    }
}
