<?php

namespace App\Http\Requests\Admin;

use App\Support\Admin\AdminPermission;
use App\Support\Storefront\Loyalty;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StoreVoucherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return AdminPermission::can(Auth::guard('admin')->user(), 'voucher.manage');
    }

    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(
            redirect()->to(url('admin'))->with('message_fail', 'Bạn không có quyền quản lý voucher!')
        );
    }

    protected function prepareForValidation(): void
    {
        $type = (string) $this->input('discount_type');
        if (! in_array($type, ['percent', 'fixed'], true)) {
            $type = 'fixed';
        }

        $this->merge([
            'code' => Loyalty::normalizeVoucherCode($this->input('code')),
            'discount_type' => $type,
            'tier_min' => strtolower(trim((string) $this->input('tier_min'))),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $unique = Rule::unique('voucher', 'code');
        $id = (int) $this->route('id');
        if ($id > 0) {
            $unique = $unique->ignore($id);
        }

        return [
            'code' => ['required', 'string', 'max:64', $unique],
            'name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'discount_type' => ['required', 'in:percent,fixed'],
            'discount_value' => ['nullable', 'integer'],
            'min_order_amount' => ['nullable', 'integer'],
            'max_discount' => ['nullable', 'integer'],
            'tier_min' => ['nullable', 'string', 'max:32'],
            'user_id' => ['nullable', 'integer', 'min:0'],
            'usage_limit' => ['nullable', 'integer', 'min:0'],
            'per_user_limit' => ['nullable', 'integer'],
            'valid_from' => ['nullable', 'date'],
            'valid_to' => ['nullable', 'date'],
            'is_active' => ['nullable'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.required' => 'Mã voucher không hợp lệ.',
            'code.unique' => 'Mã voucher đã tồn tại.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        $id = (int) $this->route('id');
        $to = $id > 0 ? url('admin/voucher/edit/' . $id) : url('admin/voucher/add');

        throw new ValidationException(
            $validator,
            redirect()->to($to)->with('message_fail', (string) $validator->errors()->first())
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function voucherAttributes(): array
    {
        $from = trim((string) $this->input('valid_from', ''));
        $to = trim((string) $this->input('valid_to', ''));

        return [
            'code' => (string) $this->input('code'),
            'name' => trim((string) $this->input('name', '')),
            'description' => trim((string) $this->input('description', '')),
            'discount_type' => (string) $this->input('discount_type'),
            'discount_value' => (int) $this->input('discount_value', 0),
            'min_order_amount' => (int) $this->input('min_order_amount', 0),
            'max_discount' => (int) $this->input('max_discount', 0),
            'tier_min' => (string) $this->input('tier_min', ''),
            'user_id' => (int) $this->input('user_id', 0),
            'usage_limit' => (int) $this->input('usage_limit', 0),
            'per_user_limit' => max(1, (int) $this->input('per_user_limit', 1)),
            'valid_from' => $from !== '' ? (int) strtotime($from) : 0,
            'valid_to' => $to !== '' ? (int) strtotime($to . ' 23:59:59') : 0,
            'is_active' => $this->input('is_active') ? 1 : 0,
        ];
    }
}
