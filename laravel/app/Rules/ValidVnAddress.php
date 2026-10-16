<?php

namespace App\Rules;

use App\Support\Storefront\VnAddress;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\ValidatorAwareRule;
use Illuminate\Validation\Validator;

/** CI3 User::validate_vn_address() — bộ ba tỉnh/quận/phường phải có trong JSON. */
class ValidVnAddress implements ValidationRule, ValidatorAwareRule
{
    private ?Validator $validator = null;

    public function setValidator(Validator $validator): static
    {
        $this->validator = $validator;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $data = $this->validator?->getData() ?? [];
        $lookup = VnAddress::lookup(
            $data['province_id'] ?? $value,
            $data['district_id'] ?? '',
            $data['ward_id'] ?? ''
        );

        if ($lookup === false) {
            $fail('Vui lòng chọn đúng Tỉnh/Thành, Quận/Huyện và Phường/Xã trên danh sách Việt Nam.');
        }
    }
}
