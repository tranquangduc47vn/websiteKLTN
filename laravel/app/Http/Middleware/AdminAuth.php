<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AdminAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::guard("admin")->check()) {
            return $request->expectsJson()
                ? response()->json(["message" => "Unauthenticated."], 401)
                : redirect()->route("admin.login");
        }

        $admin = Auth::guard("admin")->user();

        if (!$admin || !in_array((int) ($admin->level ?? -1), [0, 1, 2], true)) {
            Auth::guard("admin")->logout();
            return redirect()->route("admin.login")
                ->with("message_fail", "Tài khoản không có quyền truy cập khu vực quản trị.");
        }

        return $next($request);
    }
}
