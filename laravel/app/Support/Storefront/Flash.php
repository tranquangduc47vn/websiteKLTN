<?php

namespace App\Support\Storefront;

/**
 * B4 - Flash message storefront, port nguyên văn CI3 common_helper.php:
 *   set_store_flash(), get_store_flash(), set_product_flash(),
 *   store_infer_flash_type(), set_auth_flash(), get_auth_flash()
 *
 * B3 (auth) đã dùng session key message_success / message_fail của Laravel.
 * B4 phải ĐỌC/ghi cùng key đó để thông báo nhất quán, đồng thời giữ thêm các key
 * legacy CI3 (message, store_message_success/fail, product_message) để view gốc
 * vẫn đọc được.
 */
class Flash
{
    public const SUCCESS = 'success';
    public const FAIL = 'fail';
    public const INFO = 'info';

    /** CI3 set_store_flash(). */
    public static function store(string $type, string $message): void
    {
        $key = match ($type) {
            self::SUCCESS => 'store_message_success',
            self::INFO => 'store_message_info',
            default => 'store_message_fail',
        };

        session()->flash($key, $message);
    }

    /** CI3 store_infer_flash_type(). */
    public static function inferType(string $message): string
    {
        $text = mb_strtolower(trim($message), 'UTF-8');

        if ($text === '') {
            return self::FAIL;
        }

        if (preg_match(
            '/thành công|dạt hàng thành công|thêm vào giỏ hàng thành công/i',
            $text
        )) {
            return self::SUCCESS;
        }

        return self::FAIL;
    }

    /**
     * CI3 get_store_flash(): ưu tiên key mới, rồi legacy 'message'
     * (với phân loại tự động như CI3).
     *
     * @return array{success:string,fail:string,info:string}
     */
    public static function storeAll(): array
    {
        $session = session();

        $success = (string) ($session->pull('store_message_success', '') ?: '');
        $fail = (string) ($session->pull('store_message_fail', '') ?: '');
        $info = (string) ($session->pull('store_message_info', '') ?: '');

        $legacy = (string) ($session->pull('message', '') ?: '');
        if ($legacy !== '') {
            if (self::inferType($legacy) === self::SUCCESS) {
                $success = $success !== '' ? $success : $legacy;
            } else {
                $fail = $fail !== '' ? $fail : $legacy;
            }
        }

        return compact('success', 'fail', 'info');
    }

    /** CI3 set_product_flash(). */
    public static function product(string $message): void
    {
        session()->flash('product_message', $message);
    }

    /**
     * CI3 set_auth_flash(). Trang đăng nhập Laravel (B3) đọc key message_success/message_fail.
     */
    public static function auth(string $type, string $message): void
    {
        session()->flash($type === self::SUCCESS ? 'message_success' : 'message_fail', $message);
    }

    /**
     * Gom flash cho view. B3 auth dùng key message_success/message_fail,
     * B4 dùng key store_message_* + message legacy. Trả mảng cho view.
     *
     * Kết quả được giữ trên request hiện tại: composer chạy cho mọi view/partial của trang
     * và lần đọc đầu đã pull flash khỏi session.
     *
     * @return array{success:string,fail:string,info:string}
     */
    public static function all(): array
    {
        $request = request();

        if (! $request->attributes->has('storefront.flash')) {
            $request->attributes->set('storefront.flash', self::collect());
        }

        return $request->attributes->get('storefront.flash');
    }

    /**
     * @return array{success:string,fail:string,info:string}
     */
    private static function collect(): array
    {
        $store = self::storeAll();

        $session = session();
        $authSuccess = (string) ($session->pull('message_success', '') ?: '');
        $authFail = (string) ($session->pull('message_fail', '') ?: '');

        return [
            'success' => $store['success'] !== '' ? $store['success'] : $authSuccess,
            'fail' => $store['fail'] !== '' ? $store['fail'] : $authFail,
            'info' => $store['info'],
        ];
    }
}
