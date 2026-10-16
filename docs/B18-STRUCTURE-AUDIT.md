# B18 Structure Audit

Ngày: 2026-10-16. Chỉ đọc. Không sửa code, không xóa, không đổi route, không đụng database.

Đã đọc `docs/MIGRATION-PROGRESS.md`, `docs/MIGRATION-ISSUES.md`, `docs/B16-AUDIT.md`, `docs/B17-FULL-AUDIT.md`, `docs/B17-1-PRIORITY-CLEANUP.md`, `docs/B17-2-501-AUDIT.md`.

Quyết định giữ nguyên:

- `welcome.blade.php` đã xóa. Không tạo lại.
- `app/Policies/AdminPermission.php` đã xóa. Quyền nằm ở `app/Support/Admin/AdminPermission.php` và middleware `app/Http/Middleware/AdminPermission.php`.
- `app/Models/Store.php` giữ. Bảng `stores` có dữ liệu. Storefront cửa hàng chưa migrate.

## 1. Summary

Cấu trúc Laravel đang dùng được cho phần đã migrate (auth, home, sản phẩm, giỏ, thanh toán, tài khoản, admin bán hàng, kho, voucher, báo cáo, đánh giá, AI, nhân viên, tin tức, giới thiệu). Phần còn lại là stub `abort(501)` đã liệt kê ở B17.2, cộng lớp tương thích CI3.

Không có file nào đủ evidence để xóa ngay. Vài class không có call site, nhưng chúng nằm cạnh bảng thật hoặc là skeleton Laravel. Xóa chúng không làm project sạch hơn theo cách an toàn.

Ưu tiên vận hành: không chạy migration và seeder mặc định trên database `webshop`.

## 2. Project Statistics

Đếm file nguồn, bỏ `vendor/`, `node_modules/`, `storage/framework/`, `storage/logs/`.

| Nhóm | File | Dòng |
|------|------|------|
| Controllers | 42 | 5268 |
| Models | 31 | 2407 |
| Services | 24 | 6534 |
| Middleware | 6 | 209 |
| Requests | 21 | 879 |
| Support | 18 | 1871 |
| Blade | 76 | 5644 |
| Routes | 2 (`web.php`, `console.php`) | `web.php` có 100 lệnh `Route::` |
| Config | 13 | — |
| Tests | 32 | — |
| Database (migration, factory, seeder) | 5 | — |
| `resources/js` + `resources/css` | 3 | Vite skeleton |
| `laravel/public` JS/CSS | 0 | Storefront không đặt asset ở đây |

`assets/site` nằm ngoài thư mục `laravel/` (27 file js/css). Blade load chúng qua `Shop::assetUrl`. Chúng đang được dùng, không nằm trong `laravel/public`.

## 3. Files In Use

Hầu hết `app/` đang có route, service, view hoặc middleware alias.

Controller đã có hành vi thật: auth, home, product, cart, order, user, admin dashboard, catalog, product admin, inventory, receipt, supplier, movement, voucher, report, export, review, AI, support chat, staff, news, gioi-thieu, legacy 301.

Controller còn stub 501 cho chức năng thật: banner, slider, pages, store, tuyển dụng, hợp tác, vận chuyển, logo, hệ thống cửa hàng, loyalty. Chi tiết route nằm ở B17.2. Không xóa các controller này khi URL vẫn trỏ tới.

Model đang được query hoặc relation: `User`, `Admin`, `Product`, `ProductVariant`, `Catalog`, `Transaction`, `OrderItem`, `Voucher`, `VoucherUse`, `ContentPage`, `Banner`, `Slider`, `StockReceipt`, `StockReceiptItem`, `StockMovement`, `Supplier`, `ProductReview`, `UserAddress`, `UserPhone`, `UserSavedCart`, `UserPointLog`, `PasswordReset`, `AiSetting`, `AiFaq`, `AiConversation`, `AiMessage`, `MasterColor`.

`Store` chưa có `Store::` trong code. Vẫn tính là file cần giữ vì module cửa hàng chưa viết và bảng đang có 4 dòng.

Service đều có caller, trừ không có service nào hoàn toàn mồ côi. `VariantStockService` được cart, checkout, inventory, order admin và phiếu nhập gọi. `VoucherService` là chỗ duy nhất tính giảm giá và lượt dùng.

Middleware `admin.auth`, `admin.permission`, `admin.session`, `customer.auth`, `SyncCartSession` đều gắn route hoặc nhóm `web`. `user.session` chỉ được alias, chưa gắn route. Class vẫn là cầu session, không coi là rác.

Request FormRequest được controller resolve qua container. Không thấy Request mồ côi.

View Blade đều thuộc layout, trang đã migrate, hoặc form admin. `layouts.app` được 4 view auth dùng. Không có `view('welcome')`.

## 4. Safe To Remove

Không có.

Điều kiện SAFE đòi hỏi không route, không reference, không resolve động, không phải convention Laravel, không phải compatibility, không phải fixture. Các ứng viên dưới đây vướng ít nhất một điều kiện.

## 5. Possible Dead Files

| File | Evidence | Vì sao chưa xóa |
|------|----------|-----------------|
| `app/Support/Storefront/ContentPages.php` | Không có `ContentPages::` ngoài chính file. News và giới thiệu query `ContentPage` trực tiếp | Helper đọc `content_pages` theo type. Module tuyển dụng / vận chuyển / hợp tác sắp cần kiểu query này |
| `app/Models/MasterSize.php` | Không có `MasterSize::`. `ProductAdminService` đọc `DB::table('master_sizes')` | Bảng đang sống. Xóa model không xóa dữ liệu, nhưng mất chỗ ghi schema |
| `app/Http/Middleware/SyncLegacyUserSession.php` | Alias `user.session` trong `bootstrap/app.php`. Không route nào gắn middleware | `StorefrontComposer` vẫn đọc `session('user')` khi guard trống. `AccountService` ghi key đó khi sửa hồ sơ. Gắn hay bỏ là quyết định tương thích, không phải xóa |
| `resources/js/app.js`, `resources/js/bootstrap.js`, `resources/css/app.css` | Không Blade nào gọi `@vite`. Storefront dùng CSS/JS ở `assets/site` | `vite.config.js` và `composer.json` script `dev`/`setup` trỏ vào đây. Skeleton Laravel |
| `database/factories/UserFactory.php`, `database/seeders/DatabaseSeeder.php` | Factory tạo cột `email_verified_at`, `remember_token`. Bảng shop là `user`, không phải `users` | `php artisan db:seed` sẽ ghi sai schema. Không chạy. Chưa đủ để xóa vì đây là entry convention của Laravel |
| `routes/console.php` lệnh `inspire` | Không phục vụ shop | Artisan skeleton |
| `tests/Feature/ExampleTest.php`, `tests/Unit/ExampleTest.php` | `phpunit.xml` ép sqlite. ExampleTest gọi `GET /` trên schema trống | ISSUE-B5-10. Suite thật dùng `LegacyMysqlTestCase`. Giữ để không đổi phpunit cho đến khi có quyết định riêng |
| `assets/site/bootstrap/js/npm.js`, `assets/site/bootstrap/css/bootstrap-theme.css` | Không thấy Blade Laravel hay view CI3 `application/` nhúng hai file này | Nằm cây asset dùng chung với CI3. Cần rà thêm view cũ ngoài `application/` trước khi đụng |

`app/Models/Store.php` không nằm trong danh sách này.

## 6. Duplicate Files / Logic

Không có hai file controller cùng một việc. `Admin\UserController` là khách hàng admin. `Storefront\UserController` là tài khoản khách. `Admin\ProductController` là CRUD. `Storefront\ProductController` là catalog và trang sản phẩm.

| Logic | Nơi đang giữ | Nơi khác cũng đụng | Đề xuất |
|-------|----------------|--------------------|---------|
| Giá bán hiển thị | `app/Support/Storefront/Product.php` | Cart và checkout tự tính lại giá dòng | Giữ helper cho view. Giỏ và đơn tiếp tục tính trên server, không lấy giá client |
| Tồn kho | `VariantStockService` | `InventoryService`, `CheckoutService`, `CartSessionService`, `StockReceiptService`, `OrderAdminService` gọi service này | Giữ một service. `product.quantity` chỉ là cache |
| Voucher | `VoucherService` | Checkout gọi, không tự tính | Giữ một chỗ |
| Quyền admin | `Support/Admin/AdminPermission.php` | Middleware, Gate trong `AuthServiceProvider`, helper CI3 `permission_helper.php` | Giữ cả hai trong lúc CI3 còn chạy. Không gộp |
| Flash | `Support/Storefront/Flash.php` và key `message_success` / `message_fail` | Nhiều controller admin ghi session trực tiếp | Giữ key hiện tại. Layout admin chỉ đọc hai key đó |
| URL | `Support/Storefront/Urls.php` | Legacy redirect và một số `url()` rời | Giữ `Urls` cho URL SEO. Redirect 301 gọi `Urls` |
| Trang nội dung | `ContentPage` + query trong News và Gioithieu | `ContentPages` helper không được gọi | Khi migrate trang tĩnh, chọn một lối query rồi mới bỏ helper |
| Ảnh upload | `ProductImageStore`, `NewsImageStore` | Cùng kiểu tên file server-side, khác prefix | Không gộp trong lúc banner/slider chưa có store |
| Size | `MasterSize` model | `ProductAdminService::sizes()` dùng query builder | Một lối là đủ, nhưng chưa đổi |

Alias route cố ý, không phải route hỏng: `/VanChuyen` và `/vanchuyen`, `/tich-diem` và `/TichDiem`, `/admin/page` và `/admin/pages`, `/admin/receipts` và `/admin/stock-receipts`, `/admin/inventory` và `/admin/stock-inventory`. Giữ vì URL CI3.

## 7. Oversized Controllers

Không tách trong task này. Chiều dài một mình không đủ lý do tách.

| File | Dòng | Nhận xét |
|------|------|----------|
| `Storefront/ProductController.php` | 790 | Catalog, lọc, trang chi tiết, đánh giá, lượt xem. Route storefront đang bám method này. Tách sau khi không còn sửa catalog |
| `Storefront/UserController.php` | 592 | Login, đăng ký, hồ sơ, địa chỉ, điện thoại, mật khẩu, đơn của khách. Đã đẩy dữ liệu sang `AccountService` |
| `Admin/StockReceiptController.php` | 485 | Nhiều action CI3 (thêm, xem, xác nhận, hủy, lọc). Logic nặng nằm ở `StockReceiptService` |
| `Storefront/CartController.php` | 251 | Ổn. Logic ở `CartSessionService` |
| `Admin/AdminController.php` | 227 | Logout giữ `custom_cart` và CRUD nhân viên. Comment đầu file vẫn nói `index` là stub 501. Comment đó sai sau B16. Không phải file chết |
| `Admin/ProductController.php` | 223 | Mỏng so với `ProductAdminService` |
| `Admin/ReportController.php` | ngắn | Chỉ gọi `ReportService`. Không cần tách thêm |

`LegacyController` dài vì nhiều redirect 301. `index`, `handlePath`, `__call` không có route. Phần 301 phải giữ.

## 8. Oversized Services

| File | Dòng | Nhận xét |
|------|------|----------|
| `CartSessionService.php` | 776 | Giỏ session `custom_cart`, merge, tồn, giá. Một nguồn cho header và trang giỏ |
| `ReportService.php` | 706 | Doanh thu status 3 và export. `ReportFileWriter` đã tách ghi file |
| `Ai/ChatService.php` | 595 | FAQ, Gemini, rate limit, handoff. Không gọi API trong audit |
| `ProductAdminService.php` | 542 | Ảnh local, biến thể, `master_sizes` |
| `AdminDashboardService.php` | 524 | Số liệu dashboard |
| `CheckoutService.php` | 513 | Tạo đơn, voucher, trừ kho sau commit |
| `AccountService.php` | 442 | Hồ sơ và sổ địa chỉ |
| `StockReceiptService.php` | 424 | Phiếu nhập |

Tách các file này dễ làm lệch B3–B17. Để nguyên cho đến khi có việc sửa đúng module.

## 9. Unused Routes

Không có `Route::fallback`.

Route tới method không khai báo, rơi `__call` rồi 501: `/admin/banner/edit/{id}`, `/admin/page/edit/{id}`, `/admin/pages/edit/{id}`, `/admin/store/add|edit|delete`, `/admin/tuyendung/add|edit|delete`, `/ThongTinTuyenDung/{slug}` gọi `view`. Đây là chức năng chưa migrate, không phải route thừa. B17.2 đã phân loại.

Catch-all `{slug}` danh mục đặt cuối `web.php`, có loại trừ `admin`. Giữ thứ tự đó.

`/up` là health check của `bootstrap/app.php`, không khai báo trong `web.php`.

Alias `user.session` không được gắn. Không xóa alias trong bước cleanup mù.

Lệnh `inspire` trong `routes/console.php` không phục vụ shop.

## 10. Unused Views

76 Blade. Không thấy view nào không được `@extends`, `@include` hoặc `view()`.

Đã xóa và không còn: `resources/views/welcome.blade.php`.

`layouts/app.blade.php` chỉ bọc trang đăng nhập, đăng ký, quên mật khẩu. Storefront dùng `layouts.storefront.*`. Admin dùng `layouts.admin.app`. Cả ba layout đều đang dùng.

Chưa có Blade cho module 501 (banner admin, slider admin, cửa hàng, vận chuyển, tuyển dụng, hợp tác, loyalty, logo). View CI3 tương ứng còn trong `application/views/`. Đó là việc migrate, không phải view Laravel thừa.

## 11. Unused JS

Trong `laravel/resources/js`: `app.js` và `bootstrap.js` chỉ thuộc Vite. Blade không load.

JS đang chạy nằm ở `assets/site/js` và `assets/admin/js`, nhúng bằng `<script>`:

- Storefront: `custom.js`, `product-detail.js`, `stock-notice-modal.js`, `catalog-luxury.js`, `cart-luxury.js`, `checkout-voucher.js`, `vn-address.js`, `header-icons.js`, `ai-chat.js`, jquery, jcarousel, raty.
- Admin: jquery, bootstrap bundle, `chart.min.js`, `admin-dashboard.js`, CKEditor trên form sản phẩm.

`assets/site/js/hethongcuahang.js` chưa được Blade Laravel gọi vì trang cửa hàng còn 501. View CI3 `application/views/site/hethongcuahang/index.php` vẫn nhúng file này. Giữ.

`assets/site/bootstrap/js/npm.js` không thấy reference. Possible dead, cây asset dùng chung.

Không gỡ package npm. `axios`, `tailwindcss`, `vite` không được storefront import. Chúng thuộc skeleton. `composer.json` không kéo chúng vào PHP runtime.

## 12. Unused CSS

`resources/css/app.css` là Tailwind qua Vite. Blade shop không link file này.

CSS đang dùng: `assets/site/css/*` (layout-chrome, style, catalog, cart, checkout, account, product detail, ai-chat, responsive) và `assets/admin/css/*` (bootstrap5, admin-theme, dark mode, dashboard).

`assets/site/bootstrap/css/bootstrap-theme.css` không thấy view nhúng. Possible dead, cùng cây asset CI3.

`bootstrap.css` chính vẫn được `head.blade.php` load.

## 13. Unused Config

| File | Tình trạng |
|------|------------|
| `config/shop.php` | Tên shop, logo, upload, VietQR, địa chỉ VN. Đang gọi |
| `config/ai.php` | `GEMINI_API_KEY` từ env. Đang gọi |
| `config/stock.php` | `default_min_stock`, âm kho. Đang gọi |
| `config/auth.php` | Guard `web` và `admin`, provider `legacy_eloquent`, bảng reset `password_resets` |
| `config/app.php`, `database.php`, `session.php`, `cache.php`, `mail.php`, `queue.php`, `logging.php`, `filesystems.php` | Framework. Mail được `AuthMailService` dùng |
| `config/services.php` | Skeleton dịch vụ ngoài. Chưa thấy shop gọi key riêng. Giữ vì Laravel đọc khi có tích hợp |

Không xóa config vì chưa thấy một dòng `config('services...')`.

`composer.json` script `setup` có `php artisan migrate --force`. Script đó nguy hiểm trên database `webshop` nếu ai chạy nguyên cụm. Không sửa trong audit này. Ghi để không chạy.

## 14. Legacy Compatibility

| Phần | Loại |
|------|------|
| `LegacyController` các method 301 (cart, order, posts, product, catalog) | KEEP FOR COMPATIBILITY |
| `LegacyController::index`, `handlePath`, `__call` | DEAD trong controller đang sống. REMOVE LATER, không xóa cả class |
| `LegacyPassword`, provider `legacy_eloquent` | KEEP. MD5 cũ rồi bcrypt |
| `SyncLegacyAdminSession` (`admin.session`) | KEEP. Đang gắn nhóm admin |
| `SyncLegacyUserSession` (`user.session`) | KEEP FOR COMPATIBILITY. Chưa gắn route |
| `SyncCartSession`, session `custom_cart` | KEEP |
| `Urls`, slug `-p{id}`, `-c{id}` | KEEP |
| Bảng `user` chứ không phải `users` | KEEP. Model `User` |
| Flash `message_success` | MIGRATED BUT STILL REFERENCED |

## 15. 20 Dead 501 Review

Đối chiếu lại route với B17.2. Vẫn đúng: 44 `abort(501)`, 20 dòng không có route tới method.

Các dòng UNREACHABLE, có thể bỏ method sau, không bỏ controller:

`HethongcuahangController::__call`, `Vanchuyen` storefront `__call`, `Tuyendung` storefront `handlePath`, `Hoptac` storefront `handlePath` và `__call`, `Loyalty` `handlePath` và `__call`, `HomeController` `handlePath` và `__call`, `LegacyController` `index`, `handlePath`, `__call`, `BannerController::handlePath`, `SliderController::__call`, `PagesController::handlePath`, `StoreController::handlePath`, `Tuyendung` admin `handlePath`, `Hoptac` admin `__call`, `Vanchuyen` admin `__call`, `SiteBrandingController::__call`.

Risk nếu xóa nhầm `__call` đang được route trỏ tới (`Banner`, `Pages`, `Store`, `Tuyendung` admin, `Tuyendung` storefront): URL edit/add/delete đổi từ 501 thành lỗi method không tồn tại. Những `__call` đó không nằm trong 20 dòng dead.

Dependency: không có view hay test gọi 20 method dead. Test không assert các URL đó trả 501.

Không xóa trong B18.

## 16. Architecture Problems

Điểm: A tốt, B ổn, C cần cải thiện, D cần refactor. Mục tiêu là ổn định, không phải điểm A.

| Hạng mục | Điểm | Lý do |
|----------|------|--------|
| Folder | B | `Admin` và `Storefront` tách rõ. Service để phẳng, chỉ `Ai`, `Export`, `Cart`, `Checkout`, `Account` có thư mục con. Đủ dùng |
| Controller | C | Vài file dài và một lớp stub 501 copy cùng khuôn |
| Model | B | Một model một bảng. `Store` và `MasterSize` chưa đi qua Eloquent |
| Service | B | Logic tiền, kho, voucher đã ra khỏi controller. Vài service trên 500 dòng |
| Request | B | Form có rule thì có FormRequest. Action CI3 không validate form thì không có. Đúng với migration |
| Middleware | B | Alias đủ. `user.session` chưa gắn |
| View | B | Layout storefront / admin / auth tách. Blade dài ở tài khoản và trang sản phẩm vì port CI3 |
| JS | C | Hai hệ: Vite không được trang shop dùng, asset thật nằm ngoài `laravel/` |
| CSS | C | Cùng tình trạng JS. File đang load thì rõ |
| Route | C | `web.php` dài, catch-all phải đứng cuối, comment TODO còn câu cũ, nhiều URL 501 cố ý |
| Config | B | `shop`, `ai`, `stock` đúng việc. Migration mặc định là rủi ro vận hành, không phải config thừa |
| Legacy | B | Cần cho URL cũ, mật khẩu MD5, session giỏ |

Vấn đề cấu trúc thật:

1. Migration `0001_01_01_000000_create_users_table.php` tạo `users`, `password_reset_tokens`, `sessions`. Shop dùng `user` và `password_resets`. `php artisan migrate` trên `webshop` có thể tạo bảng lạ. Không chạy.
2. `DatabaseSeeder` gọi `User::factory()` với cột Laravel mặc định.
3. Asset và Vite song song. Không chuyển sang Vite chỉ để gọn.
4. Comment `AdminController` và một đoạn TODO trong `routes/web.php` lệch code sau B16/B17.

Namespace nhất quán: controller theo `Admin` / `Storefront`, model ở `App\Models`, không có model trong controller.

## 17. Recommended Cleanup Order

P0: không có việc xóa file.

P1, đừng làm hỏng runtime:

1. Không chạy `migrate`, `migrate:fresh`, `db:seed` trên `webshop`.
2. Không xóa `LegacyController` 301, `LegacyPassword`, `custom_cart`, `Store.php`.

P2, chỉ sau khi verify từng mục:

3. Khi sửa comment, cập nhật đầu `AdminController` và TODO route cho khớp code. Không đổi route.
4. Quyết định `user.session`: gắn vào nhóm storefront hoặc ghi rõ composer đã đủ vì guard `web`.
5. 20 method 501 không route: xóa từng method sau test route:list, không xóa cả class stub.

P3:

6. `ContentPages` và `MasterSize`: chỉ dọn khi module kế tiếp chọn một cách query.
7. Vite/Tailwind skeleton: giữ đến khi có quyết định asset. Không uninstall.
8. `ExampleTest` sqlite: tách khỏi lệnh test shop, không xóa trong lúc phpunit.xml còn ép sqlite.
9. `npm.js` và `bootstrap-theme.css`: rà CI3 thêm rồi mới xét.

Không tách `ProductController` hay `ReportService` trong đợt cleanup cấu trúc.

## 18. Files That Must NOT Be Deleted

- `app/Models/Store.php`
- `app/Support/Admin/AdminPermission.php` và `app/Http/Middleware/AdminPermission.php`
- `app/Http/Middleware/SyncCartSession.php`, `SyncLegacyAdminSession.php`, `AdminAuth.php`, `CustomerAuth.php`
- `app/Support/LegacyPassword.php`, `app/Auth/LegacyPasswordUserProvider.php`
- `app/Services/CartSessionService.php`, `CheckoutService.php`, `VoucherService.php`, `VariantStockService.php`, `InventoryService.php`
- `app/Services/ReportService.php`, `app/Services/Export/ReportFileWriter.php`
- `app/Support/Storefront/Urls.php`, `Shop.php`, `HtmlSanitizer.php`
- `app/Http/Controllers/Storefront/LegacyController.php` (phần 301)
- `routes/web.php`
- `config/shop.php`, `config/ai.php`, `config/stock.php`, `config/auth.php`
- `tests/LegacyMysqlTestCase.php` và các feature test MySQL
- Mọi controller stub 501 khi route còn trỏ tới
- `assets/site/js/ai-chat.js`, `hethongcuahang.js`, CSS layout đang được Blade hoặc CI3 nhúng
- Migration files. Không chạy chúng trên `webshop`

`welcome.blade.php` và `app/Policies/AdminPermission.php` đã ở ngoài repo. Không phục hồi.

## 19. Final Decision

B18 STATUS: AUDIT_COMPLETE

Không ghi DONE. Chưa cleanup.

Summary:

- Total files audited: khoảng 287 file nguồn trong `laravel/` (app 150, Blade 76, test 32, config 13, database 5, route 2, bootstrap 2, js/css Vite 3, file gốc composer/package/vite/phpunit). Thêm 27 js/css ở `assets/site` vì Blade tham chiếu.
- Files currently used: gần hết `app/`, 76 Blade, asset shop/admin đang được `<link>` và `<script>` gọi.
- Safe to remove: 0
- Possible dead: 8 mục ở mục 5, cộng 20 method 501 không có route
- Duplicate: 0 file trùng. Vài logic giá/nội dung có hai lối đọc, đã ghi chỗ nên giữ
- Oversized: 3 controller và 8 service trên, không tách lúc này
- Legacy: redirect 301, mật khẩu MD5, session giỏ và session admin. Giữ
- Needs review: migration mặc định, seeder, `user.session` chưa gắn, `ContentPages`, `MasterSize`, Vite skeleton

Top 10 cleanup candidates:

1. Chặn vận hành: không migrate và không seed database `webshop`.
2. 20 method `abort(501)` không có route, xóa sau khi route:list xác nhận.
3. Comment sai ở `AdminController` và TODO cũ trong `routes/web.php`.
4. Quyết định gắn hoặc ghi chú `user.session`.
5. `app/Support/Storefront/ContentPages.php` không có caller.
6. `app/Models/MasterSize.php` không có caller, bảng vẫn được query builder đọc.
7. `DatabaseSeeder` và `UserFactory` lệch schema `user`.
8. `ExampleTest` sqlite.
9. Vite/Tailwind/`resources/js` không được trang shop load.
10. `bootstrap-theme.css` và `bootstrap/js/npm.js` không thấy view nhúng.

Recommended next action: KEEP AS-IS

Dọn cấu trúc lúc này không tăng correctness. Bước sản phẩm tiếp theo vẫn là migrate trang storefront đang 501 (vận chuyển, cửa hàng, tích điểm), không phải xóa file.
