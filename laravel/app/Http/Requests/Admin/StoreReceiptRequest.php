<?php

namespace App\Http\Requests\Admin;

use App\Support\Admin\AdminPermission;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class StoreReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return AdminPermission::can(Auth::guard('admin')->user(), 'stock.receipt');
    }

    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(
            redirect()->to(url('admin/stock-receipts'))->with('message_fail', 'Bạn không có quyền tạo phiếu nhập kho.')
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'supplier_id' => ['nullable', 'integer'],
            'note' => ['nullable', 'string'],
            'variant_id' => ['nullable', 'array'],
            'variant_id.*' => ['nullable', 'integer'],
            'product_id' => ['nullable', 'array'],
            'qty' => ['nullable', 'array'],
            'unit_cost' => ['nullable', 'array'],
            'size' => ['nullable', 'array'],
            'color' => ['nullable', 'array'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new ValidationException(
            $validator,
            redirect()->back()->with('message_fail', (string) $validator->errors()->first())
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function lines(): array
    {
        $variantIds = (array) $this->input('variant_id', []);
        $productIds = (array) $this->input('product_id', []);
        $sizes = (array) $this->input('size', []);
        $colors = (array) $this->input('color', []);
        $qtys = (array) $this->input('qty', []);
        $costs = (array) $this->input('unit_cost', []);
        $rows = $variantIds !== [] ? $variantIds : $productIds;
        $lines = [];

        foreach ($rows as $i => $_) {
            $line = [
                'qty' => $qtys[$i] ?? 0,
                'unit_cost' => $costs[$i] ?? 0,
            ];
            if (! empty($variantIds[$i])) {
                $line['variant_id'] = (int) $variantIds[$i];
            } else {
                $line['product_id'] = (int) ($productIds[$i] ?? 0);
                $line['size'] = (string) ($sizes[$i] ?? '');
                $line['color'] = (string) ($colors[$i] ?? '');
            }
            if (! empty($line['variant_id']) || ! empty($line['product_id'])) {
                $lines[] = $line;
            }
        }

        return $lines;
    }
}
