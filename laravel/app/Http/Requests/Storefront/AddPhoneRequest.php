<?php

namespace App\Http\Requests\Storefront;

use App\Rules\ValidUserPhone;

class AddPhoneRequest extends AccountFormRequest
{
    public function rules(): array
    {
        return [
            'phone_number' => ['required', 'string', new ValidUserPhone()],
            'phone_label' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone_number.required' => 'Số điện thoại không được để trống.',
            'phone_label.max' => 'Ghi chú không được vượt quá 50 ký tự.',
        ];
    }

    protected function redirectHash(): string
    {
        return '#phones';
    }
}
