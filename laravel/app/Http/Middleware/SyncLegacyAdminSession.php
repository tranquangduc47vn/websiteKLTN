<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * BƯỚC 3 — MIDDLEWARE `admin.session`
 *
 * Mục tiêu: đồng bộ TRẠNG THÁI xác thực giữa Laravel guard và session key `login`
 * của hệ thống CodeIgniter 3 cũ, để code/views CI3 còn lại đọc được đúng trạng thái.
 *
 * Nguồn xác thực DUY NHẤT là `Auth::guard('admin')` (session guard `admin`).
 * Session key `login` chỉ là MIRROR đọc-được (read-only mirror), KHÔNG phải auth.
 *
 * Chiều A (đồng bộ đúng):
 *   Auth guard có admin  → session `login` = object 4 cột mà CI3 view/helper đọc
 *                          (`id`, `name`, `email`, `level`).
 *
 * Chiều B (chống "login giả"):
 *   Auth guard không có admin (guest / vừa logout) → XÓA session `login`.
 *   Không bao giờ để `login` còn đó trong khi guard đã đăng xuất — nếu không,
 *   các module CI3 cũ sẽ tin là vẫn đang đăng nhập.
 *
 * Shape mirror lấy từ code CI3 thật:
 *   - header.php: $login->name, $login->level, $login->id
 *   - admin_dashboard_helper.php: $login->level
 *   - permission_helper.php: $login->level
 * KHÔNG bao giờ mirror `password` vào session (bảo mật).
 *
 * Được gắn trong group `admin.auth` (route middleware) — thứ tự: `admin.auth` kiểm
 * tra quyền TRƯỚC, `admin.session` đồng bộ SAU, `$next()` chạy controller.
 */
class SyncLegacyAdminSession
{
    /** Cột CI3 thực sự đọc từ session `login`. */
    private const LEGACY_LOGIN_KEYS = ['id', 'name', 'email', 'level'];

    /** Session key của CodeIgniter 3. */
    private const LEGACY_SESSION_KEY = 'login';

    public function handle(Request $request, Closure $next): Response
    {
        $admin = Auth::guard('admin')->user();

        if ($admin) {
            // Chiều A — mirror đúng trạng thái guard hiện tại (ghi đè để luôn mới nhất,
            // ví dụ admin sửa profile/level thì session mirror được cập nhật).
            $request->session()->put(
                self::LEGACY_SESSION_KEY,
                $admin->only(self::LEGACY_LOGIN_KEYS)
            );
        } elseif ($request->session()->has(self::LEGACY_SESSION_KEY)) {
            // Chiều B — guard đã đăng xuất (khách, hoặc sau AdminController@logout)
            // thì KHÔNG được giữ session `login` ở trạng thái đăng nhập giả.
            $request->session()->forget(self::LEGACY_SESSION_KEY);
        }

        return $next($request);
    }
}
