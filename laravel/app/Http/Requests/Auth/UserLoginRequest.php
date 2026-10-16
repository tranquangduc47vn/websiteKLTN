<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * BƯỚC 3 — POST /dang-nhap
 *
 * Dịch từ CI3 `User::login()`:
 *   $this->form_validation->set_rules('email', 'Email đăng nhập', 'required|valid_email');
 *   $this->form_validation->set_rules('password', 'Mật khẩu', 'required');
 */
class UserLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:50'],
            'password' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Email đăng nhập không được để trống.',
            'email.email' => 'Email đăng nhập không hợp lệ.',
            'email.max' => 'Email đăng nhập tối đa 50 ký tự.',
            'password.required' => 'Mật khẩu không được để trống.',
        ];
    }
}
