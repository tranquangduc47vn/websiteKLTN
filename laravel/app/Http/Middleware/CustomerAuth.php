<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CustomerAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::guard("web")->check()) {
            if ($request->expectsJson()) {
                return response()->json(["message" => "Unauthenticated."], 401);
            }
            $request->session()->put("redirect_back", $request->fullUrl());
            return redirect()->route("storefront.user.login");
        }
        return $next($request);
    }
}
