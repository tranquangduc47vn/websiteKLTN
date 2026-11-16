<?php

namespace Tests\Feature\V2;

use App\Models\AiConversation;
use App\Models\AiFaq;
use App\Models\AiMessage;
use App\Models\AiSetting;
use App\Models\StockReceipt;
use App\Models\Supplier;
use App\Models\User;
use App\Models\UserSavedCart;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\LegacyMysqlTestCase;

/**
 * Ghi và đọc timestamp DATETIME trên webshop_v2_migration.
 * DatabaseTransactions rollback sau mỗi test.
 */
class V2TimestampTest extends LegacyMysqlTestCase
{
    public function test_stock_receipt_create_and_update_store_datetime(): void
    {
        $receipt = StockReceipt::query()->create([
            'receipt_code' => 'T' . Str::upper(Str::random(8)),
            'supplier_id' => null,
            'status' => StockReceipt::STATUS_DRAFT,
            'note' => 'v2 timestamp',
            'total_qty' => 0,
            'total_amount' => 0,
            'receipt_date' => '2026-10-21',
            'created_by' => null,
            'confirmed_by' => null,
            'confirmed_at' => null,
            'cancelled_at' => null,
        ]);

        $created = $this->datetimeValue('stock_receipts', 'created_at', $receipt->id);
        $updated = $this->datetimeValue('stock_receipts', 'updated_at', $receipt->id);
        $this->assertNotNull($receipt->fresh()->created_at);
        $this->assertNotNull($receipt->fresh()->updated_at);

        $receipt->note = 'v2 timestamp updated';
        $receipt->save();

        $this->datetimeValue('stock_receipts', 'created_at', $receipt->id);
        $after = $this->datetimeValue('stock_receipts', 'updated_at', $receipt->id);
        $this->assertGreaterThanOrEqual($created, $this->datetimeValue('stock_receipts', 'created_at', $receipt->id));
        $this->assertGreaterThanOrEqual($updated, $after);
        $this->assertSame('v2 timestamp updated', DB::table('stock_receipts')->where('id', $receipt->id)->value('note'));
    }

    public function test_supplier_create_and_update_store_datetime(): void
    {
        $supplier = Supplier::query()->create([
            'code' => 'TS' . Str::upper(Str::random(6)),
            'name' => 'V2 Timestamp Supplier',
            'contact_name' => '',
            'phone' => '',
            'email' => '',
            'address' => '',
            'note' => null,
            'status' => 'active',
        ]);

        $this->datetimeValue('suppliers', 'created_at', $supplier->id);
        $this->datetimeValue('suppliers', 'updated_at', $supplier->id);

        $supplier->name = 'V2 Timestamp Supplier Updated';
        $supplier->save();

        $this->datetimeValue('suppliers', 'created_at', $supplier->id);
        $this->datetimeValue('suppliers', 'updated_at', $supplier->id);
        $this->assertSame('V2 Timestamp Supplier Updated', DB::table('suppliers')->where('id', $supplier->id)->value('name'));
    }

    public function test_ai_setting_create_and_update_store_updated_at_only(): void
    {
        $this->assertNoColumn('ai_settings', 'created_at');
        $this->assertNoColumn('ai_settings', 'created');
        $this->assertNoColumn('ai_settings', 'updated');

        $key = 'v2_ts_' . Str::lower(Str::random(8));
        $setting = AiSetting::query()->create([
            'setting_key' => $key,
            'setting_value' => '0',
        ]);

        $this->datetimeValue('ai_settings', 'updated_at', $setting->id);

        AiSetting::setValue($key, '1');
        $raw = DB::table('ai_settings')->where('setting_key', $key)->first();
        $this->assertSame('1', (string) $raw->setting_value);
        $this->assertDatetimeString($raw->updated_at);
    }

    public function test_saved_cart_create_and_update_store_updated_at_only(): void
    {
        $this->assertNoColumn('user_saved_carts', 'created_at');
        $this->assertNoColumn('user_saved_carts', 'updated');

        $user = User::query()->create([
            'name' => 'V2 Cart Timestamp',
            'email' => 'v2ts.' . Str::lower(Str::random(8)) . '@example.test',
            'password_hash' => Hash::make('V2-Timestamp#1'),
        ]);

        $cart = UserSavedCart::query()->create([
            'user_id' => $user->id,
            'cart_json' => '{"items":1}',
        ]);

        $this->datetimeValue('user_saved_carts', 'updated_at', $cart->user_id, 'user_id');

        $cart->cart_json = '{"items":2}';
        $cart->save();

        $raw = DB::table('user_saved_carts')->where('user_id', $user->id)->first();
        $this->assertSame('{"items":2}', (string) $raw->cart_json);
        $this->assertDatetimeString($raw->updated_at);
    }

    public function test_ai_message_create_stores_created_at_only(): void
    {
        $this->assertNoColumn('ai_messages', 'updated_at');
        $this->assertNoColumn('ai_messages', 'created');

        $conversation = AiConversation::query()->create([
            'user_id' => null,
            'guest_token' => null,
            'staff_id' => null,
            'staff_name' => '',
            'status' => 'open',
            'unread_staff' => 0,
            'unread_customer' => 0,
        ]);

        $message = AiMessage::query()->create([
            'conversation_id' => $conversation->id,
            'sender' => AiMessage::SENDER_CUSTOMER,
            'content' => 'v2 timestamp message',
            'meta' => '',
        ]);

        $created = $this->datetimeValue('ai_messages', 'created_at', $message->id);
        $this->assertNotNull($message->fresh()->created_at);

        $message->content = 'v2 timestamp message updated';
        $message->save();

        $this->assertSame($created, $this->datetimeValue('ai_messages', 'created_at', $message->id));
        $this->assertSame('v2 timestamp message updated', DB::table('ai_messages')->where('id', $message->id)->value('content'));
    }

    public function test_ai_faq_create_and_update_store_datetime(): void
    {
        $faq = AiFaq::query()->create([
            'question' => 'V2 timestamp FAQ ' . Str::random(4),
            'answer' => 'Tra loi',
            'keywords' => 'v2',
            'category' => 'test',
            'sort_order' => 99,
            'is_active' => 1,
        ]);

        $this->datetimeValue('ai_faqs', 'created_at', $faq->id);
        $this->datetimeValue('ai_faqs', 'updated_at', $faq->id);

        $faq->answer = 'Tra loi moi';
        $faq->save();

        $this->datetimeValue('ai_faqs', 'created_at', $faq->id);
        $this->datetimeValue('ai_faqs', 'updated_at', $faq->id);
        $this->assertSame('Tra loi moi', DB::table('ai_faqs')->where('id', $faq->id)->value('answer'));
    }

    private function datetimeValue(string $table, string $column, int $id, string $key = 'id'): string
    {
        $value = DB::table($table)->where($key, $id)->value($column);
        $this->assertDatetimeString($value);

        return (string) $value;
    }

    private function assertDatetimeString(mixed $value): void
    {
        $this->assertIsString($value);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value);
        $year = (int) substr($value, 0, 4);
        $this->assertGreaterThanOrEqual(2020, $year);
        $this->assertLessThanOrEqual(2100, $year);
    }

    private function assertNoColumn(string $table, string $column): void
    {
        $exists = DB::table('information_schema.COLUMNS')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('COLUMN_NAME', $column)
            ->exists();
        $this->assertFalse($exists, $table . '.' . $column . ' must not exist');
    }
}
