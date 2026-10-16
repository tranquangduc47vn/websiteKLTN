<?php

namespace Tests\Feature\Account;

use App\Models\User;
use App\Support\Storefront\VnAddress;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Order\OrderTestCase;

abstract class AccountTestCase extends OrderTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['shop.vn_address_path' => base_path('tests/fixtures/vn-hanh-chinh.json')]);
        VnAddress::resetCache();
    }

    protected function addressPayload(array $overrides = []): array
    {
        return array_merge([
            'province_id' => '01',
            'district_id' => '001',
            'ward_id' => '00001',
            'address_note' => '123 Test B7',
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function insertOrder(User $user, array $overrides = []): int
    {
        return (int) DB::table('transaction')->insertGetId(array_merge([
            'status' => 0,
            'user_id' => $user->id,
            'user_name' => $user->name,
            'user_email' => $user->email,
            'user_phone' => $user->phone,
            'user_address' => $user->address,
            'message' => '',
            'amount' => 250000,
            'voucher_code' => '',
            'discount_amount' => 0,
            'payment' => 'COD',
            'created' => time(),
        ], $overrides));
    }
}
