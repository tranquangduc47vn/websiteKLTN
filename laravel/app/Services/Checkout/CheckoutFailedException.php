<?php

namespace App\Services\Checkout;

use RuntimeException;

final class CheckoutFailedException extends RuntimeException
{
    public function __construct(string $message, public readonly string $redirect)
    {
        parent::__construct($message);
    }
}
