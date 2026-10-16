<?php

namespace App\Support\Storefront;

/**
 * B4 - CSRF giống CI3 (csrf_helper.php) nhưng trên cơ chế Laravel.
 * Giữ NGUYÊN ngữ cảnh JS cũ (window.CI_CSRF, window.appendCsrf,
 * window.applyCsrfResponse) để AJAX/view cũ tiếp tục chạy.
 *
 * Laravel luôn bật CSRF qua middleware VerifyCsrfToken.
 */
class Csrf
{
    /** csrf_bootstrap_script() cua CI3. */
    public static function bootstrapScript(): string
    {
        $payload = json_encode(
            ['name' => '_token', 'hash' => self::token()],
            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
        );

        return view('layouts.storefront.partials.csrf-bootstrap', ['payload' => $payload])->render();
    }

    /** csrf_form_inject_script() cua CI3. */
    public static function injectScript(): string
    {
        return view('layouts.storefront.partials.csrf-inject')->render();
    }

    /** csrf_field() -> @csrf cua Blade. */
    public static function field(): string
    {
        return '<input type="hidden" name="_token" value="' . e(self::token()) . '">';
    }

    /** csrf_json_meta() - mang cho response AJAX. */
    public static function jsonMeta(): array
    {
        return ['csrf_name' => '_token', 'csrf_hash' => self::token()];
    }

    public static function token(): string
    {
        return csrf_token();
    }
}
