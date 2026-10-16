<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SyncLegacyUserSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard("web")->user();

        if ($user) {
            $request->session()->put("user", $user->only(["id", "name", "email", "phone", "address"]));
        }

        return $next($request);
    }
}
