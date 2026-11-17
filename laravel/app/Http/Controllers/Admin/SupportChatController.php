<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\AiConversation;
use App\Models\User;
use App\Services\Ai\ChatService;
use App\Support\Storefront\Csrf;
use Illuminate\Http\Request;

/**
 * Hộp thư nhân viên. Quyền order.chat nằm ở route. Xóa toàn bộ chỉ level 0.
 */
class SupportChatController extends Controller
{
    public function __construct(private readonly ChatService $chat) {}

    public function index(Request $request): mixed
    {
        $status = trim((string) $request->query('status', 'all'));
        if ($status === '') {
            $status = 'all';
        }

        return view('admin.support.index', [
            'stats' => $this->chat->stats(),
            'list' => $this->chat->inbox($status, trim((string) $request->query('q', '')), $request->boolean('unread')),
            'filter_status' => $status,
            'keyword' => trim((string) $request->query('q', '')),
            'unread_filter' => $request->boolean('unread'),
            'isAdmin' => (int) auth('admin')->user()?->level === Admin::ROLE_ADMIN,
        ]);
    }

    public function handlePath(Request $request, string $path = ''): mixed
    {
        $parts = array_values(array_filter(explode('/', trim($path, '/')), fn ($part) => $part !== ''));
        $action = $parts[0] ?? '';
        $id = (int) ($parts[1] ?? 0);

        return match ($action) {
            'chat' => $this->chatPage($id),
            'take' => $this->jsonPost($request, fn () => $this->chat->take($id, $this->staffId(), $this->staffName())),
            'send' => $this->send($request),
            'end' => $this->jsonPost($request, fn () => $this->chat->end($id, $this->staffId())),
            'return_ai' => $this->jsonPost($request, fn () => $this->chat->returnToAi($id, $this->staffId())),
            'poll' => response()->json($this->chat->pollStaff((int) $request->query('conversation_id'), (int) $request->query('after_id'))),
            'waiting_count' => response()->json(['ok' => true, 'count' => $this->chat->stats()['waiting']]),
            'delete' => $this->delete($request, $id),
            'purge' => $this->purge($request),
            default => abort(404),
        };
    }

    private function chatPage(int $id): mixed
    {
        $conversation = AiConversation::query()->find($id);
        if (! $conversation) {
            return redirect('/admin/support-chat')->with('message_fail', 'Hội thoại không tồn tại.');
        }
        $customer = (int) $conversation->user_id > 0 ? User::query()->find((int) $conversation->user_id) : null;

        return view('admin.support.chat', [
            'conversation' => $conversation,
            'messages' => $conversation->messages,
            'customer' => $customer,
            'staffId' => $this->staffId(),
        ]);
    }

    private function send(Request $request): mixed
    {
        if (! $request->isMethod('post')) {
            return response()->json(['ok' => false, 'message' => 'Yêu cầu không hợp lệ.']);
        }
        $result = $this->chat->staffSend(
            (int) $request->input('conversation_id'),
            $this->staffId(),
            $this->staffName(),
            (string) $request->input('message', '')
        );

        return response()->json(array_merge($result, Csrf::jsonMeta()));
    }

    private function jsonPost(Request $request, callable $action): mixed
    {
        if (! $request->isMethod('post')) {
            abort(404);
        }

        return response()->json(array_merge($action(), Csrf::jsonMeta()));
    }

    private function delete(Request $request, int $id): mixed
    {
        if (! $request->isMethod('post')) {
            abort(404);
        }
        $result = $this->chat->deleteConversation($id);
        $key = ! empty($result['ok']) ? 'message_success' : 'message_fail';

        return redirect('/admin/support-chat')->with($key, (string) ($result['message'] ?? 'Không xóa được hội thoại.'));
    }

    private function purge(Request $request): mixed
    {
        if (! $request->isMethod('post')) {
            abort(404);
        }
        if ((int) auth('admin')->user()?->level !== Admin::ROLE_ADMIN) {
            return redirect('/admin/support-chat')->with('message_fail', 'Chỉ Admin mới được xóa toàn bộ lịch sử chat.');
        }
        \App\Models\AiMessage::query()->delete();
        AiConversation::query()->delete();

        return redirect('/admin/support-chat')->with('message_success', 'Đã xóa toàn bộ lịch sử chat.');
    }

    private function staffId(): int
    {
        return (int) auth('admin')->id();
    }

    private function staffName(): string
    {
        return (string) (auth('admin')->user()->name ?? 'Nhân viên');
    }
}
