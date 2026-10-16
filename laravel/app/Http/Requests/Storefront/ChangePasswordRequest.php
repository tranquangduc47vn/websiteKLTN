<?php

namespace App\Http\Requests\Storefront;

use App\Rules\StrongPassword;

class ChangePasswordRequest extends AccountFormRequest
{
    public function rules(): array
    {
        return [
            'old_password' => ['required', 'string'],
            'password' => ['required', 'string', new StrongPassword()],
            're_password' => ['required', 'string', 'same:password'],
        ];
    }

    public function messages(): array
    {
        return [
            'old_password.required' => 'Mật khẩu hiện tại không được để trống.',
            'password.required' => 'Mật khẩu mới không được để trống.',
            're_password.required' => 'Nhập lại mật khẩu mới không được để trống.',
            're_password.same' => 'Nhập lại mật khẩu mới không khớp.',
        ];
    }

    protected function redirectHash(): string
    {
        return '#jm-account-profile';
    }
}
