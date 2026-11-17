<?php

namespace App\Support\Admin;

use App\Models\AiConversation;
use App\Models\AiMessage;

/**
 * Nhãn và hiển thị danh sách hội thoại hỗ trợ (admin).
 */
final class SupportChatPresenter
{
    /** @return array<string, string> */
    public static function statusLabels(): array
    {
        return [
            'ai_active' => 'AI đang tư vấn',
            'waiting_staff' => 'Chờ nhân viên hỗ trợ',
            'staff_joined' => 'Nhân viên đang hỗ trợ',
            'closed' => 'Đã đóng',
        ];
    }

    public static function statusLabel(?string $status): string
    {
        $key = trim((string) $status);

        return self::statusLabels()[$key] ?? ($key !== '' ? $key : '—');
    }

    public static function statusBadgeClass(?string $status): string
    {
        return match ($status) {
            'ai_active' => 'adm-sc-badge--ai',
            'waiting_staff' => 'adm-sc-badge--waiting',
            'staff_joined' => 'adm-sc-badge--staff',
            'closed' => 'adm-sc-badge--closed',
            default => 'adm-sc-badge--default',
        };
    }

    public static function customerLabel(AiConversation $conversation): string
    {
        if ((int) $conversation->user_id <= 0) {
            return 'Khách (chưa đăng nhập)';
        }

        $user = $conversation->relationLoaded('user')
            ? $conversation->user
            : $conversation->user()->first();

        if (! $user) {
            return 'User #' . (int) $conversation->user_id;
        }

        $name = trim((string) $user->name);
        if ($name !== '') {
            return $name;
        }

        $email = trim((string) $user->email);

        return $email !== '' ? $email : ('User #' . (int) $conversation->user_id);
    }

    public static function staffLabel(AiConversation $conversation): string
    {
        $name = trim((string) $conversation->staff_name);

        return $name !== '' ? $name : '—';
    }

    public static function lastActivityAt(AiConversation $conversation): ?\Illuminate\Support\Carbon
    {
        if ($conversation->last_message_at) {
            return $conversation->last_message_at;
        }

        $last = $conversation->relationLoaded('lastMessage')
            ? $conversation->lastMessage
            : null;
        if ($last && $last->created_at) {
            return $last->created_at;
        }

        return $conversation->started_at;
    }

    public static function messagePreview(?AiMessage $message, int $maxLen = 72): string
    {
        if (! $message) {
            return '—';
        }
        $text = trim(strip_tags((string) $message->content));
        $text = preg_replace('/\s+/u', ' ', $text) ?? '';
        if ($text === '') {
            return '—';
        }

        return mb_strlen($text) > $maxLen
            ? mb_substr($text, 0, $maxLen - 1) . '…'
            : $text;
    }

    /**
     * DB chỉ có cờ unread_staff (0/1), không đếm số tin — hiển thị trung thực.
     */
    public static function unreadStaffLabel(AiConversation $conversation): string
    {
        return (bool) $conversation->unread_staff ? 'Có' : '—';
    }
}