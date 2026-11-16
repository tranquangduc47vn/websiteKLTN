<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ManagesContentPages;
use App\Http\Controllers\Controller;
use App\Models\ContentPage;

class TuyendungController extends Controller
{
    use ManagesContentPages;

    protected function contentType(): string
    {
        return ContentPage::TYPE_TUYENDUNG;
    }

    protected function contentBase(): string
    {
        return '/admin/tuyendung';
    }

    protected function contentHeading(): string
    {
        return 'Tuyển dụng';
    }
}
