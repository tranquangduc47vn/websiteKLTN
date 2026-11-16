<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ManagesContentPages;
use App\Http\Controllers\Controller;
use App\Models\ContentPage;

class VanchuyenController extends Controller
{
    use ManagesContentPages;

    protected function contentType(): string
    {
        return ContentPage::TYPE_VAN_CHUYEN;
    }

    protected function contentBase(): string
    {
        return '/admin/vanchuyen';
    }

    protected function contentHeading(): string
    {
        return 'Vận chuyển';
    }
}
