<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $price = str_replace(',', '', (string) $this->input('price', ''));
        $this->merge([
            'price_value' => is_numeric($price) ? (int) $price : $price,
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'catalog_id' => ['required', 'integer', 'exists:catalog,id'],
            'price' => ['required'],
            'price_value' => ['integer', 'min:0'],
            'discount' => ['nullable'],
            'content' => ['nullable', 'string'],
            'color' => ['nullable', 'array'],
            'color.*' => ['string', 'max:64'],
            'size' => ['nullable', 'array'],
            'size.*' => ['string', 'max:32'],
            'image' => ['required', 'file', 'max:5120'],
            'list_image' => ['nullable', 'array'],
            'list_image.*' => ['file', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Tên sản phẩm không được để trống.',
            'catalog_id.required' => 'Danh mục không được để trống.',
            'catalog_id.exists' => 'Danh mục không hợp lệ.',
            'price.required' => 'Giá sản phẩm không được để trống.',
            'price_value.integer' => 'Giá sản phẩm không hợp lệ.',
            'price_value.min' => 'Giá sản phẩm không được âm.',
            'image.required' => 'Không tải được ảnh đại diện.',
            'image.max' => 'Ảnh vượt quá 5MB.',
            'list_image.*.max' => 'Ảnh kèm theo vượt quá 5MB.',
        ];
    }
}
