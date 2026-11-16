<?php

use App\Http\Controllers\Payment\SepayWebhookController;
use App\Http\Controllers\Storefront\AiAssistantController;
use App\Http\Controllers\Storefront\CartController;
use App\Http\Controllers\Storefront\GioithieuController;
use App\Http\Controllers\Storefront\HethongcuahangController;
use App\Http\Controllers\Storefront\HomeController;
use App\Http\Controllers\Storefront\HoptacController;
use App\Http\Controllers\Storefront\LegacyController;
use App\Http\Controllers\Storefront\LoyaltyController;
use App\Http\Controllers\Storefront\NewsController;
use App\Http\Controllers\Storefront\OrderController;
use App\Http\Controllers\Storefront\ProductController;
use App\Http\Controllers\Storefront\SeoController;
use App\Http\Controllers\Storefront\TintucController;
use App\Http\Controllers\Storefront\TuyendungController;
use App\Http\Controllers\Storefront\UserController;
use App\Http\Controllers\Storefront\VanchuyenController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| BƯỚC 2 — MIGRATE ROUTES: CodeIgniter 3 -> Laravel 12
|--------------------------------------------------------------------------
|
| Nguồn: application/config/routes.php (CI3) — 132 route key.
| Đối chiếu: 132/132 URL cũ đã được map (xem báo cáo cuối buổi).
|
| NGUYÊN TẮC BẮT BUỘC:
|  1. Giữ NGUYÊN URL c��, kể cả phân biệt hoa/thường:
|     `hethongcuahang`, `VanChuyen`, `vanchuyen`, `TichDiem`, `tich-diem`,
|     `ThongTinTuyenDung`, `LienHeHopTacKinhDoanh`.
|  2. THỨ TỰ ROUTE CÓ Ý NGHĨA. CI3 so khớp tuần tự, Laravel CŨNG vậy.
|     Mọi catch-all (`{slug}`, `{path}`, `{slug}-p{id}`, `{slug}-c{id}`)
|     PHẢI nằm CUỐI file, sau TẤT CẢ route tĩnh.
|  3. Route chỉ khai báo: URL + HTTP method + Controller@action + middleware.
|     KHÔNG chứa business logic.
|  4. Controller Laravel chưa implement (B3+). Xem TODO cuối file.
|  5. Tên route: `storefront.*` và `admin.*`.
|
| HTTP METHOD:
|  CI3 không phân biệt GET/POST ở tầng routing — cùng URL nhận được cả hai,
|  tuỳ method kiểm tra trong Controller (ví dụ `gio-hang/update` nhận POST từ
|  form, nhưng `gio-hang/(.+)` vẫn là route GET để bắt path lạ).
|  Vì vậy B2 dùng `match(['get','post'])` cho các URL đó để BẢO TOÀN behavior.
|  Khi refactor Controller (B5+) sẽ siết lại đúng method — kèm cập nhật Blade/JS.
|
| PHÂN LOẠI:
|  - STOREFRONT: khối 1..10 bên dưới
|  - ADMIN:      route group `prefix('admin')` + middleware `admin.auth`
|  - AJAX:       nằm trong web.php (GIỮ NGUYÊN session + CSRF như CI3),
|                KHÔNG tách sang routes/api.php — không cần, và tách ra sẽ
|                làm mất session/CSRF mà frontend hiện tại đang dùng.
|
*/

/*
|--------------------------------------------------------------------------
| 1. STOREFRONT — Trang chủ
|--------------------------------------------------------------------------
| CI3: $route['default_controller'] = 'home';
*/
Route::get('/', [HomeController::class, 'index'])->name('storefront.home');

/*
|--------------------------------------------------------------------------
| 2. STOREFRONT — Trang tĩnh / nội dung
|--------------------------------------------------------------------------
*/
Route::get('gioi-thieu', [GioithieuController::class, 'index'])->name('storefront.gioithieu');

Route::get('hethongcuahang', [HethongcuahangController::class, 'index'])->name('storefront.hethongcuahang');
Route::match(['get', 'post'], 'hethongcuahang/{path}', [HethongcuahangController::class, 'handlePath'])
    ->where('path', '.*')
    ->name('storefront.hethongcuahang.path');

Route::get('VanChuyen', [VanchuyenController::class, 'index'])->name('storefront.vanchuyen.index');
Route::get('vanchuyen', [VanchuyenController::class, 'index'])->name('storefront.vanchuyen');
Route::match(['get', 'post'], 'vanchuyen/{path}', [VanchuyenController::class, 'handlePath'])
    ->where('path', '.*')
    ->name('storefront.vanchuyen.path');

Route::get('ThongTinTuyenDung', [TuyendungController::class, 'index'])->name('storefront.tuyendung.index');
Route::get('ThongTinTuyenDung/{slug}', [TuyendungController::class, 'view'])->name('storefront.tuyendung.detail');
Route::get('tuyendung/view/{slug}', [TuyendungController::class, 'view'])->name('storefront.tuyendung.view');

Route::get('LienHeHopTacKinhDoanh', [HoptacController::class, 'index'])->name('storefront.hoptac.index');
Route::get('hoptac/view/{slug}', [HoptacController::class, 'show'])->name('storefront.hoptac.view');
Route::get('vanchuyen/view/{slug}', [VanchuyenController::class, 'show'])->name('storefront.vanchuyen.view');

Route::get('robots.txt', [SeoController::class, 'robots'])->name('storefront.robots');
Route::get('sitemap.xml', [SeoController::class, 'sitemap'])->name('storefront.sitemap');

Route::get('tich-diem', [LoyaltyController::class, 'index'])->name('storefront.loyalty');
Route::get('TichDiem', [LoyaltyController::class, 'index'])->name('storefront.loyalty.legacy');

/*
|--------------------------------------------------------------------------
| 3. STOREFRONT — Tin tức
|--------------------------------------------------------------------------
| CI3: tin-tuc/([a-z0-9-]+)-n(:num) -> news/view/$2
|      tin-tuc/(:num)               -> news/index/$1  (offset phân trang)
*/
Route::get('tin-tuc', [NewsController::class, 'index'])->name('storefront.news.index');
Route::get('tin-tuc/{slug}-n{id}', [NewsController::class, 'view'])
    ->whereNumber('id')
    ->where('slug', '[a-z0-9-]+')
    ->name('storefront.news.view');
Route::get('tin-tuc/{offset}', [NewsController::class, 'index'])
    ->whereNumber('offset')
    ->name('storefront.news.page');

/*
|--------------------------------------------------------------------------
| 4. STOREFRONT — Giỏ hàng (session key: custom_cart — xem AGENTS.md)
|--------------------------------------------------------------------------
*/
Route::get('gio-hang', [CartController::class, 'index'])->name('storefront.cart.index');
Route::match(['get', 'post'], 'gio-hang/them/{id}', [CartController::class, 'add'])
    ->whereNumber('id')
    ->name('storefront.cart.add');
Route::match(['get', 'post'], 'gio-hang/add/{id}', [CartController::class, 'add'])
    ->whereNumber('id')
    ->name('storefront.cart.add.legacy');
Route::match(['get', 'post'], 'gio-hang/cap-nhat-thuoc-tinh', [CartController::class, 'updateOptions'])
    ->name('storefront.cart.update_options');
Route::match(['get', 'post'], 'gio-hang/update', [CartController::class, 'update'])
    ->name('storefront.cart.update');
Route::match(['get', 'post'], 'gio-hang/del', [CartController::class, 'del'])
    ->name('storefront.cart.del');
Route::match(['get', 'post'], 'gio-hang/update/{rowid}', [CartController::class, 'update'])
    ->name('storefront.cart.update.rowid');
Route::match(['get', 'post'], 'gio-hang/del/{rowid}', [CartController::class, 'del'])
    ->name('storefront.cart.del.rowid');
Route::match(['get', 'post'], 'gio-hang/{path}', [CartController::class, 'handlePath'])
    ->where('path', '.*')
    ->name('storefront.cart.path');

/*
|--------------------------------------------------------------------------
| 5. STOREFRONT — Đặt hàng / Thanh toán
|--------------------------------------------------------------------------
*/
Route::match(['get', 'post'], 'thanh-toan', [OrderController::class, 'index'])->name('storefront.order.index');
Route::match(['get', 'post'], 'thanh-toan/apply_voucher', [OrderController::class, 'applyVoucher'])
    ->name('storefront.order.apply_voucher');
Route::match(['get', 'post'], 'thanh-toan/checkout_qr/{id}', [OrderController::class, 'checkoutQr'])
    ->whereNumber('id')
    ->name('storefront.order.checkout_qr');
Route::get('thanh-toan/payment-status/{id}', [OrderController::class, 'paymentStatus'])
    ->whereNumber('id')
    ->name('storefront.order.payment_status');
Route::post('payment/webhook/sepay', SepayWebhookController::class)
    ->name('payment.webhook.sepay');
Route::match(['get', 'post'], 'thanh-toan/cancel/{id}', [OrderController::class, 'cancel'])
    ->whereNumber('id')
    ->name('storefront.order.cancel');
Route::match(['get', 'post'], 'thanh-toan/{path}', [OrderController::class, 'handlePath'])
    ->where('path', '.*')
    ->name('storefront.order.path');

/*
|--------------------------------------------------------------------------
| 6. STOREFRONT — Tìm kiếm
|--------------------------------------------------------------------------
*/
Route::get('tim-kiem', [ProductController::class, 'searchSeo'])->name('storefront.search');
Route::get('tim-kiem/{keyword}', [ProductController::class, 'searchSeo'])->name('storefront.search.keyword');
Route::get('tim-kiem/{keyword}/{offset}', [ProductController::class, 'searchSeo'])
    ->whereNumber('offset')
    ->name('storefront.search.page');

/*
|--------------------------------------------------------------------------
| 7. STOREFRONT — Danh sách nổi bật
|--------------------------------------------------------------------------
*/
Route::get('ban-chay', [ProductController::class, 'hot'])->name('storefront.product.hot');
Route::get('ban-chay/{offset}', [ProductController::class, 'hot'])
    ->whereNumber('offset')
    ->name('storefront.product.hot.page');

Route::get('moi', [ProductController::class, 'news'])->name('storefront.product.new');
Route::get('moi/{offset}', [ProductController::class, 'news'])
    ->whereNumber('offset')
    ->name('storefront.product.new.page');

Route::get('khuyen-mai', [ProductController::class, 'discount'])->name('storefront.product.discount');
Route::get('khuyen-mai/{offset}', [ProductController::class, 'discount'])
    ->whereNumber('offset')
    ->name('storefront.product.discount.page');

/*
|--------------------------------------------------------------------------
| 8. STOREFRONT — Reserved product paths
|--------------------------------------------------------------------------
| PHẢI đứng TRƯỚC `{slug}` và `{slug}-p{id}` vì CI3 khai báo các path này
| sau product detail nhưng trước catch-all category.
*/
Route::match(['get', 'post'], 'product/index', [ProductController::class, 'index'])
    ->name('storefront.product.index');
Route::get('product/views', [ProductController::class, 'views'])->name('storefront.product.views');
Route::get('product/news', [ProductController::class, 'news'])->name('storefront.product.news');
Route::post('product/raty', [ProductController::class, 'raty'])->name('storefront.product.raty');
Route::post('product/raty-undo', [ProductController::class, 'ratyUndo'])->name('storefront.product.raty_undo');

/*
|--------------------------------------------------------------------------
| 9. STOREFRONT — Tài khoản
|--------------------------------------------------------------------------
| Auth SEO: /dang-nhap /dang-ky /quen-mat-khau /dat-lai-mat-khau — public.
| Account /user* : middleware customer.auth (CI3 `_require_login()` → /dang-nhap).
| POST /user/logout và handlePath (user/login, user/register...) đứng ngoài middleware.
| GET /user/logout không đăng xuất.
*/
Route::get('dang-nhap', [UserController::class, 'login'])->name('storefront.user.login');
Route::post('dang-nhap', [UserController::class, 'login'])->name('storefront.user.login.post');
Route::get('dang-ky', [UserController::class, 'register'])->name('storefront.user.register');
Route::post('dang-ky', [UserController::class, 'register'])->middleware('throttle:auth-public')->name('storefront.user.register.post');
Route::get('quen-mat-khau', [UserController::class, 'forgotPassword'])->name('storefront.user.forgot_password');
Route::post('quen-mat-khau', [UserController::class, 'forgotPassword'])->middleware('throttle:auth-public')->name('storefront.user.forgot_password.post');
Route::get('dat-lai-mat-khau', [UserController::class, 'resetPassword'])->name('storefront.user.reset_password');
Route::get('dat-lai-mat-khau/{token}', [UserController::class, 'resetPassword'])->name('storefront.user.reset_password.token');
Route::post('dat-lai-mat-khau', [UserController::class, 'resetPassword'])->middleware('throttle:auth-public')->name('storefront.user.reset_password.post');

Route::post('user/logout', [UserController::class, 'logout'])->name('storefront.user.logout');

Route::middleware('customer.auth')->group(function () {
    Route::get('user', [UserController::class, 'index'])->name('storefront.user.index');
    Route::match(['get', 'post'], 'user/check_email', [UserController::class, 'checkEmail'])->name('storefront.user.check_email');
    Route::match(['get', 'post'], 'user/validate_vn_address', [UserController::class, 'validateVnAddress'])->name('storefront.user.validate_vn_address');
    Route::match(['get', 'post'], 'user/validate_user_phone', [UserController::class, 'validateUserPhone'])->name('storefront.user.validate_user_phone');
    Route::post('user/password_change', [UserController::class, 'passwordChange'])->name('storefront.user.password_change');
    Route::post('user/profile_update', [UserController::class, 'profileUpdate'])->name('storefront.user.profile_update');
    Route::post('user/phone_add', [UserController::class, 'phoneAdd'])->name('storefront.user.phone_add');
    Route::match(['get', 'post'], 'user/phone_delete/{id}', [UserController::class, 'phoneDelete'])
        ->whereNumber('id')->name('storefront.user.phone_delete');
    Route::match(['get', 'post'], 'user/phone_default/{id}', [UserController::class, 'phoneDefault'])
        ->whereNumber('id')->name('storefront.user.phone_default');
    Route::post('user/address_add', [UserController::class, 'addressAdd'])->name('storefront.user.address_add');
    Route::match(['get', 'post'], 'user/address_delete/{id}', [UserController::class, 'addressDelete'])
        ->whereNumber('id')->name('storefront.user.address_delete');
    Route::match(['get', 'post'], 'user/address_default/{id}', [UserController::class, 'addressDefault'])
        ->whereNumber('id')->name('storefront.user.address_default');
    Route::match(['get', 'post'], 'user/order_detail/{id}', [UserController::class, 'orderDetail'])
        ->whereNumber('id')->name('storefront.user.order_detail');
    Route::match(['get', 'post'], 'user/get_info_user', [UserController::class, 'getInfoUser'])->name('storefront.user.get_info');
});

Route::match(['get', 'post'], 'user/{path}', [UserController::class, 'handlePath'])
    ->where('path', '.*')->name('storefront.user.path');

Route::match(['get', 'post'], 'tintuc', [TintucController::class, 'index'])->name('storefront.tintuc');

/*
|--------------------------------------------------------------------------
| 10. STOREFRONT — AI assistant (AJAX, fetch từ assets/site/js/ai-chat.js)
|--------------------------------------------------------------------------
| URL phải giữ nguyên: /ai-assistant/send|history|poll
*/
Route::match(['get', 'post'], 'ai-assistant/send', [AiAssistantController::class, 'send'])->middleware('throttle:ai-send')->name('storefront.ai.send');
Route::get('ai-assistant/history', [AiAssistantController::class, 'history'])->name('storefront.ai.history');
Route::get('ai-assistant/poll', [AiAssistantController::class, 'poll'])->name('storefront.ai.poll');

/*
|--------------------------------------------------------------------------
| 11. STOREFRONT — Legacy 301 (URL cũ -> URL SEO)
|--------------------------------------------------------------------------
| PHẢI đứng TRƯỚC catch-all `{slug}` vì `cart`, `news`, `order`, `posts`
| đều khớp pattern `[a-z0-9-]+`.
*/
Route::match(['get', 'post'], 'product/catalog/{catalogId}', [LegacyController::class, 'catalog'])
    ->whereNumber('catalogId')->name('storefront.legacy.catalog');
Route::match(['get', 'post'], 'product/catalog/{catalogId}/{offset}', [LegacyController::class, 'catalog'])
    ->whereNumber('catalogId')->whereNumber('offset')->name('storefront.legacy.catalog.page');
Route::match(['get', 'post'], 'product/view/{id}', [LegacyController::class, 'product'])
    ->whereNumber('id')->name('storefront.legacy.product_view');
Route::match(['get', 'post'], 'product/detail/{id}', [LegacyController::class, 'product'])
    ->whereNumber('id')->name('storefront.legacy.product_detail');
Route::match(['get', 'post'], 'product/search', [LegacyController::class, 'productSearch'])
    ->name('storefront.legacy.product_search');

Route::match(['get', 'post'], 'cart', [LegacyController::class, 'cart'])->name('storefront.legacy.cart');
Route::match(['get', 'post'], 'cart/{path}', [LegacyController::class, 'cartPath'])
    ->where('path', '.*')->name('storefront.legacy.cart_path');

Route::match(['get', 'post'], 'order', [LegacyController::class, 'order'])->name('storefront.legacy.order');
Route::match(['get', 'post'], 'order/{path}', [LegacyController::class, 'orderPath'])
    ->where('path', '.*')->name('storefront.legacy.order_path');

Route::match(['get', 'post'], 'posts', [LegacyController::class, 'posts'])->name('storefront.legacy.posts');
Route::match(['get', 'post'], 'posts/view/{id}', [LegacyController::class, 'postView'])
    ->whereNumber('id')->name('storefront.legacy.post_view');
Route::match(['get', 'post'], 'posts/{offset}', [LegacyController::class, 'postsPage'])
    ->whereNumber('offset')->name('storefront.legacy.posts_page');

Route::match(['get', 'post'], 'news', [LegacyController::class, 'posts'])->name('storefront.legacy.news');
Route::match(['get', 'post'], 'news/{path}', [LegacyController::class, 'posts'])
    ->where('path', '.*')->name('storefront.legacy.news_path');/*
|--------------------------------------------------------------------------
| 12. STOREFRONT — Product detail & danh mục SEO (CATCH-ALL)
|--------------------------------------------------------------------------
| ĐẶT TRONG KHỐI NÀY, SAU TẤT CẢ route tĩnh ở trên.
| CI3: ([a-zA-Z0-9-]+)-p(:num) -> product/view/$2
|      ([a-z0-9-]+)             -> product/catalog_seo/$1
|
| THỨ TỰ BÊN TRONG RẤT QUAN TRỌNG:
|   1. product detail  ({slug}-p{id})
|   2. legacy category ({slug}-c{id})
|   3. danh mục có filter giá (cu the)
|   4. danh mục có phân trang offset
|   5. danh mục thường ({slug} catch-all — PHẢI CUỐI CÙNG)
*/
Route::get('{slug}-p{id}', [ProductController::class, 'view'])
    ->where('slug', '[a-zA-Z0-9-]+')
    ->whereNumber('id')
    ->name('storefront.product.view');

Route::match(['get', 'post'], '{slug}-c{id}/{offset}', [LegacyController::class, 'catalogC'])
    ->where('slug', '[a-zA-Z0-9-]+')->whereNumber('id')->whereNumber('offset')
    ->name('storefront.legacy.catalog_c.page');
Route::match(['get', 'post'], '{slug}-c{id}', [LegacyController::class, 'catalogC'])
    ->where('slug', '[a-zA-Z0-9-]+')->whereNumber('id')
    ->name('storefront.legacy.catalog_c');

Route::get('{slug}/duoi-200k', [ProductController::class, 'catalogSeo'])
    ->where('slug', '[a-z0-9-]+')->defaults('price_slug', 'duoi-200k')
    ->name('storefront.catalog.seo.duoi200k');
Route::get('{slug}/duoi-200k/{offset}', [ProductController::class, 'catalogSeo'])
    ->where('slug', '[a-z0-9-]+')->whereNumber('offset')
    ->defaults('price_slug', 'duoi-200k')
    ->name('storefront.catalog.seo.duoi200k.page');

Route::get('{slug}/200k-500k', [ProductController::class, 'catalogSeo'])
    ->where('slug', '[a-z0-9-]+')->defaults('price_slug', '200k-500k')
    ->name('storefront.catalog.seo.200k500k');
Route::get('{slug}/200k-500k/{offset}', [ProductController::class, 'catalogSeo'])
    ->where('slug', '[a-z0-9-]+')->whereNumber('offset')
    ->defaults('price_slug', '200k-500k')
    ->name('storefront.catalog.seo.200k500k.page');

Route::get('{slug}/500k-1tr', [ProductController::class, 'catalogSeo'])
    ->where('slug', '[a-z0-9-]+')->defaults('price_slug', '500k-1tr')
    ->name('storefront.catalog.seo.500k1tr');
Route::get('{slug}/500k-1tr/{offset}', [ProductController::class, 'catalogSeo'])
    ->where('slug', '[a-z0-9-]+')->whereNumber('offset')
    ->defaults('price_slug', '500k-1tr')
    ->name('storefront.catalog.seo.500k1tr.page');

Route::get('{slug}/tren-1tr', [ProductController::class, 'catalogSeo'])
    ->where('slug', '[a-z0-9-]+')->defaults('price_slug', 'tren-1tr')
    ->name('storefront.catalog.seo.tren1tr');
Route::get('{slug}/tren-1tr/{offset}', [ProductController::class, 'catalogSeo'])
    ->where('slug', '[a-z0-9-]+')->whereNumber('offset')
    ->defaults('price_slug', 'tren-1tr')
    ->name('storefront.catalog.seo.tren1tr.page');

foreach (['duoi-200k', '200k-500k', '500k-1tr', 'tren-1tr'] as $priceSlug) {
    Route::get('danh-muc/{slug}/' . $priceSlug, [ProductController::class, 'catalogSeo'])
        ->where('slug', '[a-z0-9-]+')
        ->defaults('price_slug', $priceSlug);
    Route::get('danh-muc/{slug}/' . $priceSlug . '/{offset}', [ProductController::class, 'catalogSeo'])
        ->where('slug', '[a-z0-9-]+')
        ->whereNumber('offset')
        ->defaults('price_slug', $priceSlug);
}

Route::get('danh-muc/{slug}/{offset}', [ProductController::class, 'catalogSeo'])
    ->where('slug', '[a-z0-9-]+')
    ->whereNumber('offset')
    ->defaults('price_slug', '')
    ->name('storefront.catalog.namespaced.page');
Route::get('danh-muc/{slug}', [ProductController::class, 'catalogSeo'])
    ->where('slug', '[a-z0-9-]+')
    ->defaults('price_slug', '')
    ->name('storefront.catalog.namespaced');

Route::get('{slug}/{offset}', [ProductController::class, 'catalogSeo'])
    ->where('slug', '(?!admin$)[a-z0-9-]+')
    ->whereNumber('offset')
    ->defaults('price_slug', '')
    ->name('storefront.catalog.seo.page');

Route::get('{slug}/_/{offset}', [ProductController::class, 'catalogSeo'])
    ->where('slug', '[a-z0-9-]+')->whereNumber('offset')->defaults('price_slug', '')
    ->name('storefront.catalog.seo.offset');

Route::get('{slug}', [ProductController::class, 'catalogSeo'])
    ->where('slug', '(?!admin$)[a-z0-9-]+')
    ->name('storefront.catalog.seo');

/*
|--------------------------------------------------------------------------
| ADMIN
|--------------------------------------------------------------------------
| Laravel: middleware `admin.auth` dùng Auth::guard('admin').
|
| Route group admin.* KHÔNG áp middleware cho `admin/login` (trang đăng nhập).
| Phân quyền chi tiết (Admin=0 / Mod=1 / User=2) sẽ làm ở B3 bằng Gate/Policy —
| lấy từ application/helpers/permission_helper.php.
*/
Route::prefix('admin')->name('admin.')->group(function () {

    // Đăng nhập panel
    Route::get('login', [\App\Http\Controllers\Admin\LoginController::class, 'index'])->name('login');
    Route::post('login', [\App\Http\Controllers\Admin\LoginController::class, 'checkLogin'])->name('login.post');
    Route::get('logout', [\App\Http\Controllers\Admin\AdminController::class, 'logout']);
    Route::post('logout', [\App\Http\Controllers\Admin\AdminController::class, 'logout'])->name('logout');

    // admin.auth = Auth::guard('admin'). Session key `login` không cấp quyền.
    Route::middleware(['admin.auth'])->group(function () {

        // Dashboard — CI3: /admin và /admin/home đều vào Home::index
        Route::get('/', [\App\Http\Controllers\Admin\HomeController::class, 'index'])->name('home');
        Route::get('home', [\App\Http\Controllers\Admin\HomeController::class, 'index'])->name('home.alias');
        Route::match(['get', 'post'], 'home/{path}', [\App\Http\Controllers\Admin\HomeController::class, 'handlePath'])
            ->where('path', '.*')->name('home.path');

        // Alias plural -> controller cũ (giữ nguyên như CI3)
        Route::middleware('admin.permission:content.manage')->group(function () {
            Route::match(['get', 'post'], 'branding', [\App\Http\Controllers\Admin\SiteBrandingController::class, 'index'])->name('branding.index');
            Route::match(['get', 'post'], 'branding/{path}', [\App\Http\Controllers\Admin\SiteBrandingController::class, 'handlePath'])
                ->where('path', '.*')->name('branding.path');
        });

        Route::middleware('admin.permission:product.manage')->group(function () {
            Route::get('products', [\App\Http\Controllers\Admin\ProductController::class, 'index'])->name('products.index');
            Route::match(['get', 'post'], 'products/{path}', [\App\Http\Controllers\Admin\ProductController::class, 'handlePath'])
                ->where('path', '.*')->name('products.path');

            Route::get('product-reviews', [\App\Http\Controllers\Admin\ProductReviewController::class, 'index'])->name('product-reviews.index');
            Route::match(['get', 'post'], 'product-reviews/{path}', [\App\Http\Controllers\Admin\ProductReviewController::class, 'handlePath'])
                ->where('path', '.*')->name('product-reviews.path');
        });

        // Tồn kho
        Route::get('inventory', [\App\Http\Controllers\Admin\InventoryController::class, 'index'])->name('inventory.index');
        Route::get('inventory/low-stock', [\App\Http\Controllers\Admin\InventoryController::class, 'lowStock'])->name('inventory.low-stock');
        Route::post('inventory/adjust/{id}', [\App\Http\Controllers\Admin\InventoryController::class, 'adjust'])
            ->whereNumber('id')->name('inventory.adjust');
        Route::match(['get', 'post'], 'inventory/{path}', [\App\Http\Controllers\Admin\InventoryController::class, 'handlePath'])
            ->where('path', '.*')->name('inventory.path');

        // Phiếu nhập kho
        Route::get('receipts', [\App\Http\Controllers\Admin\StockReceiptController::class, 'index'])->name('receipts.index');
        Route::get('receipts/create', [\App\Http\Controllers\Admin\StockReceiptController::class, 'add'])->name('receipts.create');
        Route::get('receipts/view/{id}', [\App\Http\Controllers\Admin\StockReceiptController::class, 'view'])
            ->whereNumber('id')->name('receipts.view');
        Route::match(['get', 'post'], 'receipts/confirm/{id}', [\App\Http\Controllers\Admin\StockReceiptController::class, 'confirm'])
            ->whereNumber('id')->name('receipts.confirm');
        Route::match(['get', 'post'], 'receipts/cancel/{id}', [\App\Http\Controllers\Admin\StockReceiptController::class, 'cancel'])
            ->whereNumber('id')->name('receipts.cancel');
        Route::match(['get', 'post'], 'receipts/{path}', [\App\Http\Controllers\Admin\StockReceiptController::class, 'handlePath'])
            ->where('path', '.*')->name('receipts.path');

        // Biến động kho
        Route::get('stock-movements', [\App\Http\Controllers\Admin\StockMovementsController::class, 'index'])->name('stock-movements.index');
        Route::match(['get', 'post'], 'stock-movements/{path}', [\App\Http\Controllers\Admin\StockMovementsController::class, 'handlePath'])
            ->where('path', '.*')->name('stock-movements.path');

        // Nhà cung cấp
        Route::get('suppliers', [\App\Http\Controllers\Admin\SuppliersController::class, 'index'])->name('suppliers.index');
        Route::post('suppliers/add', [\App\Http\Controllers\Admin\SuppliersController::class, 'store'])->name('suppliers.store');
        Route::match(['get', 'post'], 'suppliers/{path}', [\App\Http\Controllers\Admin\SuppliersController::class, 'handlePath'])
            ->where('path', '.*')->name('suppliers.path');

        // Alias: stock-receipts / stock-inventory (song song receipts / inventory)
        Route::get('stock-receipts', [\App\Http\Controllers\Admin\StockReceiptController::class, 'index'])->name('stock-receipts.index');
        Route::post('stock-receipts/add', [\App\Http\Controllers\Admin\StockReceiptController::class, 'store'])->name('stock-receipts.store');
        Route::post('stock-receipts/edit/{id}', [\App\Http\Controllers\Admin\StockReceiptController::class, 'update'])
            ->whereNumber('id')->name('stock-receipts.update');
        Route::post('stock-receipts/confirm', [\App\Http\Controllers\Admin\StockReceiptController::class, 'confirm'])->name('stock-receipts.confirm');
        Route::post('stock-receipts/cancel', [\App\Http\Controllers\Admin\StockReceiptController::class, 'cancel'])->name('stock-receipts.cancel');
        Route::match(['get', 'post'], 'stock-receipts/{path}', [\App\Http\Controllers\Admin\StockReceiptController::class, 'handlePath'])
            ->where('path', '.*')->name('stock-receipts.path');

        Route::get('stock-inventory', [\App\Http\Controllers\Admin\InventoryController::class, 'index'])->name('stock-inventory.index');
        Route::get('stock-inventory/low-stock', [\App\Http\Controllers\Admin\InventoryController::class, 'lowStock'])->name('stock-inventory.low-stock');
        Route::match(['get', 'post'], 'stock-inventory/{path}', [\App\Http\Controllers\Admin\InventoryController::class, 'handlePath'])
            ->where('path', '.*')->name('stock-inventory.path');

        // Khách hàng
        Route::get('users', [\App\Http\Controllers\Admin\UserController::class, 'index'])->name('users.index');
        Route::match(['get', 'post'], 'users/{path}', [\App\Http\Controllers\Admin\UserController::class, 'handlePath'])
            ->where('path', '.*')->name('users.path');

        // Đơn hàng (controller cũ: transaction)
        Route::middleware('admin.permission:order.manage')->group(function () {
            Route::get('orders', [\App\Http\Controllers\Admin\TransactionController::class, 'index'])->name('orders.index');
            Route::get('orders/{id}', [\App\Http\Controllers\Admin\TransactionController::class, 'index'])
                ->whereNumber('id')->name('orders.offset');
            Route::match(['get', 'post'], 'orders/{path}', [\App\Http\Controllers\Admin\TransactionController::class, 'handlePath'])
                ->where('path', '.*')->name('orders.path');
            Route::get('report', [\App\Http\Controllers\Admin\ReportController::class, 'index'])->name('report.index');
        });

        // Bài viết (controller cũ: news) — /admin/posts
        Route::get('posts', [\App\Http\Controllers\Admin\NewsController::class, 'index'])->name('posts.index');
        Route::match(['get', 'post'], 'posts/{path}', [\App\Http\Controllers\Admin\NewsController::class, 'handlePath'])
            ->where('path', '.*')->name('posts.path');

        // Legacy 301: /admin/news -> /admin/posts
        Route::match(['get', 'post'], 'news', [\App\Http\Controllers\Storefront\LegacyController::class, 'adminPosts'])
            ->name('legacy.news');
        Route::match(['get', 'post'], 'news/{path}', [\App\Http\Controllers\Storefront\LegacyController::class, 'adminPosts'])
            ->where('path', '.*')->name('legacy.news.path');

        // Trang nội dung tĩnh — hỗ trợ cả /admin/page và /admin/pages
        Route::get('page', [\App\Http\Controllers\Admin\PagesController::class, 'index'])->name('page.index');
        Route::match(['get', 'post'], 'page/add', [\App\Http\Controllers\Admin\PagesController::class, 'add'])->name('page.add');
        Route::match(['get', 'post'], 'page/edit/{id}', [\App\Http\Controllers\Admin\PagesController::class, 'edit'])
            ->whereNumber('id')->name('page.edit');
        Route::get('pages', [\App\Http\Controllers\Admin\PagesController::class, 'index'])->name('pages.index');
        Route::match(['get', 'post'], 'pages/add', [\App\Http\Controllers\Admin\PagesController::class, 'add'])->name('pages.add');
        Route::match(['get', 'post'], 'pages/edit/{id}', [\App\Http\Controllers\Admin\PagesController::class, 'edit'])
            ->whereNumber('id')->name('pages.edit');

        // Cửa hàng
        Route::get('store', [\App\Http\Controllers\Admin\StoreController::class, 'index'])->name('store.index');
        Route::match(['get', 'post'], 'store/add', [\App\Http\Controllers\Admin\StoreController::class, 'add'])->name('store.add');
        Route::match(['get', 'post'], 'store/edit/{id}', [\App\Http\Controllers\Admin\StoreController::class, 'edit'])
            ->whereNumber('id')->name('store.edit');
        Route::match(['get', 'post'], 'store/delete/{id}', [\App\Http\Controllers\Admin\StoreController::class, 'delete'])
            ->whereNumber('id')->name('store.delete');

        // Tuyển dụng
        Route::get('tuyendung', [\App\Http\Controllers\Admin\TuyendungController::class, 'index'])->name('tuyendung.index');
        Route::match(['get', 'post'], 'tuyendung/add', [\App\Http\Controllers\Admin\TuyendungController::class, 'add'])->name('tuyendung.add');
        Route::match(['get', 'post'], 'tuyendung/edit/{id}', [\App\Http\Controllers\Admin\TuyendungController::class, 'edit'])
            ->whereNumber('id')->name('tuyendung.edit');
        Route::match(['get', 'post'], 'tuyendung/delete/{id}', [\App\Http\Controllers\Admin\TuyendungController::class, 'delete'])
            ->whereNumber('id')->name('tuyendung.delete');

        // AI assistant (admin)
        Route::middleware('admin.permission:ai.manage')->group(function () {
            Route::match(['get', 'post'], 'ai-assistant', [\App\Http\Controllers\Admin\AiAssistantController::class, 'settings'])->name('ai-assistant.settings');
            Route::match(['get', 'post'], 'ai-assistant/{path}', [\App\Http\Controllers\Admin\AiAssistantController::class, 'handlePath'])
                ->where('path', '.*')->name('ai-assistant.path');
        });

        // Hỗ trợ chat (admin) — order.chat = level 0, 1 và 2
        Route::middleware('admin.permission:order.chat')->group(function () {
            Route::get('support-chat', [\App\Http\Controllers\Admin\SupportChatController::class, 'index'])->name('support-chat.index');
            Route::match(['get', 'post'], 'support-chat/{path}', [\App\Http\Controllers\Admin\SupportChatController::class, 'handlePath'])
                ->where('path', '.*')->name('support-chat.path');
        });

        // Export Excel / PDF
        Route::match(['get', 'post'], 'export/excel/{module}', [\App\Http\Controllers\Admin\ExportController::class, 'excel'])->name('export.excel');
        Route::match(['get', 'post'], 'export/pdf/{module}', [\App\Http\Controllers\Admin\ExportController::class, 'pdf'])->name('export.pdf');
        Route::match(['get', 'post'], 'export/preview/{module}', [\App\Http\Controllers\Admin\ExportController::class, 'preview'])->name('export.preview');

        // Các module admin CHƯA có alias URL trong CI3 — CI3 dùng default routing
        // /admin/{controller}/{method}. Cần khai báo tường minh cho Laravel.
        Route::middleware('admin.permission:staff.manage')->group(function () {
            Route::get('admin', [\App\Http\Controllers\Admin\AdminController::class, 'index'])->name('admin.index');
            Route::match(['get', 'post'], 'admin/{path}', [\App\Http\Controllers\Admin\AdminController::class, 'handlePath'])
                ->where('path', '.*')->name('admin.path');
        });

        Route::middleware('admin.permission:catalog.manage')->group(function () {
            Route::get('catalog', [\App\Http\Controllers\Admin\CatalogController::class, 'index'])->name('catalog.index');
            Route::match(['get', 'post'], 'catalog/{path}', [\App\Http\Controllers\Admin\CatalogController::class, 'handlePath'])
                ->where('path', '.*')->name('catalog.path');
        });

        Route::middleware('admin.permission:banner.manage')->group(function () {
            Route::get('banner', [\App\Http\Controllers\Admin\BannerController::class, 'index'])->name('banner.index');
            Route::match(['get', 'post'], 'banner/edit/{id}', [\App\Http\Controllers\Admin\BannerController::class, 'edit'])
                ->whereNumber('id')->name('banner.edit');
        });

        Route::get('slider', [\App\Http\Controllers\Admin\SliderController::class, 'index'])->name('slider.index');
        Route::match(['get', 'post'], 'slider/{path}', [\App\Http\Controllers\Admin\SliderController::class, 'handlePath'])
            ->where('path', '.*')->name('slider.path');

        Route::get('hoptac', [\App\Http\Controllers\Admin\HoptacController::class, 'index'])->name('hoptac.index');
        Route::match(['get', 'post'], 'hoptac/{path}', [\App\Http\Controllers\Admin\HoptacController::class, 'handlePath'])
            ->where('path', '.*')->name('hoptac.path');

        Route::get('vanchuyen', [\App\Http\Controllers\Admin\VanchuyenController::class, 'index'])->name('vanchuyen.index');
        Route::match(['get', 'post'], 'vanchuyen/{path}', [\App\Http\Controllers\Admin\VanchuyenController::class, 'handlePath'])
            ->where('path', '.*')->name('vanchuyen.path');

        Route::middleware('admin.permission:voucher.manage')->group(function () {
            Route::get('voucher', [\App\Http\Controllers\Admin\VoucherController::class, 'index'])->name('voucher.index');
            Route::post('voucher/add', [\App\Http\Controllers\Admin\VoucherController::class, 'store'])->name('voucher.store');
            Route::post('voucher/edit/{id}', [\App\Http\Controllers\Admin\VoucherController::class, 'update'])
                ->whereNumber('id')->name('voucher.update');
            Route::post('voucher/delete/{id}', [\App\Http\Controllers\Admin\VoucherController::class, 'destroy'])
                ->whereNumber('id')->name('voucher.delete');
            Route::match(['get', 'post'], 'voucher/toggle_active/{id}', [\App\Http\Controllers\Admin\VoucherController::class, 'toggle'])
                ->whereNumber('id')->name('voucher.toggle');
            Route::match(['get', 'post'], 'voucher/{path}', [\App\Http\Controllers\Admin\VoucherController::class, 'handlePath'])
                ->where('path', '.*')->name('voucher.path');
        });
    });
});

/*
|--------------------------------------------------------------------------
| 404 FALLBACK
|--------------------------------------------------------------------------
| CI3: $route['404_override'] = '' — dùng trang 404 mặc định.
*/
Route::fallback(function () {
    abort(404);
});/*
|==========================================================================
| TODO — B3+ (KHÔNG làm ở B2)
|==========================================================================
|
| 1. CONTROLLER CÒN 501 (cập nhật B17.1, không còn là toàn bộ controller)
|    Đã chạy: auth, home, product, cart, order, user, admin chính, kho,
|    voucher, báo cáo, đánh giá, AI, nhân viên, tin tức, giới thiệu.
|    Còn stub: banner, slider, trang tĩnh admin, cửa hàng, vận chuyển,
|    tuyển dụng, hợp tác, logo, trang tích điểm.
|
| 2. ACTION CHƯA TỒN TẠI TRONG CONTROLLER CI3
|    Route dùng `handlePath` mô phỏng default routing của CI3 (/admin/{ctrl}/{method}).
|    Khi refactor cần liệt kê method thật:
|      - StockReceipt: index, add, edit, view, confirm, cancel,
|        search_variants, filter_products, filter_options, recent_variants
|      - Transaction: index, del, detail, deldetail, accept, ship, complete,
|        cancel, chat, pending_count
|      - Inventory: index, low_stock, adjust
|      - Product: index, add, edit, del, bulk_del, list_catalog
|      - Catalog: index, add, edit, del
|      - Voucher: index, add, edit, delete, toggle_active
|      - Hoptac, Tuyendung, Vanchuyen, Slider, Store: index, add, edit, delete
|      - AiAssistant: settings, faq, faq/add, faq/edit/{id}, faq/delete/{id},
|        faq/toggle_active/{id}, conversations
|      - SupportChat: index, chat, take, send, end, return_ai, poll,
|        waiting_count, delete, purge
|      - User (admin): index, view, order, detail, accept, del, deldetail
|      - Export: excel, pdf, preview
|
| 3. HTTP METHOD — CẦN SIẾT LẠI Ở B3+
|    Hiện match(['get','post']) để bảo toàn behavior CI3. Khi refactor Controller
|    cần chuyển đúng: POST cho write, GET cho read, DELETE cho xóa
|    (kèm @method('DELETE')) — PHẢI cập nhật cả Blade/JS, không tự ý đổi.
|
| 4. MIDDLEWARE AUTH
|    `admin.auth` đã tạo (dịch từ MY_Admin_Controller). Bổ sung ở B3:
|      - `admin.permission:{permission}` dịch từ permission_helper::admin_can()
|      - `customer.auth` cho nhóm /user/* (CI3 dùng _require_login())
|    CHƯA tạo ở B2.
|
| 5. CSRF
|    CI3 dùng helper csrf_field() + window.CI_CSRF (csrf_helper.php).
|    Laravel mặc định bật CSRF nhóm web. Khi chuyển Blade (B7) phải thay bằng
|    @csrf VÀ đảm bảo AJAX gửi token — nếu không sẽ 419.
|    ẢNH HƯỞNG: gio-hang/*, ai-assistant/*, product/raty,
|    user/validate_*, thanh-toan/apply_voucher, admin/support-chat/*.
|    Cần giữ window.CI_CSRF trong giai đoạn chuyển tiếp.
|
| 6. SESSION KEY
|    `custom_cart` là giỏ hàng Laravel và được giữ.
|    `login` / `user` không còn dùng để xác thực.
|
| 7. ENV — KHÔNG đổi ở B2.
|==========================================================================
*/

