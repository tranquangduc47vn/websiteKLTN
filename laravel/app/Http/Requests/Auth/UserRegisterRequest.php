<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use App\Rules\StrongPassword;
use App\Rules\ValidUserPhone;
use App\Rules\ValidVnAddress;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * BƯỚC 3 — POST /dang-ky
 *
 * Dịch từ CI3 `User::register()`:
 *   name          required
 *   email         required|valid_email|callback_check_email   (check_email: email đã tồn tại)
 *   password      required|callback_validate_password_strength
 *   re_password   matches[password]
 *   province_id   required|trim|callback_validate_vn_address
 *   district_id   required|trim
 *   ward_id       required|trim
 *   address_note  required|trim|max_length[255]
 *   phone         required|trim|callback_validate_user_phone
 */
class UserRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:50', Rule::unique(User::class, 'email')],
            'password' => ['required', 'string', new StrongPassword()],
            're_password' => ['required', 'string', 'same:password'],
            'province_id' => ['required', 'string', 'max:20', new ValidVnAddress()],
            'district_id' => ['required', 'string', 'max:20'],
            'ward_id' => ['required', 'string', 'max:20'],
            'address_note' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20', new ValidUserPhone()],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Họ tên không được để trống.',
            'email.required' => 'Email đăng nhập không được để trống.',
            'email.email' => 'Email đăng nhập không hợp lệ.',
            'email.unique' => 'Tên đăng nhập đã tồn tại.',
            'password.required' => 'Mật khẩu không được để trống.',
            're_password.same' => 'Mật khẩu nhập lại không khớp.',
            'province_id.required' => 'Tỉnh / Thành phố không được để trống.',
            'district_id.required' => 'Quận / Huyện không được để trống.',
            'ward_id.required' => 'Phường / Xã không được để trống.',
            'address_note.required' => 'Ghi chú địa chỉ không được để trống.',
            'phone.required' => 'Điện thoại không được để trống.',
        ];
    }
}
