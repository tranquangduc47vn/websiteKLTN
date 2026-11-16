<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Slider;
use App\Support\Storefront\ContentPages;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class TuyendungController extends Controller
{
    public function index(): View
    {
        return view('site.content.list', [
            'page_title' => 'Cơ hội nghề nghiệp - ' . config('shop.name', 'qD Design'),
            'heading' => 'Cơ hội nghề nghiệp - ' . config('shop.name', 'qD Design'),
            'list' => ContentPages::list(ContentPages::TYPE_TUYENDUNG),
            'detail_prefix' => 'tuyendung/view',
            'slider' => Slider::query()->ordered()->get(),
        ]);
    }

    public function view(string $slug): View|RedirectResponse
    {
        return $this->show($slug);
    }

    public function show(string $slug): View|RedirectResponse
    {
        $page = ContentPages::bySlug(ContentPages::TYPE_TUYENDUNG, $slug);
        if ($page === null) {
            return redirect()->to(url('/'));
        }

        return view('site.content.detail', [
            'page_title' => $page->title,
            'page' => $page,
            'slider' => Slider::query()->ordered()->get(),
        ]);
    }

    public function handlePath(): never
    {
        abort(404);
    }

    public function __call(string $method, array $parameters): never
    {
        abort(404);
    }
}
