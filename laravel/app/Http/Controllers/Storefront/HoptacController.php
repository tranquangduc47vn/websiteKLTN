<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Slider;
use App\Support\Storefront\ContentPages;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class HoptacController extends Controller
{
    public function index(): View
    {
        return view('site.content.list', [
            'page_title' => 'Hợp tác kinh doanh - ' . config('shop.name', 'qD Design'),
            'heading' => 'Hợp tác kinh doanh - ' . config('shop.name', 'qD Design'),
            'list' => ContentPages::list(ContentPages::TYPE_HOP_TAC),
            'detail_prefix' => 'hoptac/view',
            'slider' => Slider::query()->ordered()->get(),
        ]);
    }

    public function show(string $slug): View|RedirectResponse
    {
        $page = ContentPages::bySlug(ContentPages::TYPE_HOP_TAC, $slug);
        if ($page === null) {
            return redirect()->to(url('/'));
        }

        return view('site.content.detail', [
            'page_title' => $page->title,
            'page' => $page,
            'slider' => Slider::query()->ordered()->get(),
        ]);
    }

    public function handlePath(string $path = ''): View|RedirectResponse
    {
        $segments = explode('/', trim($path, '/'));
        if (($segments[0] ?? '') === 'view' && isset($segments[1]) && $segments[1] !== '') {
            return $this->show(rawurldecode($segments[1]));
        }

        abort(404);
    }

    public function __call(string $method, array $parameters): never
    {
        abort(404);
    }
}
