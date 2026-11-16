<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AdminLoginRequest;
use App\Models\Admin;
use App\Support\LegacyPassword;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * BƯỚC 3 — AUTHENTICATION (phía admin)
 *
 * Dịch từ `application/controllers/admin/Login.php` (CodeIgniter 3):
 *   - GET  /admin/login  -> index()      : nếu đã đăng nhập thì về dashboard, ngược lại render form.
 *   - POST /admin/login  -> check_login(): rate-limit theo IP+email (5 lần / 15 phút),
 *                             verify mật khẩu (bcrypt HOẶC MD5 legacy), kiểm tra
 *                             `admin_can_access_panel()`, đăng nhập guard `admin`.
 *
 * Khác biệt có chủ đích so với CI3:
 *   - Session lưu qua guard `admin`. Không ghi session key `login`.
 *   - Rate-limit dùng RateLimiter của Laravel (cache store `file`, CACHE_STORE=file)
 *     thay cho rate_limit_helper.php của CI3 — cùng ngữ nghĩa: 5 lần / 900 giây.
 *   - Mật khẩu MD5 legacy được rehash bcrypt ngay khi đăng nhập thành công
 *     (App\Auth\LegacyPasswordUserProvider), không reset hàng loạt.
 *
 * Business logic giữ nguyên, chỉ đổi lớp triển khai sang Laravel.
 */
class LoginController extends Controller
{
    /** Số lần thử tối đa trước khi khóa (khớp rate_limit_helper.php). */
    private const MAX_ATTEMPTS = 5;

    /** Cửa sổ khóa tính bằng giây (khớp rate_limit_helper.php). */
    private const DECAY_SECONDS = 900;

    /**
     * GET /admin/login — trang đăng nhập.
     *
     * CI3: nếu session `login` đã tồn tại và `admin_can_access_panel()` trả true
     * thì redirect `admin/home`.
     */
    public function index(): View|RedirectResponse
    {
        $admin = Auth::guard('admin')->user();

        if ($admin && $this->canAccessPanel($admin)) {
            return redirect()->route('admin.home');
        }

        return view('auth.admin-login');
    }

    /**
     * POST /admin/login — xác thực tài khoản quản trị.
     *
     * CI3: check_login() (form_validation callback) + get_info_login().
     */
    public function checkLogin(AdminLoginRequest $request): RedirectResponse
    {
        $email = trim((string) $request->input('email'));
        $password = (string) $request->input('password');

        $throttleKey = $this->throttleKey($email, $request->ip());

        // 1. Kiểm tra đã vượt quá giới hạn lần thử chưa.
        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            return $this->lockedOut($request, $throttleKey);
        }

        // 2. Tìm tài khoản theo email (CI3: get_info_rule(['email' => $email])).
        $admin = Admin::where('email', $email)->first();

        if (! $admin) {
            RateLimiter::hit($throttleKey, self::DECAY_SECONDS);

            return $this->loginFailed($request, 'Đăng nhập thất bại');
        }

        // 3. Xác thực mật khẩu: bcrypt trước, MD5 legacy sau (App\Support\LegacyPassword).
        $verified = LegacyPassword::verify($password, (string) $admin->password);

        if (! $verified['verified']) {
            RateLimiter::hit($throttleKey, self::DECAY_SECONDS);

            return $this->loginFailed($request, 'Đăng nhập thất bại');
        }

        // 4. Kiểm tra quyền truy cập panel (CI3: admin_can_access_panel()).
        if (! $this->canAccessPanel($admin)) {
            return $this->loginFailed($request, 'Tài khoản không có quyền truy cập khu vực quản trị.');
        }

        // 5. Nâng cấp hash MD5 -> bcrypt khi đăng nhập thành công (migrate dần, không reset hàng loạt).
        if ($verified['needsRehash']) {
            $admin->forceFill([
                $admin->getAuthPasswordName() => LegacyPassword::hash($password),
            ])->save();
        }

        RateLimiter::clear($throttleKey);

        // 6. Đăng nhập + chống session fixation.
        Auth::guard('admin')->login($admin);
        $request->session()->regenerate();

        return redirect()
            ->intended(route('admin.home'))
            ->with('message_success', 'Đăng nhập thành công');
    }

    /**
     * CI3: admin_can_access_panel($login)
     * level phải thuộc {ROLE_ADMIN=0, ROLE_MOD=1, ROLE_USER=2}.
     */
    private function canAccessPanel(Admin $admin): bool
    {
        return in_array(
            (int) $admin->level,
            [Admin::ROLE_ADMIN, Admin::ROLE_MOD, Admin::ROLE_USER],
            true
        );
    }

    /**
     * CI3: rate_limit_storage_key() = 'rl_' . md5(ip . '|' . strtolower(email)).
     * Laravel RateLimiter đã tự thêm prefix, nên chỉ cần giữ cùng nguyên tắc IP + email.
     */
    private function throttleKey(string $email, ?string $ip): string
    {
        return 'admin-login|' . Str::lower($email) . '|' . ($ip ?? 'unknown');
    }

    /**
     * CI3: rate_limit_message() — quy đổi sang phút, tối thiểu 1 phút.
     */
    private function lockedOut(AdminLoginRequest $request, string $throttleKey): RedirectResponse
    {
        $seconds = RateLimiter::availableIn($throttleKey);
        $minutes = max(1, (int) ceil($seconds / 60));

        return back()
            ->withInput($request->only('email'))
            ->with('message_fail', 'Quá nhiều lần đăng nhập sai. Vui lòng thử lại sau ' . $minutes . ' phút.');
    }

    private function loginFailed(AdminLoginRequest $request, string $message): RedirectResponse
    {
        return back()
            ->withInput($request->only('email'))
            ->with('message_fail', $message);
    }
}
