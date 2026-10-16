<?php

namespace App\Http\Requests\Storefront;

use App\Support\Storefront\Flash;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;

abstract class AccountFormRequest extends FormRequest
{
    abstract protected function redirectHash(): string;

    public function authorize(): bool
    {
        return Auth::guard('web')->check();
    }

    protected function failedValidation(Validator $validator): void
    {
        Flash::auth(Flash::FAIL, trim(strip_tags(implode(' ', $validator->errors()->all()))));

        throw new HttpResponseException(redirect()->to(url('user') . $this->redirectHash()));
    }
}
