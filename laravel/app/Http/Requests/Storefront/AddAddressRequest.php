<?php

namespace App\Http\Requests\Storefront;

use App\Rules\ValidVnAddress;

class AddAddressRequest extends AccountFormRequest
{
    public function rules(): array
    {
        return [
            'province_id' => ['required', 'string', 'max:20', new ValidVnAddress()],
            'district_id' => ['required', 'string', 'max:20'],
            'ward_id' => ['required', 'string', 'max:20'],
            'address_note' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'province_id.required' => 'Tỉnh / Thành phố không được để trống.',
            'district_id.required' => 'Quận / Huyện không được để trống.',
            'ward_id.required' => 'Phường / Xã không được để trống.',
            'address_note.required' => 'Ghi chú địa chỉ không được để trống.',
            'address_note.max' => 'Ghi chú địa chỉ không được vượt quá 255 ký tự.',
        ];
    }

    protected function redirectHash(): string
    {
        return '#addresses';
    }
}
