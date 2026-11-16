<?php

namespace Tests\Feature\AI;

use App\Models\AiFaq;
use App\Models\AiSetting;
use App\Models\User;
use App\Support\LegacyPassword;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Str;
use Tests\LegacyMysqlTestCase;

class AiChatTest extends LegacyMysqlTestCase
{
    private const KEY = 'b15-test-key';

    /** @var list<array<string, mixed>> */
    private array $settings = [];

    /** @var list<int> */
    private array $userIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->settings = DB::table('ai_settings')->get()->map(fn ($row) => (array) $row)->all();
        config(['ai.gemini_api_key' => self::KEY]);
        AiSetting::setValue('ai_enabled', '1');
        AiSetting::setValue('staff_support_enabled', '1');
        AiSetting::setValue('fallback_to_staff', '0');
    }

    protected function tearDown(): void
    {
        foreach (['ai_enabled', 'staff_support_enabled', 'fallback_to_staff', 'welcome_message', 'working_hours_text'] as $key) {
            $row = collect($this->settings)->firstWhere('setting_key', $key);
            if ($row) {
                AiSetting::setValue($key, (string) $row['setting_value']);
            }
        }
        if ($this->userIds !== []) {
            $conversationIds = DB::table('ai_conversations')->whereIn('user_id', $this->userIds)->pluck('id');
            DB::table('ai_messages')->whereIn('conversation_id', $conversationIds)->delete();
            DB::table('ai_conversations')->whereIn('user_id', $this->userIds)->delete();
            DB::table('orders')->whereIn('user_id', $this->userIds)->delete();
            DB::table('users')->whereIn('id', $this->userIds)->delete();
        }
        DB::table('ai_faqs')->where('question', 'like', 'B15-%')->delete();
        parent::tearDown();
    }

    public function test_guest_and_fake_session_cannot_chat(): void
    {
        $before = (int) DB::table('ai_conversations')->where('user_id', 10)->count();
        $this->postJson('/ai-assistant/send', ['message' => 'xin chao'])->assertOk()
            ->assertJson(['ok' => false, 'login_required' => true, 'message' => 'Bạn cần đăng nhập tài khoản để sử dụng hỗ trợ khách hàng.']);
        $this->withSession(['user' => ['id' => 10, 'name' => 'Fake']])->postJson('/ai-assistant/send', ['message' => 'xin chao'])
            ->assertJson(['login_required' => true]);
        $this->assertSame($before, (int) DB::table('ai_conversations')->where('user_id', 10)->count());
        Http::assertNothingSent();
    }

    public function test_logged_user_gets_one_gemini_reply_and_owns_history(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response($this->gemini('B15-AI-OK'), 200)]);
        $user = $this->makeUser();
        $other = $this->makeUser();
        $this->actingAs($user, 'web');

        $sent = $this->postJson('/ai-assistant/send', ['message' => 'zzb15qxonly'])->assertOk()->json();
        $this->assertTrue($sent['ok']);
        $this->assertSame('B15-AI-OK', $sent['content']);
        $this->assertArrayNotHasKey('login_required', $sent);
        $this->assertStringNotContainsString(self::KEY, json_encode($sent));
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->hasHeader('x-goog-api-key', self::KEY)
            && ! str_contains($request->url(), self::KEY));

        $id = (int) $sent['conversation_id'];
        $this->getJson('/ai-assistant/history?conversation_id=' . $id)->assertOk()
            ->assertJsonFragment(['content' => 'B15-AI-OK']);

        $this->actingAs($other, 'web');
        $this->getJson('/ai-assistant/history?conversation_id=' . $id)->assertOk()
            ->assertJson(['ok' => true, 'messages' => []]);
        $this->getJson('/ai-assistant/poll?conversation_id=' . $id . '&after_id=0')->assertOk()
            ->assertJson(['messages' => []]);
    }

    public function test_order_context_stays_on_the_logged_in_user(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response($this->gemini('da xem don'), 200)]);
        $user = $this->makeUser();
        $other = $this->makeUser();
        $this->insertOrder($user, 864202);
        $this->insertOrder($other, 864201);
        $this->actingAs($user, 'web');
        $this->postJson('/ai-assistant/send', [
            'message' => 'zzb15qxonly zzb15qa zzb15qb zzb15qc kiểm tra đơn zzb15qd zzb15qe',
        ])->assertOk();
        Http::assertSent(function ($request) {
            $body = $request->body();

            return str_contains($body, '864202') && ! str_contains($body, '864201') && ! str_contains($body, 'cost_price');
        });
    }

    public function test_faq_handoff_and_disabled_do_not_call_the_provider(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response($this->gemini('khong duoc goi'), 200)]);
        $user = $this->makeUser();
        $this->actingAs($user, 'web');
        AiFaq::query()->create([
            'question' => 'B15-FAQ',
            'answer' => 'B15-FAQ-ANSWER',
            'keywords' => 'zzb15faqphrase',
            'category' => 'b15',
            'sort_order' => 0,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->postJson('/ai-assistant/send', ['message' => 'zzb15faqphrase'])->assertOk()
            ->assertJsonPath('content', 'B15-FAQ-ANSWER');
        $this->postJson('/ai-assistant/send', ['message' => 'gặp nhân viên'])->assertOk()
            ->assertJsonPath('status', 'waiting_staff');
        AiSetting::setValue('ai_enabled', '0');
        $this->postJson('/ai-assistant/send', ['message' => 'zzb15qxonly'])->assertOk()
            ->assertJsonFragment(['content' => 'Trợ lý AI hiện đang tắt. Mình chuyển bạn sang nhân viên hỗ trợ nhé.']);
        Http::assertNothingSent();
    }

    public function test_validation_rate_limit_and_provider_errors_stay_friendly(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user, 'web');
        $this->postJson('/ai-assistant/send', ['message' => ''])->assertOk()
            ->assertJsonPath('content', 'Bạn vui lòng nhập nội dung cần hỏi nhé.');
        $this->postJson('/ai-assistant/send', ['message' => str_repeat('a', 501)])->assertOk()
            ->assertJsonPath('message', 'Tin nhắn tối đa 500 ký tự.');

        $mode = 200;
        Http::fake(function () use (&$mode) {
            if ($mode === 0) {
                throw new ConnectionException('timed out');
            }
            if ($mode === 200) {
                return Http::response($this->gemini('ok'), 200);
            }

            return Http::response(['error' => ['message' => self::KEY]], $mode);
        });
        $id = 0;
        for ($i = 0; $i < 8; $i++) {
            $id = (int) $this->postJson('/ai-assistant/send', ['message' => 'zzb15qxonly ' . $i, 'conversation_id' => $id])->json('conversation_id');
        }
        $this->postJson('/ai-assistant/send', ['message' => 'zzb15qxonly them', 'conversation_id' => $id])->assertOk()
            ->assertJsonPath('content', 'Bạn gửi tin nhanh quá, vui lòng chờ một chút rồi thử lại nhé.');
        Http::assertSentCount(8);

        config(['ai.rate_limit_max' => 100]);
        foreach ([401, 403, 429, 500] as $status) {
            $mode = $status;
            $body = $this->postJson('/ai-assistant/send', ['message' => 'zzb15qxonly err ' . $status])->assertOk()->json();
            $this->assertStringContainsString('Xin lỗi, mình chưa thể trả lời câu này.', (string) $body['content']);
            $this->assertStringNotContainsString(self::KEY, (string) $body['content']);
        }
        $mode = 0;
        $timeout = $this->postJson('/ai-assistant/send', ['message' => 'zzb15qxonly timeout'])->assertOk()->json();
        $this->assertStringContainsString('Xin lỗi, mình chưa thể trả lời câu này.', (string) $timeout['content']);
    }

    public function test_widget_and_logs_do_not_expose_the_api_key(): void
    {
        $lines = [];
        Log::listen(function (MessageLogged $event) use (&$lines) {
            $lines[] = $event->message . ' ' . json_encode($event->context);
        });
        Http::fake(fn () => throw new ConnectionException('timed out ' . self::KEY));
        $this->get('/')->assertOk()->assertSee('id="support-chat-widget"', false)->assertDontSee(self::KEY);
        $user = $this->makeUser();
        $this->actingAs($user, 'web');
        $this->postJson('/ai-assistant/send', ['message' => 'zzb15qxonly log'])->assertOk();
        $blob = implode("\n", $lines);
        $this->assertStringNotContainsString(self::KEY, $blob);
        $this->assertNotSame('', $blob);
    }

    private function gemini(string $text): array
    {
        return ['candidates' => [['content' => ['parts' => [['text' => $text]]]]]];
    }

    private function makeUser(): User
    {
        $user = User::query()->create([
            'name' => 'B15 Chat',
            'email' => 'b15chat.' . Str::lower(Str::random(6)) . '@example.test',
            'password_hash' => LegacyPassword::hash('B15-Secret#1'),
            'created_at' => now(),
        ]);
        $this->userIds[] = (int) $user->id;

        return $user;
    }

    private function insertOrder(User $user, int $amount): void
    {
        DB::table('orders')->insert([
            'status' => 'pending',
            'user_id' => $user->id,
            'customer_name' => $user->name,
            'customer_email' => $user->email,
            'customer_phone' => (string) $user->phone,
            'shipping_address' => (string) $user->address,
            'note' => '',
            'subtotal' => $amount,
            'discount_amount' => 0,
            'shipping_fee' => 0,
            'total' => $amount,
            'voucher_code' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
