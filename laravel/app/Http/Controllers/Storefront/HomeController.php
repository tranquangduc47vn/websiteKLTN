<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Models\Product;
use App\Models\Slider;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * B4 — Storefront Home.
 *
 * Port từ CI3 application/controllers/Home.php::index():
 *   slider       — order sort_order DESC
 *   list_banner  — order sort_order ASC
 *   new_product  — product_storefront_input(order id DESC, limit 6)
 *   hot_product  — product_storefront_input(where buyed > 0, order buyed DESC, limit 6)
 *   view_product — product_storefront_input(order view DESC, limit 6)
 *
 * product_storefront_input() (application/helpers/common_helper.php) = status = 1.
 */
class HomeController extends Controller
{
    /** Trang chủ (layout main: header + slider + nội dung + footer). */
    public function index(Request $request): View
    {
        return view("site.home.index", [
            "sf_temp" => "site/home/index",
            "page_title" => null,
            "slider" => Slider::query()->orderByDesc("sort_order")->get(),
            "list_banner" => Banner::query()->orderBy("sort_order")->get(),
            "new_product" => $this->storefrontList("id"),
            "hot_product" => Product::query()->active()->where("sold_count", ">", 0)
                ->orderByDesc("sold_count")->limit(6)->get(),
            "view_product" => $this->storefrontList("view_count"),
        ]);
    }

    /**
     * CI3: product_storefront_input() — chỉ lấy sản phẩm đang bán (status = 1).
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Product>
     */
    protected function storefrontList(string $orderColumn): \Illuminate\Database\Eloquent\Collection
    {
        $allowed = ["id" => true, "view_count" => true, "sold_count" => true, "list_price" => true, "name" => true, "view" => "view_count", "buyed" => "sold_count", "price" => "list_price"];

        if (isset($allowed[$orderColumn]) && is_string($allowed[$orderColumn])) {
            $orderColumn = $allowed[$orderColumn];
        } elseif (! isset($allowed[$orderColumn])) {
            $orderColumn = "id";
        }

        return Product::query()
            ->active()
            ->orderByDesc($orderColumn)
            ->limit(6)
            ->get();
    }
}
