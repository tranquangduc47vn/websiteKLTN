<?php

namespace App\Http\Requests\Admin;

use App\Support\Admin\AdminPermission;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AdjustStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return AdminPermission::can(Auth::guard('admin')->user(), 'inventory.adjust');
    }

    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(
            redirect()->to(url('admin/inventory'))->with(
                'message_fail',
                'Chỉ Admin mới được điều chỉnh số lượng tồn. Mod chỉ xem kiểm kê.'
            )
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'variant_id' => ['required', 'integer', 'min:1'],
            'new_qty' => ['required', 'integer', 'min:0'],
            'note' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'new_qty.min' => 'Số lượng tồn không được âm.',
            'new_qty.integer' => 'Số lượng tồn không được âm.',
            'note.required' => 'Vui lòng nhập lý do điều chỉnh.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        $variantId = (int) $this->input('variant_id');
        $to = $variantId > 0 ? url('admin/inventory/adjust/' . $variantId) : url('admin/inventory');

        throw new ValidationException(
            $validator,
            redirect()->to($to)->with('message_fail', (string) $validator->errors()->first())
        );
    }
}
