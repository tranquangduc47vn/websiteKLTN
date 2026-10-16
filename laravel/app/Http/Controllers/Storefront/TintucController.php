<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\ContentPage;
use Illuminate\View\View;

/**
 * /tintuc — CI3 Tintuc::index, liệt kê mọi bài, layout có slider.
 */
class TintucController extends Controller
{
    public function index(): View
    {
        return view('site.news.tintuc', [
            'sf_temp' => 'site/tintuc_index',
            'page_title' => 'Tin tức & Sự kiện - ' . \App\Support\Storefront\Shop::name(),
            'posts' => ContentPage::query()->where('type', ContentPage::TYPE_NEWS)->orderByDesc('id')->get(),
        ]);
    }
}
