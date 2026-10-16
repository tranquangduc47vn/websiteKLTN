<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class AdminLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            "email" => ["required", "email", "max:100"],
            "password" => ["required", "string", "min:1"],
        ];
    }

    public function messages(): array
    {
        return [
            "email.required" => "Email kh\u00F4ng \u0111\u01B0\u1EE3c \u0111\u1EC3 tr\u1ED1ng.",
            "email.email" => "Email kh\u00F4ng h\u1EE3p l\u1EC7.",
            "password.required" => "M\u1EADt kh\u1EA9u kh\u00F4ng \u0111\u01B0\u1EE3c \u0111\u1EC3 tr\u1ED1ng.",
        ];
    }
}
