<?php

namespace App\Services;

use App\Models\PasswordReset;
use App\Models\User;

/**
 * BƯỚC 3 — Quản lý token đặt lại mật khẩu.
 *
 * Dịch 1-1 từ `application/models/Password_reset_model.php` (CodeIgniter 3) để giữ
 * nguyên TƯƠNG THÍCH với dữ liệu đang có trong bảng `password_resets`:
 *
 *   - token plain: bin2hex(random_bytes(32))              -> 64 ký tự hex
 *   - token lưu:   hash('sha256', $token_plain)            -> `token_hash` varchar(64)
 *   - hết hạn:     15 phút (EXPIRE_SECONDS = 900)         -> `expires_at` INT Unix
 *   - dùng rồi:    `used_at` = time()                      -> token vô hiệu
 *   - khi tạo token mới: vô hiệu hoá TẤT CẢ token chưa dùng của user đó.
 *
 * KHÔNG dùng Password broker mặc định của Laravel vì bảng `password_resets` của
 * dự án có schema khác (token_hash/expires_at/used_at thay vì email/token/created_at).
 * Giữ nguyên schema = không tạo migration, không mất token đang dở.
 */
class PasswordResetService
{
    /** Hết hạn sau 15 phút — khớp Password_reset_model::EXPIRE_SECONDS. */
    public const EXPIRE_SECONDS = 900;

    /**
     * Tạo token cho user, vô hiệu hoá các token cũ chưa dùng.
     *
     * @return string token PLAIN (chỉ trả về để đưa vào email, không lưu)
     */
    public function createToken(User $user): string
    {
        $this->invalidateUserTokens($user->id);

        $plain = bin2hex(random_bytes(32));

        PasswordReset::create([
            'user_id' => (int) $user->id,
            'token_hash' => $this->hashToken($plain),
            'expires_at' => time() + self::EXPIRE_SECONDS,
            'used_at' => 0,
            'created' => time(),
        ]);

        return $plain;
    }

    /**
     * Tìm token còn hiệu lực theo token plain.
     */
    public function findValidByToken(string $plain): ?PasswordReset
    {
        $plain = trim($plain);

        if ($plain === '') {
            return null;
        }

        return PasswordReset::query()
            ->where('token_hash', $this->hashToken($plain))
            ->where('used_at', 0)
            ->where('expires_at', '>', time())
            ->first();
    }

    /**
     * Đánh dấu token đã dùng.
     */
    public function markUsed(int $id): bool
    {
        return PasswordReset::query()
            ->where('id', $id)
            ->update(['used_at' => time()]) > 0;
    }

    /**
     * Vô hiệu hoá toàn bộ token chưa dùng của user.
     */
    public function invalidateUserTokens(int $userId): void
    {
        if ($userId <= 0) {
            return;
        }

        PasswordReset::query()
            ->where('user_id', $userId)
            ->where('used_at', 0)
            ->update(['used_at' => time()]);
    }

    private function hashToken(string $plain): string
    {
        return hash('sha256', $plain);
    }
}
