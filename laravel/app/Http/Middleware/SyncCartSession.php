<?php

namespace App\Http\Middleware;

use App\Services\CartSessionService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Port phần giỏ hàng trong CI3 MY_Controller::__construct() (mọi trang trừ /admin):
 *   - khách đã đăng nhập: cart_ensure_for_logged_in_user() — giỏ session trống thì nạp từ user_saved_cart;
 *   - khách chưa đăng nhập: unset custom_cart (storefront không có giỏ cho guest).
 */
class SyncCartSession
{
    public function __construct(private readonly CartSessionService $cart)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->hasSession() && $request->segment(1) !== 'admin') {
            $user = Auth::guard('web')->user();

            if ($user) {
                $this->cart->ensureForLoggedInUser($request->session(), $user);
            } else {
                $this->cart->forgetSessionCart($request->session());
            }
        }

        return $next($request);
    }
}
