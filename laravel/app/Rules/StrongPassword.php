<?php

namespace App\Rules;

use App\Support\LegacyPassword;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Dịch `password_helper.php::password_strength_message()` của CI3:
 *   - >= 8 ký tự
 *   - >= 1 chữ cái (hỗ trợ ký tự tiếng Việt)
 *   - >= 1 chữ số
 */
class StrongPassword implements ValidationRule
{
    public function __construct(private readonly bool $checkStrength = true)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $this->checkStrength) {
            return;
        }

        $message = LegacyPassword::strengthMessage((string) $value);

        if ($message !== '') {
            $fail($message);
        }
    }
}
