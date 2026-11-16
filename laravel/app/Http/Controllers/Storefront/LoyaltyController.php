<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Support\Storefront\Loyalty;
use Illuminate\View\View;

class LoyaltyController extends Controller
{
    public function index(): View
    {
        return view('site.loyalty.index', [
            'page_title' => 'Chính sách tích điểm & hạng thành viên',
            'tiers' => [
                [Loyalty::tierLabel('member'), 'Mặc định khi đăng ký'],
                [Loyalty::tierLabel('silver'), '≥ 3 đơn hoàn thành hoặc tổng chi tiêu ≥ 5.000.000 ₫'],
                [Loyalty::tierLabel('gold'), '≥ 10 đơn hoàn thành hoặc tổng chi tiêu ≥ 20.000.000 ₫'],
                [Loyalty::tierLabel('diamond'), '≥ 25 đơn hoàn thành hoặc tổng chi tiêu ≥ 50.000.000 ₫'],
            ],
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
