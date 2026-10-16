<?php

namespace App\Services\Checkout;

use App\Models\Transaction;

final class CheckoutResult
{
    public const REDIRECT_CART = 'cart';
    public const REDIRECT_CHECKOUT = 'checkout';
    public const REDIRECT_QR = 'qr';
    public const REDIRECT_HOME = 'home';
    public const REDIRECT_USER = 'user';

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public readonly bool $ok,
        public readonly string $message,
        public readonly string $redirect,
        public readonly string $flash = 'fail',
        public readonly ?Transaction $transaction = null,
        public readonly array $data = [],
    ) {
    }

    public static function fail(string $message, string $redirect, string $flash = 'fail'): self
    {
        return new self(false, $message, $redirect, $flash);
    }

    public static function success(string $message, string $redirect, ?Transaction $transaction = null, string $flash = 'success'): self
    {
        return new self(true, $message, $redirect, $flash, $transaction);
    }
}
