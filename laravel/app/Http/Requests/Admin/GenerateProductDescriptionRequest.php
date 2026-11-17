<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class GenerateProductDescriptionRequest extends FormRequest
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
        $maxGallery = max(1, (int) config('ai.product_description.max_images', 4));

        return [
            'product_id' => ['nullable', 'integer', 'min:1'],
            'name' => ['nullable', 'string', 'max:255'],
            'catalog_id' => ['nullable', 'integer', 'min:1'],
            'price' => ['nullable', 'string', 'max:32'],
            'discount' => ['nullable', 'string', 'max:8'],
            'color' => ['nullable', 'array', 'max:32'],
            'color.*' => ['string', 'max:64'],
            'size' => ['nullable', 'array', 'max:32'],
            'size.*' => ['string', 'max:32'],
            'product_image_main' => ['nullable', 'string', 'max:255'],
            'product_image_list' => ['nullable', 'string', 'max:4000'],
            'image' => ['nullable', 'file', 'image', 'max:5120'],
            'list_image' => ['nullable', 'array', 'max:' . $maxGallery],
            'list_image.*' => ['file', 'image', 'max:5120'],
        ];
    }
}