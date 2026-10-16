<?php

namespace Tests\Feature\Account;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\DB;

class AccountCsrfTest extends AccountTestCase
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

    public function test_account_posts_without_token_are_rejected(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user, 'web');
        $this->get('/user')->assertOk();

        $this->post('/user/profile_update', ['name' => 'Hacker'])->assertStatus(419);
        $this->post('/user/password_change', [
            'old_password' => self::PASSWORD,
            'password' => 'NewPass99',
            're_password' => 'NewPass99',
        ])->assertStatus(419);
        $this->post('/user/phone_add', ['phone_number' => '0911222333'])->assertStatus(419);
        $this->post('/user/address_add', $this->addressPayload())->assertStatus(419);

        $this->assertSame('B5 Cart Tester', $user->fresh()->name);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check(self::PASSWORD, $user->fresh()->password));
    }

    public function test_account_posts_with_token_are_accepted(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user, 'web');
        $this->get('/user')->assertOk();
        $token = csrf_token();

        $this->post('/user/profile_update', ['_token' => $token, 'name' => 'B7 Token Name'])
            ->assertRedirect(url('user') . '#jm-account-profile');
        $this->assertSame('B7 Token Name', $user->fresh()->name);

        $phone = DB::table('user_phone')->where('user_id', $user->id)->orderByDesc('id')->value('id');
        $this->post('/user/phone_delete/' . $phone, ['_token' => $token])
            ->assertRedirect(url('user') . '#phones');
    }
}
