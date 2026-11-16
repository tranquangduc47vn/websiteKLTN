<?php

namespace Tests\Feature\Security;

use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;
use App\Support\LegacyPassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\LegacyMysqlTestCase;

class ModelFillableTest extends LegacyMysqlTestCase
{
    public function test_order_models_ignore_columns_outside_the_whitelist(): void
    {
        foreach ([User::class, Product::class, Transaction::class, OrderItem::class, Payment::class] as $class) {
            $this->assertSame(['*'], (new $class)->getGuarded(), $class);
            $this->assertNotContains('id', (new $class)->getFillable(), $class);
        }

        $email = 'fillable.' . Str::lower(Str::random(8)) . '@example.test';
        $user = User::query()->create([
            'name' => 'Fillable Guard',
            'email' => $email,
            'password_hash' => LegacyPassword::hash('FillablePass1'),
            'id' => 900000000,
            'not_a_column' => 'injected',
        ]);
        $this->assertNotSame(900000000, (int) $user->id);
        $this->assertSame(0, (int) $user->loyalty_points);

        $categoryId = (int) DB::table('categories')->orderBy('id')->value('id');
        $product = Product::query()->create([
            'category_id' => $categoryId,
            'name' => 'Fillable Product',
            'content' => '',
            'code' => 'FL' . strtoupper(Str::random(8)),
            'list_price' => 1000,
            'discount_amount' => 0,
            'id' => 900000000,
            'not_a_column' => 'injected',
        ]);
        $this->assertNotSame(900000000, (int) $product->id);

        $order = Transaction::query()->create([
            'user_id' => $user->id,
            'customer_name' => 'Fillable Guard',
            'customer_email' => $email,
            'customer_phone' => '0900000000',
            'shipping_address' => 'Test',
            'subtotal' => 1000,
            'discount_amount' => 0,
            'shipping_fee' => 0,
            'total' => 1000,
            'status' => Transaction::STATUS_NEW,
            'id' => 900000000,
            'cancel_reason' => 'injected',
        ]);
        $this->assertNotSame(900000000, (int) $order->id);
        $this->assertNull(DB::table('orders')->where('id', $order->id)->value('cancel_reason'));
        $this->assertSame('1000.00', (string) DB::table('orders')->where('id', $order->id)->value('total'));
    }
}
