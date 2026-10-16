<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\ContentPage;
use App\Models\Slider;
use App\Support\Storefront\Shop;
use Illuminate\View\View;

/**
 * Trang giới thiệu. CI3 Gioithieu::index đọc content_pages slug gioi-thieu.
 */
class GioithieuController extends Controller
{
    public function index(): View
    {
        $page = ContentPage::query()
            ->where('type', ContentPage::TYPE_PAGE)
            ->where('slug', 'gioi-thieu')
            ->first();

        return view('site.pages.gioithieu', [
            'page_title' => $page?->title ?: ('Giới thiệu về ' . Shop::name()),
            'page_content' => $page?->content ?: 'Nội dung giới thiệu đang được cập nhật...',
            'other_news' => ContentPage::query()
                ->where('type', ContentPage::TYPE_NEWS)
                ->orderByDesc('id')
                ->limit(6)
                ->get(),
            'slider' => Slider::query()->orderByDesc('sort_order')->get(),
        ]);
    }
}
