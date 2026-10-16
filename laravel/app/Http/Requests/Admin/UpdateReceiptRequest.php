<?php

namespace App\Http\Requests\Admin;

use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateReceiptRequest extends StoreReceiptRequest
{
    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(
            redirect()->to(url('admin/stock-receipts'))->with('message_fail', 'Bạn không có quyền sửa phiếu nhập kho.')
        );
    }
}
