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
        $status = $overrides['status'] ?? 'pending';
        $map = ['0' => 'pending', '1' => 'confirmed', '2' => 'packed', '3' => 'completed', '4' => 'cancelled'];
        $status = $map[(string) $status] ?? $status;
        $total = $overrides['total'] ?? $overrides['amount'] ?? 250000;
        unset($overrides['status'], $overrides['amount'], $overrides['user_name'], $overrides['user_email'], $overrides['user_phone'], $overrides['user_address'], $overrides['message'], $overrides['payment'], $overrides['created']);

        return (int) DB::table('orders')->insertGetId(array_merge([
            'status' => $status,
            'user_id' => $user->id,
            'customer_name' => $user->name,
            'customer_email' => $user->email,
            'customer_phone' => (string) ($overrides['customer_phone'] ?? $user->phone),
            'shipping_address' => (string) $user->address,
            'note' => '',
            'subtotal' => $total,
            'discount_amount' => 0,
            'shipping_fee' => 0,
            'total' => $total,
            'voucher_code' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }
}
