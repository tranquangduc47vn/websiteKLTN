<?php

namespace Tests\Feature\AI;

use App\Models\Admin;
use App\Models\AiConversation;
use App\Models\AiSetting;
use App\Models\User;
use App\Support\LegacyPassword;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Feature\Admin\AdminTestCase;

class AiSecurityTest extends AdminTestCase
{
    /** @var list<array<string, mixed>> */
    private array $settings = [];

    /** @var list<int> */
    private array $userIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->settings = DB::table('ai_setting')->get()->map(fn ($row) => (array) $row)->all();
        config(['ai.gemini_api_key' => 'b15-test-key']);
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
            $conversationIds = DB::table('ai_conversation')->whereIn('user_id', $this->userIds)->pluck('id');
            DB::table('ai_message')->whereIn('conversation_id', $conversationIds)->delete();
            DB::table('ai_conversation')->whereIn('user_id', $this->userIds)->delete();
            DB::table('user')->whereIn('id', $this->userIds)->delete();
        }
        DB::table('ai_faq')->where('question', 'like', 'B15-%')->delete();
        parent::tearDown();
    }

    public function test_admin_levels_fake_session_and_query_level(): void
    {
        $this->get('/admin/ai-assistant')->assertRedirect('/admin/login');
        $this->get('/admin/support-chat')->assertRedirect('/admin/login');
        $this->withSession(['login' => ['id' => 1, 'name' => 'Fake', 'email' => 'fake@example.test', 'level' => 0]])
            ->get('/admin/ai-assistant')->assertRedirect('/admin/login');

        $this->actingAs($this->makeAdmin(Admin::ROLE_USER), 'admin');
        $this->get('/admin/ai-assistant')->assertRedirect('/admin');
        $this->get('/admin/ai-assistant?level=0')->assertRedirect('/admin');
        $this->get('/admin/support-chat')->assertOk()->assertSee('Hỗ trợ khách hàng');
        $this->get('/admin/support-chat/take/1')->assertNotFound();
        $this->get('/admin/support-chat/purge')->assertNotFound();

        Auth::guard('admin')->logout();
        $this->actingAs($this->makeAdmin(Admin::ROLE_MOD), 'admin');
        $this->get('/admin/ai-assistant/settings')->assertOk()->assertSee('Lưu cấu hình')->assertDontSee('b15-test-key');
        $before = (int) DB::table('ai_conversation')->count();
        $this->post('/admin/support-chat/purge')->assertRedirect('/admin/support-chat');
        $this->assertSame($before, (int) DB::table('ai_conversation')->count());
    }

    public function test_staff_cannot_take_another_staff_conversation_and_csrf_blocks_posts(): void
    {
        $user = User::query()->create([
            'name' => 'B15 Chat',
            'email' => 'b15chat.' . Str::lower(Str::random(6)) . '@example.test',
            'password' => LegacyPassword::hash('B15-Secret#1'),
            'phone' => '0900151501',
            'address' => 'HN',
            'created' => time(),
        ]);
        $this->userIds[] = (int) $user->id;
        $conversation = AiConversation::query()->create([
            'user_id' => $user->id,
            'guest_token' => '',
            'staff_id' => 0,
            'staff_name' => '',
            'status' => 'waiting_staff',
            'started' => time(),
            'last_message' => time(),
            'unread_staff' => 1,
            'unread_customer' => 0,
        ]);
        $first = $this->makeAdmin(Admin::ROLE_MOD);
        $second = $this->makeAdmin(Admin::ROLE_ADMIN);
        $this->actingAs($first, 'admin');
        $this->postJson('/admin/support-chat/take/' . $conversation->id)->assertOk()->assertJson(['ok' => true]);
        $this->actingAs($second, 'admin');
        $this->postJson('/admin/support-chat/take/' . $conversation->id)->assertOk()
            ->assertJson(['ok' => false, 'message' => 'Hội thoại đang được nhân viên khác xử lý.']);
        $this->postJson('/admin/support-chat/send', [
            'conversation_id' => $conversation->id,
            'message' => 'tin cua admin khac',
        ])->assertOk()->assertJson(['ok' => false]);
        $this->assertSame(0, (int) DB::table('ai_message')->where('content', 'tin cua admin khac')->count());

        $this->app->bind(ValidateCsrfToken::class, fn ($app) => new class($app, $app['encrypter']) extends ValidateCsrfToken
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });
        $this->post('/admin/support-chat/take/' . $conversation->id)->assertStatus(419);
        $this->post('/ai-assistant/send', ['message' => 'zzb15qxonly'])->assertStatus(419);
    }

    public function test_settings_do_not_store_an_api_key(): void
    {
        $this->actingAs($this->makeAdmin(Admin::ROLE_ADMIN), 'admin');
        $this->post('/admin/ai-assistant/settings', [
            'submit' => '1',
            'ai_enabled' => '1',
            'staff_support_enabled' => '1',
            'fallback_to_staff' => '1',
            'welcome_message' => 'B15-welcome',
            'working_hours_text' => '8-17',
            'gemini_api_key' => 'b15-test-key',
        ])->assertRedirect('/admin/ai-assistant/settings');
        $this->assertSame('B15-welcome', AiSetting::getValue('welcome_message'));
        $this->assertNull(DB::table('ai_setting')->where('setting_value', 'b15-test-key')->first());
        $this->post('/admin/ai-assistant/faq/add', [
            'submit' => '1',
            'question' => 'B15-cau hoi',
            'answer' => 'B15-tra loi',
            'keywords' => 'b15',
            'is_active' => '1',
        ])->assertRedirect('/admin/ai-assistant/faq');
        $this->assertNotNull(DB::table('ai_faq')->where('question', 'B15-cau hoi')->first());
        $this->get('/admin/ai-assistant/faq/delete/' . (int) DB::table('ai_faq')->where('question', 'B15-cau hoi')->value('id'))->assertNotFound();
    }
}
