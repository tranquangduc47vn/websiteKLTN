<?php

namespace App\Http\Requests\Auth;

use App\Rules\StrongPassword;
use Illuminate\Foundation\Http\FormRequest;

/**
 * BƯỚC 3 — POST /dat-lai-mat-khau
 *
 * Dịch từ CI3 `User::reset_password()`:
 *   password      required|callback_validate_password_strength
 *   re_password   required|matches[password]
 *
 * Token được resolve ở controller (từ route {token} hoặc input('token')).
 */
class UserResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'password' => ['required', 'string', new StrongPassword()],
            're_password' => ['required', 'string', 'same:password'],
        ];
    }

    public function messages(): array
    {
        return [
            'password.required' => 'Mật khẩu mới không được để trống.',
            're_password.same' => 'Mật khẩu nhập lại không khớp.',
        ];
    }
}
