<?php

namespace Tests\Feature\Admin;

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\User;
use App\Support\LegacyPassword;
use Illuminate\Support\Str;

class SupportChatInboxTest extends AdminTestCase
{
    /** @var list<int> */
    private array $userIds = [];

    protected function tearDown(): void
    {
        if ($this->userIds !== []) {
            $conversationIds = AiConversation::query()->whereIn('user_id', $this->userIds)->pluck('id');
            AiMessage::query()->whereIn('conversation_id', $conversationIds)->delete();
            AiConversation::query()->whereIn('user_id', $this->userIds)->delete();
            User::query()->whereIn('id', $this->userIds)->delete();
        }
        parent::tearDown();
    }

    public function test_inbox_shows_customer_name_vietnamese_status_and_last_message(): void
    {
        $user = User::query()->create([
            'name' => 'B16 Khách Inbox',
            'email' => 'b16inbox.' . Str::lower(Str::random(6)) . '@example.test',
            'password_hash' => LegacyPassword::hash('secret'),
            'created_at' => now(),
        ]);
        $this->userIds[] = (int) $user->id;

        $conversation = AiConversation::query()->create([
            'user_id' => $user->id,
            'guest_token' => '',
            'staff_id' => 0,
            'staff_name' => 'Admin',
            'status' => 'staff_joined',
            'started' => time(),
            'last_message' => time(),
            'unread_staff' => 1,
            'unread_customer' => 0,
        ]);

        AiMessage::query()->create([
            'conversation_id' => $conversation->id,
            'sender' => 'customer',
            'content' => 'B16-inbox-tin-nhan-cuoi',
            'meta' => '',
            'created_at' => now(),
        ]);

        $this->actingAs($this->makeAdmin(), 'admin');

        $this->get('/admin/support-chat?q=' . urlencode('B16 Khách'))
            ->assertOk()
            ->assertSee('B16 Khách Inbox')
            ->assertSee('Nhân viên đang hỗ trợ')
            ->assertSee('B16-inbox-tin-nhan-cuoi')
            ->assertSee('Có')
            ->assertSee('Mở chat');
    }
}