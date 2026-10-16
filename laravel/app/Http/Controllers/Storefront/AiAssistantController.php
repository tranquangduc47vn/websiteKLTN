<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Services\Ai\ChatService;
use App\Support\Storefront\Csrf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * AJAX widget hỗ trợ khách. URL giữ /ai-assistant/send|history|poll.
 */
class AiAssistantController extends Controller
{
    public function __construct(private readonly ChatService $chat) {}

    public function send(Request $request): JsonResponse
    {
        if ($denied = $this->loginRequired()) {
            return $denied;
        }
        if (! $request->isMethod('post')) {
            return response()->json(['ok' => false, 'message' => 'Yêu cầu không hợp lệ.']);
        }

        try {
            $result = $this->chat->customerSend((string) $request->input('message', ''), (int) $request->input('conversation_id', 0));
        } catch (Throwable $e) {
            report($e);
            $result = ['ok' => false, 'message' => 'Lỗi máy chủ khi xử lý tin nhắn.'];
        }

        return response()->json(array_merge($result, Csrf::jsonMeta()));
    }

    public function history(Request $request): JsonResponse
    {
        if ($denied = $this->loginRequired()) {
            return $denied;
        }
        $id = (int) $request->query('conversation_id', 0);
        if ($id <= 0) {
            return response()->json(['ok' => true, 'messages' => []]);
        }

        return response()->json($this->chat->history($id));
    }

    public function poll(Request $request): JsonResponse
    {
        if ($denied = $this->loginRequired()) {
            return $denied;
        }

        return response()->json($this->chat->poll(
            (int) $request->query('conversation_id', 0),
            (int) $request->query('after_id', 0)
        ));
    }

    private function loginRequired(): ?JsonResponse
    {
        if (auth('web')->check()) {
            return null;
        }

        return response()->json([
            'ok' => false,
            'login_required' => true,
            'message' => 'Bạn cần đăng nhập tài khoản để sử dụng hỗ trợ khách hàng.',
        ]);
    }
}
