<?php

namespace App\Http\Requests\Storefront;

class UpdateProfileRequest extends AccountFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Họ tên không được để trống.',
            'name.max' => 'Họ tên không được vượt quá 100 ký tự.',
        ];
    }

    protected function redirectHash(): string
    {
        return '#jm-account-profile';
    }
}
