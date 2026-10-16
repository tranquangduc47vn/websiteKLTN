<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * BƯỚC 3 — POST /quen-mat-khau
 *
 * Dịch từ CI3 `User::forgot_password()`:
 *   $this->form_validation->set_rules('email', 'Email', 'required|valid_email|trim');
 */
class UserForgotPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Email không được để trống.',
            'email.email' => 'Email không hợp lệ.',
        ];
    }
}
