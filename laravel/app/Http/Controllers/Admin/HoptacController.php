<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ManagesContentPages;
use App\Http\Controllers\Controller;
use App\Models\ContentPage;

class HoptacController extends Controller
{
    use ManagesContentPages;

    protected function contentType(): string
    {
        return ContentPage::TYPE_HOP_TAC;
    }

    protected function contentBase(): string
    {
        return '/admin/hoptac';
    }

    protected function contentHeading(): string
    {
        return 'Hợp tác kinh doanh';
    }
}
