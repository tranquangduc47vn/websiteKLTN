<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreCatalogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer', 'min:0'],
            'sort_order' => ['nullable', 'integer', 'between:0,127'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Tên danh mục không được để trống.',
            'name.max' => 'Tên danh mục tối đa 100 ký tự.',
            'description.max' => 'Mô tả tối đa 255 ký tự.',
            'sort_order.between' => 'Thứ tự phải từ 0 đến 127.',
        ];
    }
}
