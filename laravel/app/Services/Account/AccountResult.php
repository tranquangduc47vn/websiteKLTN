<?php

namespace App\Services\Account;

final class AccountResult
{
    public function __construct(
        public readonly bool $ok,
        public readonly string $message,
        public readonly string $hash = '',
    ) {
    }

    public function redirect(): string
    {
        return url('user') . $this->hash;
    }
}
