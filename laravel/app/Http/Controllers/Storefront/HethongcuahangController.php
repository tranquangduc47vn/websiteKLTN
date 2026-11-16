<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Slider;
use App\Models\Store;
use Illuminate\View\View;

class HethongcuahangController extends Controller
{
    public function index(): View
    {
        return view('site.stores.index', [
            'page_title' => 'Hệ thống cửa hàng - ' . config('shop.name', 'qD Design'),
            'list_stores' => Store::query()->orderBy('id')->get(),
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
