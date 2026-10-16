<?php

namespace App\Http\Middleware;

use App\Support\Admin\AdminPermission as Permission;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Chặn action admin khi không đủ quyền — cùng hướng CI3: flash + redirect /admin
 * (không 403). Level lấy từ guard, không từ request.
 */
class AdminPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $admin = Auth::guard('admin')->user();
        if (! Permission::can($admin, $permission)) {
            return redirect()
                ->route('admin.home')
                ->with('message_fail', 'Bạn không có quyền truy cập khu vực này!');
        }

        return $next($request);
    }
}
