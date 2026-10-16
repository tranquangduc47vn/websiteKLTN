<?php

namespace App\Http\Requests\Admin;

class UpdateProductRequest extends StoreProductRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        $rules['image'] = ['nullable', 'file', 'max:5120'];
        $rules['status'] = ['nullable', 'in:0,1'];
        $rules['product_image_main'] = ['nullable', 'string', 'max:100'];
        $rules['product_image_list'] = ['nullable', 'string'];
        $rules['product_images_remove'] = ['nullable', 'array'];
        $rules['variant'] = ['nullable', 'array'];

        return $rules;
    }
}
