<?php

namespace Tests\Feature\Order;

use App\Models\User;
use App\Models\Voucher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Feature\Cart\CartTestCase;

abstract class OrderTestCase extends CartTestCase
{
    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function checkoutPayload(User $user, array $overrides = []): array
    {
        return array_merge([
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'address' => $user->address,
            'message' => 'Ghi chú B6',
            'payment' => 'COD',
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function makeVoucher(array $attributes = []): Voucher
    {
        return Voucher::query()->create(array_merge([
            'code' => 'B6T' . strtoupper(Str::random(8)),
            'name' => 'B6 Test Voucher',
            'description' => '',
            'discount_type' => 'percent',
            'discount_value' => 10,
            'min_order_amount' => 0,
            'max_discount' => 0,
            'tier_min' => 'member',
            'user_id' => 0,
            'usage_limit' => 100,
            'used_count' => 0,
            'per_user_limit' => 5,
            'valid_from' => 0,
            'valid_to' => 0,
            'is_active' => 1,
            'created' => time(),
        ], $attributes));
    }

    protected function variantStock(int $variantId): int
    {
        return (int) DB::table('product_variants')->where('id', $variantId)->value('stock');
    }

    protected function lastTransactionId(User $user): int
    {
        return (int) DB::table('transaction')->where('user_id', $user->id)->orderByDesc('id')->value('id');
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function cartRow(\App\Models\Product $product, string $size = 'S', string $color = 'Đen', int $qty = 1): array
    {
        $rowid = $this->rowId($product, $size, $color);
        $price = 250000.0;

        return [
            $rowid => [
                'rowid' => $rowid,
                'id' => $product->id,
                'variant_id' => $this->variantId($product, $color, $size),
                'qty' => $qty,
                'price' => $price,
                'name' => $product->name,
                'subtotal' => $price * $qty,
                'options' => ['image_link' => 'b5-test.jpg', 'size' => $size, 'color' => $color],
            ],
        ];
    }
}
