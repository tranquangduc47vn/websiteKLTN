<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Header an toàn cho response web. Không đặt CSP hay HSTS.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        return self::apply($next($request), $request);
    }

    public static function apply(Response $response, ?Request $request = null): Response
    {
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        $path = trim(($request ?? request())->path(), '/');
        foreach (['gio-hang', 'thanh-toan', 'dang-nhap', 'dang-ky', 'quen-mat-khau', 'dat-lai-mat-khau', 'user', 'admin'] as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
                break;
            }
        }

        header_remove('X-Powered-By');
        $response->headers->remove('X-Powered-By');

        return $response;
    }
}
