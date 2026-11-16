<?php

namespace App\View\Composers;

use App\Support\Storefront\CatalogTree;
use App\Support\Storefront\Flash;
use App\Support\Storefront\Urls;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * B4 - View Composer cho toàn bộ layout storefront.
 *
 * Port từ CI3 MY_Controller::__construct() (application/core/MY_Controller.php):
 *   catalog          — danh mục cấp 1 + con trực tiếp (parent_id=1, sort_order ASC)
 *   user             — Auth::guard('web'); khách khi guard trống
 *   user_order_badge — số đơn đang xử lý của user
 *   carts / total_items — đếm số lượng trong session 'custom_cart' (CHỈ ĐỌC)
 *
 * KHÔNG ghi DB. Phần khôi phục / xóa giỏ theo trạng thái đăng nhập của MY_Controller
 * nằm ở middleware App\Http\Middleware\SyncCartSession.
 * Giỏ hàng vẫn đọc session 'custom_cart'. Không đọc session 'user' để xác định đăng nhập.
 */
class StorefrontComposer
{
    /** View nào được gắn composer. */
    public static array $views = [
        'layouts.storefront.*',
        'site.*',
    ];

    public function compose(View $view): void
    {
        $request = request();

        // Composer chạy cho từng view/partial của trang: chỉ query một lần mỗi request.
        if (! $request->attributes->has('storefront.shared')) {
            $request->attributes->set('storefront.shared', $this->sharedData());
        }

        $view->with($request->attributes->get('storefront.shared'));
    }

    /**
     * @return array<string, mixed>
     */
    private function sharedData(): array
    {
        $user = $this->currentUser();
        $cartCount = $this->cartItemCount();

        return [
            'catalog' => CatalogTree::menu(),
            'user' => $user,
            'user_order_badge' => $this->orderBadge($user),
            'carts' => $this->cartSummary($cartCount),
            'total_items' => $cartCount,
            'shop_name' => config('shop.name', 'qD Design'),
            'asset' => fn (string $p = '') => url('assets/site/' . ltrim($p, '/')),
            'flash' => Flash::all(),
            'cart_url' => Urls::cart(),
            'login_url' => Urls::login(),
            'register_url' => Urls::register(),
            'robots_meta' => $this->privatePath(request()) ? 'noindex, nofollow' : null,
        ];
    }

    private function privatePath(\Illuminate\Http\Request $request): bool
    {
        $path = trim($request->path(), '/');
        foreach (['gio-hang', 'thanh-toan', 'dang-nhap', 'dang-ky', 'quen-mat-khau', 'dat-lai-mat-khau', 'user', 'admin'] as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return true;
            }
        }

        return false;
    }

    /** User hiện tại chỉ lấy từ guard web. Session 'user' không cấp quyền. */
    private function currentUser(): mixed
    {
        return Auth::guard('web')->user();
    }

    /**
     * Huy hiệu đơn đang xử lý. Chỉ đếm đơn gắn đúng user_id.
     * Không đếm theo email form, kể cả đơn lịch sử user_id NULL.
     */
    private function orderBadge(mixed $user): int
    {
        $uid = (int) ($user->id ?? 0);
        if ($uid <= 0) {
            return 0;
        }

        return (int) DB::table('orders')
            ->where('user_id', $uid)
            ->whereIn('status', ['pending', 'confirmed', 'packed'])
            ->count();
    }

    /**
     * Đếm số lượng item trong session 'custom_cart' — CI3: sum(qty).
     * AGENTS.md: dữ liệu giỏ hàng nằm ở session key 'custom_cart'.
     */
    private function cartItemCount(): int
    {
        $cart = session()->get('custom_cart');

        if (! is_array($cart)) {
            return 0;
        }

        $total = 0;

        foreach ($cart as $item) {
            if (is_array($item)) {
                $total += (int) ($item['qty'] ?? 0);
            } elseif (is_object($item)) {
                $total += (int) ($item->qty ?? 0);
            }
        }

        return $total;
    }

    /** Giữ biến $carts kiểu mảng như CI3 (view header/footer chỉ dùng count). */
    private function cartSummary(int $count): array
    {
        $cart = session()->get('custom_cart');

        return is_array($cart) ? array_slice($cart, 0, $count > 0 ? count($cart) : 0) : [];
    }
}
