<?php

namespace App\Http\Requests\Storefront;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validate form checkout — độ dài theo schema `transaction`.
 * Không nhận price / subtotal / total / discount / stock từ client.
 */
class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:100'],
            'phone' => ['required', 'string', 'max:100'],
            'address' => ['required', 'string', 'max:255'],
            'message' => ['nullable', 'string', 'max:255'],
            'payment' => ['nullable', 'string', 'max:32'],
            'voucher_code' => ['nullable', 'string', 'max:40'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'Họ tên',
            'email' => 'Email đăng nhập',
            'phone' => 'Điện thoại',
            'address' => 'Địa chỉ',
            'message' => 'Ghi chú',
        ];
    }
}
