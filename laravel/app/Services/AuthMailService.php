<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * BƯỚC 3 — Email cho luồng auth storefront.
 *
 * Dịch từ `application/libraries/Mail_service.php::send_welcome()` và
 * `::send_password_reset()` — giữ nguyên nội dung/tiêu đề/link.
 *
 * Khác biệt: dùng Mail facade của Laravel (MAIL_MAILER=log trong .env hiện tại)
 * thay vì SMTP thủ công. Không hard-code credentials — đọc từ `config/mail.php`.
 *
 * Lỗi gửi mail KHÔNG được làm hỏng luồng auth: bắt exception và ghi log,
 * đúng như CI3 (`log_message('error', ...)`) rồi vẫn trả về false.
 */
class AuthMailService
{
    /**
     * Email chào mừng sau khi đăng ký.
     */
    public function sendWelcome(User $user): bool
    {
        return $this->send(
            (string) $user->email,
            'Chào mừng bạn đến với ' . $this->shopName(),
            $this->welcomeBody($user)
        );
    }

    /**
     * Email liên kết đặt lại mật khẩu.
     */
    public function sendPasswordReset(User $user, string $token): bool
    {
        if ($token === '') {
            return false;
        }

        $link = url('dat-lai-mat-khau/' . $token);

        $body = '<div style="font-family:Arial,sans-serif;line-height:1.6;color:#222;">'
            . '<p>Xin chào ' . e($user->name) . ',</p>'
            . '<p>Bạn (hoặc ai đó) đã yêu cầu đặt lại mật khẩu. Nhấn liên kết bên dưới trong <strong>15 phút</strong>:</p>'
            . '<p><a href="' . e($link) . '" style="display:inline-block;padding:10px 18px;background:#111;color:#fff;text-decoration:none;border-radius:4px;">Đặt lại mật khẩu</a></p>'
            . '<p style="word-break:break-all;font-size:12px;color:#666;">' . e($link) . '</p>'
            . '<p style="color:#888;font-size:12px;">Nếu bạn không yêu cầu, hãy bỏ qua email này. Liên kết chỉ dùng được một lần.</p>'
            . '</div>';

        return $this->send((string) $user->email, 'Đặt lại mật khẩu — ' . $this->shopName(), $body);
    }

    private function welcomeBody(User $user): string
    {
        $loginUrl = url('dang-nhap');

        return '<div style="font-family:Arial,sans-serif;line-height:1.6;color:#222;">'
            . '<h2 style="color:#111;">Xin chào ' . e($user->name) . '!</h2>'
            . '<p>Cảm ơn bạn đã đăng ký tài khoản tại <strong>' . e($this->shopName()) . '</strong>.</p>'
            . '<p>Bạn có thể đăng nhập và mua sắm bất cứ lúc nào tại '
            . '<a href="' . e($loginUrl) . '">' . e($loginUrl) . '</a>.</p>'
            . '<p style="color:#888;font-size:12px;">Email tự động — vui lòng không trả lời.</p>'
            . '</div>';
    }

    private function send(string $to, string $subject, string $html): bool
    {
        if ($to === '') {
            return false;
        }

        try {
            Mail::html($html, function ($message) use ($to, $subject) {
                $message->to($to)->subject($subject);
            });

            return true;
        } catch (\Throwable $e) {
            Log::error('AuthMailService send failed to ' . $to . ': ' . $e->getMessage());

            return false;
        }
    }

    private function shopName(): string
    {
        return (string) config('app.name', 'Webshop');
    }
}
