<?php

namespace App\Http\Requests\Admin;

use App\Support\Admin\AdminPermission;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class StoreSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return AdminPermission::can(Auth::guard('admin')->user(), 'stock.manage');
    }

    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(redirect()->to(url('admin/suppliers')));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:32'],
            'name' => ['required', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:128'],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'string', 'max:128'],
            'address' => ['nullable', 'string', 'max:512'],
            'note' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.required' => 'Vui lòng nhập mã nhà cung cấp.',
            'name.required' => 'Vui lòng nhập tên nhà cung cấp.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new ValidationException(
            $validator,
            redirect()->to(url('admin/suppliers/add'))->with('message_fail', (string) $validator->errors()->first())
        );
    }
}
