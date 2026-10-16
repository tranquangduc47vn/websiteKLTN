<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** CI3 User::validate_user_phone() — 9–15 chữ số sau khi bỏ khoảng trắng. */
class ValidUserPhone implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $phone = preg_replace('/\s+/', '', trim((string) $value)) ?? '';
        if ($phone === '') {
            $fail('Số điện thoại không được để trống.');

            return;
        }

        $digits = preg_replace('/\D/', '', $phone) ?? '';
        if (strlen($digits) < 9 || strlen($digits) > 15) {
            $fail('Số điện thoại không hợp lệ (9–15 chữ số).');
        }
    }
}
