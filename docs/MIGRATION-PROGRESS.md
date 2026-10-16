# Migration progress — CodeIgniter 3 → Laravel 12

Trạng thái: `IN_PROGRESS` / `DONE` / `DONE_WITH_ISSUES`. Chi tiết lỗi/sai lệch: [MIGRATION-ISSUES.md](MIGRATION-ISSUES.md).

| Bước | Module | Trạng thái |
|------|--------|-----------|
| B3 | Auth storefront (đăng nhập / đăng ký / quên mật khẩu / logout) | DONE |
| B4 | Home / Product / PDP / layout storefront | DONE_WITH_ISSUES (3 lỗi B4 sửa trong B5 — xem ISSUE-B5-01..03) |
| B5 | Storefront Cart | **DONE_WITH_ISSUES** |
| B6 | Order / Checkout | **DONE_WITH_ISSUES** (ISSUE-B6-02 đóng bởi B7) |
| B7 | User Account / Order History | **DONE_WITH_ISSUES** |
| B8 | Admin Dashboard + Admin Framework | **DONE_WITH_ISSUES** |
| B9 | Admin Product + Catalog | **DONE_WITH_ISSUES** |
| B10 | Admin Order + Customer | **DONE_WITH_ISSUES** |
| B11 | Admin Inventory + Supplier + Receipt | **DONE_WITH_ISSUES** |
| B12 | Admin Voucher | **DONE_WITH_ISSUES** |
| B13 | Admin Reports + Export Excel/PDF | **DONE_WITH_ISSUES** |
| B14 | Product Reviews + Rating | **DONE_WITH_ISSUES** |
| B15 | AI / Chat | **DONE_WITH_ISSUES** |
| B16 | Admin nhân viên | **DONE_WITH_ISSUES** |
| B17 | Tin tức storefront + admin | **DONE_WITH_ISSUES** |
| B17.1 | Secret, URL legacy, HTML, giới thiệu | **DONE_WITH_ISSUES** |

---

## B5 — Storefront Cart (`DONE_WITH_ISSUES`)

Nguồn CI3: `application/controllers/Cart.php`, `application/helpers/cart_helper.php`,
`application/libraries/Product_service.php` (kiểm tra tồn kho), `application/core/MY_Controller.php` (đồng bộ giỏ mỗi request).

### Cấu trúc `custom_cart` (giữ nguyên CI3)

```
session('custom_cart') = [
  "{product_id}_" . md5("{size}_{color}") => [
    'rowid', 'id' (int), 'variant_id' (int), 'qty' (int), 'price' (float), 'name', 'subtotal' (float),
    'options' => ['image_link', 'size', 'color'],
  ],
]
```

Thứ tự key và kiểu dữ liệu được test khóa lại (`test_product_detail_add_to_cart_end_to_end`).
Bảng `user_saved_cart` (`user_id` PK, `cart_json`, `updated`) đồng bộ mỗi lần giỏ thay đổi khi đã đăng nhập — như `cart_persist_session`.

### File

| File | Vai trò |
|------|--------|
| `laravel/app/Services/CartSessionService.php` | Service giỏ: add / updateOptions / changeQuantity / remove / clear / preparePage (sanitize → recalculate → enrich), persist + sync `user_saved_cart`, restore khi login |
| `laravel/app/Services/VariantStockService.php` | Port `Product_service`: resolve biến thể, kiểm tra tồn kho, thông báo CI3 |
| `laravel/app/Services/Cart/CartActionResult.php` | Kết quả action (ok / code / message / payload JSON) |
| `laravel/app/Services/Cart/CartPageData.php` | Dữ liệu trang giỏ (items hiển thị, tổng, thông báo) |
| `laravel/app/Http/Controllers/Storefront/CartController.php` | `index`, `add`, `updateOptions`, `update`, `del`, `handlePath` |
| `laravel/app/Http/Middleware/SyncCartSession.php` | = `MY_Controller`: user đăng nhập → `ensureForLoggedInUser`, khách → xóa `custom_cart` (bỏ qua `/admin`) |
| `laravel/bootstrap/app.php` | Đăng ký `SyncCartSession` vào nhóm `web` |
| `laravel/app/Http/Controllers/Storefront/LegacyController.php` | `/cart`, `/cart/{path}` → 301 sang `/gio-hang...` |
| `laravel/resources/views/site/cart/index.blade.php` | Port `views/site/cart/index.php` (giữ class/JS `cart-luxury.js`) |
| `laravel/resources/views/site/partials/stock-notice-modal.blade.php` | Markup `qdStockNotice*` mà `stock-notice-modal.js` cần |
| `laravel/app/Support/Storefront/Flash.php` | `auth()` flash; `all()` memo theo request |
| `laravel/app/View/Composers/StorefrontComposer.php` | Memo dữ liệu dùng chung theo request; sửa query badge đơn hàng |
| `laravel/tests/LegacyMysqlTestCase.php` | Base test MySQL thật + `DatabaseTransactions` (rollback mỗi test) |
| `laravel/tests/Feature/Cart/*` | `CartTestCase`, `CartTest`, `CartCsrfTest`, `StorefrontRegressionTest` |

### Route (URL CI3 giữ nguyên)

| Method | URL | Action |
|--------|-----|--------|
| GET | `/gio-hang` | index |
| GET, POST | `/gio-hang/them/{id}`, `/gio-hang/add/{id}` | add |
| GET, POST | `/gio-hang/cap-nhat-thuoc-tinh` | updateOptions |
| POST (GET → 404) | `/gio-hang/update`, `/gio-hang/update/{rowid}` | update (`action=sum/sub`) |
| POST (GET → 404) | `/gio-hang/del`, `/gio-hang/del/{rowid}` | del (không rowid = xóa hết) |
| GET, POST | `/gio-hang/{path}` | handlePath (`index`, `add/{id}`, `update_options`, `update`, `del`; khác → 404) |
| GET, POST | `/cart`, `/cart/{path}` | 301 → `/gio-hang`, `/gio-hang/{path}` |

### Hành vi đã port

- Bắt buộc đăng nhập: `index`/`add` → lưu `redirect_back`, flash auth, chuyển `/dang-nhap`; action khác → AJAX 401 JSON `login_required`, form → flash store + chuyển login.
- Giá = `price - discount` (fallback 1000 nếu ≤ 0), luôn lấy từ DB; `price`/`subtotal`/`stock` từ client bị bỏ qua.
- Tồn kho theo biến thể `product_variants` (status = 1, `đã có trong giỏ + qty ≤ stock`), thông báo tồn kho + payload modal như CI3.
- Trang giỏ: xóa sản phẩm đã bị xóa khỏi DB, cập nhật giá, loại dòng ngừng bán / hết hàng, thông báo flash.
- JSON trả `csrf_name` / `csrf_hash`; CSRF Laravel bật cho mọi POST.
- Login: khôi phục giỏ từ `user_saved_cart`; logout: đồng bộ trước khi xóa session; chỉ ghi dòng của user hiện tại.
- Hiệu năng: trang giỏ preload sản phẩm (`whereIn`) + biến thể (1 query) — không N+1; dữ liệu layout chỉ query một lần/request.

### Kiểm thử (MySQL thật `webshop`, mỗi test rollback)

```
php artisan test tests/Feature/Cart   →  42 passed (275 assertions)
php artisan test                       →  43 passed, 1 failed (ExampleTest mặc định, sqlite — ISSUE-B5-10)
```

Phủ: A route · B guest/login · C thêm giỏ (PDP + link GET) · D merge/tách dòng, option sai · E tồn kho / biến thể ngừng bán · F giá server · G cập nhật số lượng AJAX + form · H đổi size/màu · I xóa / xóa hết, tính lại khi mở trang · J CSRF (middleware thật, 419 khi thiếu token) + không đụng giỏ người khác · login/logout/restore · 301 legacy · regression Home / danh mục / bán chạy / mới / khuyến mãi / tìm kiếm / PDP / đăng nhập / đăng ký (khách + user có giỏ) · badge đơn hàng.

Kiểm tra khác: `php -l` toàn bộ file đổi — OK; `php artisan view:cache` — OK; `route:list` — đúng bảng trên;
sau khi chạy test: không còn user / sản phẩm / biến thể / đơn test trong DB; `user_saved_cart` vẫn 2 dòng thật (user 10, 11) không đổi.

---

## B6 — Order + Checkout (`DONE_WITH_ISSUES`)

Nguồn CI3: `application/controllers/Order.php` (không có Checkout.php).
Hỗ trợ: `Voucher_model`, `Transaction_model`, `Order_model`, `Loyalty_model`,
`Inventory_service`, `Product_service`, `loyalty_helper.php`, `cart_helper.php`.

### Mapping CI3 → Laravel

| CI3 | Laravel |
|-----|---------|
| `Order::index` GET/POST `/thanh-toan` | `OrderController::index` |
| `Order::apply_voucher` | `OrderController::applyVoucher` + `VoucherService` |
| `Order::checkout_qr` | `OrderController::checkoutQr` |
| `Order::cancel` POST | `OrderController::cancel` |
| `custom_cart` | `CartSessionService::sanitizeForCheckout` |
| `voucher_model->validate_for_checkout` | `VoucherService::validateForCheckout` (lock FOR UPDATE lúc tạo đơn) |
| `inventory_service->deduct_for_order` | `InventoryService::deductForOrder` (`UPDATE ... WHERE stock >= qty`) |
| `loyalty_model->on_order_cancelled` | `LoyaltyService::onOrderCancelled` |
| session `checkout_voucher` / `order_access_ids` | cùng key |
| `Legacy::order` / `order_path` | 301 `/thanh-toan` |

### Luồng đã xác định từ CI3 (giữ)

1. Checkout **không bắt login** ở controller; khách vẫn xem form. Giỏ khách bị `SyncCartSession` xóa (như `MY_Controller`) nên POST khách luôn gặp giỏ trống → `/gio-hang` → login.
2. Form: name, email, phone, address, message, voucher, payment `COD` | `Chuyển khoản`. Prefill từ bảng `user` (không đọc `user_address` / `user_phone`).
3. Không có shipping, không gửi email lúc đặt hàng.
4. Giá = `price - discount` từ DB (int subtotal cho voucher). Client price/total/discount/stock bị bỏ.
5. Tạo đơn trong `DB::transaction`: `transaction` → `voucher_use` + `used_count` → trừ kho + `stock_movements` → dòng `order`. Fail kho thì rollback.
6. Status mới = 0. COD: flash + redirect `/gio-hang`. Chuyển khoản: redirect `/thanh-toan/checkout_qr/{id}` (VietQR img.vietqr.io).
7. Hủy POST, status 0 hoặc 1, hoàn kho + hoàn voucher, redirect `/user`. GET cancel = 404. IDOR: chủ đơn hoặc session `order_access_ids`.

### File

| File | Vai trò |
|------|--------|
| `laravel/app/Services/CheckoutService.php` | Đặt hàng, voucher session, QR, hủy |
| `laravel/app/Services/VoucherService.php` | Validate / record / release voucher |
| `laravel/app/Services/InventoryService.php` | Trừ/hoàn kho + cache `product.quantity` |
| `laravel/app/Services/LoyaltyService.php` | Hoàn điểm/voucher khi hủy |
| `laravel/app/Support/Storefront/Loyalty.php` | Port `loyalty_helper.php` |
| `laravel/app/Http/Controllers/Storefront/OrderController.php` | Controller mỏng |
| `laravel/app/Http/Requests/Storefront/CheckoutRequest.php` | Validate form (không nhận giá) |
| `laravel/resources/views/site/order/index.blade.php` | Checkout |
| `laravel/resources/views/site/order/checkout_qr.blade.php` | VietQR |
| `laravel/config/stock.php` | `allow_negative_stock` |
| `laravel/config/shop.php` | `vietqr.*` (giá trị CI3) |
| `laravel/app/Models/BaseModel.php` | Sửa `legacyTimestampCache` không lọt vào INSERT bảng `order` |
| `laravel/tests/Feature/Order/*` | Feature test MySQL |

### Route

| Method | URL | Action |
|--------|-----|--------|
| GET, POST | `/thanh-toan` | index |
| GET, POST | `/thanh-toan/apply_voucher` | applyVoucher (JSON + CSRF meta) |
| GET, POST | `/thanh-toan/checkout_qr/{id}` | checkoutQr |
| POST (GET → 404) | `/thanh-toan/cancel/{id}` | cancel |
| GET, POST | `/thanh-toan/{path}` | handlePath |
| GET, POST | `/order`, `/order/{path}` | 301 → `/thanh-toan...` |

### Kiểm thử

```
php artisan test tests/Feature/Order   →  19 passed (131 assertions)
php artisan test tests/Feature/Order tests/Feature/Cart  →  61 passed (406 assertions)
```

Phủ A–O: guest/auth, giỏ trống, COD 1 SP + nhiều SP, variant, vượt kho, giá giả từ client, voucher (hợp lệ/hết hạn/tối thiểu/hết lượt), COD + chuyển khoản + QR, tạo transaction/order/stock_movements, rollback khi hết hàng, clear `custom_cart` + `user_saved_cart`, double submit, IDOR, hủy status 0 vs 3, CSRF 419, regression Home/PDP/Cart/Checkout.

Cleanup: 0 user/product/variant/voucher/transaction test còn lại. `user_saved_cart` user 10/11 không đổi. JM10 used_count=0, JM20 used_count=2 không đổi.

---

## B7 — User Account / Order History (`DONE_WITH_ISSUES`)

Nguồn CI3: `application/controllers/User.php` (index, order_detail, profile_update, password_change, phone_*, address_*),
`User_address_model` / `User_phone_model` (MAX 3), `Loyalty_model::get_profile`, `Voucher_model::list_available_for_user`,
`views/site/user/index.php` + `order_detail.php`. **Không có URL `/user/order`** — lịch sử nằm trên `GET /user`.

### Mapping CI3 → Laravel

| CI3 | Laravel |
|-----|---------|
| `GET /user` (+ `order_status`, `order_page`) | `UserController::index` + `AccountService` |
| `GET /user/order_detail/{id}` (fragment AJAX, không layout) | `UserController::orderDetail` — 404 nếu không sở hữu |
| `POST /user/profile_update` | `UpdateProfileRequest` — chỉ họ tên |
| `POST /user/password_change` | `ChangePasswordRequest` + `LegacyPassword` |
| `POST /user/phone_add\|delete\|default` | `AccountService` — tối đa 3 số |
| `POST /user/address_add\|delete\|default` | `AccountService` + `VnAddress` — tối đa 3 địa chỉ |
| `POST /thanh-toan/cancel/{id}` | giữ B6 `CheckoutService::cancel` |
| Guest | middleware `customer.auth` → `/dang-nhap` + `redirect_back` |

Ownership đơn: `user_id` khớp, không thì email không phân biệt hoa thường (đơn khách cũ). User A xem/hủy/QR đơn B → 404 (chi tiết) hoặc redirect home (QR/cancel B6).

### File

| File | Vai trò |
|------|--------|
| `app/Services/AccountService.php` | Sổ ĐT/địa chỉ, đổi tên/mật khẩu, ownership đơn, phân trang 5 |
| `app/Services/LoyaltyService.php` | Thêm `getProfile()` |
| `app/Services/VoucherService.php` | Thêm `listAvailableForUser()` |
| `app/Support/Storefront/VnAddress.php` | Port `vn_address_helper.php` |
| `app/Support/Storefront/OrderStatus.php` | Nhãn trạng thái CI3 |
| `app/Http/Requests/Storefront/{UpdateProfile,ChangePassword,AddPhone,AddAddress}Request.php` | Validate + flash fail + redirect hash |
| `app/Http/Controllers/Storefront/UserController.php` | Bỏ stub 501; account + handlePath |
| `resources/views/site/user/index.blade.php` | Dashboard + lịch sử + modal |
| `resources/views/site/user/order_detail.blade.php` | Partial AJAX |
| `tests/Feature/Account/*` | Feature test MySQL |

### Route (URL CI3 giữ nguyên)

| Method | URL | Action |
|--------|-----|--------|
| GET | `/user` | index (customer.auth) |
| GET | `/user/order_detail/{id}` | fragment chi tiết |
| POST | `/user/profile_update` | đổi họ tên |
| POST | `/user/password_change` | đổi mật khẩu |
| POST | `/user/phone_add` | thêm SĐT |
| GET→redirect / POST | `/user/phone_delete/{id}`, `/user/phone_default/{id}` | xóa / mặc định |
| POST | `/user/address_add` | thêm địa chỉ |
| GET→redirect / POST | `/user/address_delete/{id}`, `/user/address_default/{id}` | xóa / mặc định |
| GET, POST | `/user/logout` | logout (public) |
| GET, POST | `/user/{path}` | handlePath (`index`, `login`, `order_detail/{id}`, …) |

### Kiểm thử (MySQL thật `webshop`, `DatabaseTransactions`)

```
php artisan test tests/Feature/Account                         →  17 passed
php artisan test tests/Feature/Account tests/Feature/Cart tests/Feature/Order
                                                               →  78 passed (580 assertions)
```

Phủ: guest redirect login · dashboard · profile name-only · password bcrypt + MD5 hiện tại · phone CRUD/max 3/trùng/IDOR · address CRUD/IDOR · lịch sử chỉ đơn mình + lọc + phân trang · order_detail owner vs 404 · QR/cancel IDOR · hủy idempotent · CSRF 419 · logout + saved cart · handlePath · regression B3/B4/B5/B6/B7.

Cleanup: 0 user/product/order test. `user_saved_cart` user 10 (335 bytes) / 11 (2 bytes) không đổi. JM10 used_count=0, JM20 used_count=2. 31 dòng `user_address` orphan MyISAM (B3 register + B7 trước khi tearDown) đã xóa.

**NEXT STEP:** B8 Admin Dashboard + Admin Framework. Không triển khai trong task B7.

---

## B8 — Admin Dashboard + Admin Framework (`DONE_WITH_ISSUES`)

Nguồn CI3: `controllers/admin/Home.php` (route `/admin` → `admin/home/index`), `core/MY_Admin_Controller.php`, `helpers/permission_helper.php`, `helpers/admin_dashboard_helper.php`, `helpers/admin_helper.php`, `views/admin/layouts/*`, `views/admin/sidebar.php`, `views/admin/header.php`, `views/admin/home/index.php`.

View dashboard CI3 không dùng bộ đếm đơn giản trong `Home::index()`. Số liệu lấy từ `admin_dashboard_get_data()`.

### Ánh xạ

| CI3 | Laravel | Route |
|-----|---------|--------|
| `admin/Home::index` | `Admin\HomeController@index` | `GET /admin` (`admin.home`), `GET /admin/home` (`admin.home.alias`) |
| `admin/home/{path}` khác `index` | `HomeController@handlePath` | 404 (không 501) |
| `permission_helper` | `App\Support\Admin\AdminPermission` + `Gate::admin.can` / `admin.access-panel` | — |
| `admin_can()` trong constructor | middleware `admin.permission:{quyền}` | redirect `/admin` + `message_fail` (đúng CI3, không 403) |
| `admin_dashboard_helper` | `App\Services\AdminDashboardService` | — |
| `views/admin/layouts/main` | `resources/views/layouts/admin/app.blade.php` | header, sidebar, flash |
| Login/logout B3 | không viết lại | `GET/POST /admin/login`, `GET/POST /admin/logout` |

### Ba mức quyền (hằng CI3, không đổi nghĩa)

| Level | Hằng | Ý nghĩa CI3 |
|------:|------|-------------|
| 0 | `ROLE_ADMIN` | Toàn quyền, kể cả voucher / nhân viên / xóa |
| 1 | `ROLE_MOD` | Staff: đơn, sản phẩm, kho nhập, AI. Không voucher, không quản lý nhân viên |
| 2 | `ROLE_USER` | Panel + chat + xem kho. Không doanh thu, không đơn, không menu staff |

`order.manage` = staff (0 hoặc 1). `order.chat`, `panel.home`, `inventory.view`, `stock.view` = cả 3 mức. `voucher.manage` và `staff.manage` = admin. Middleware chỉ bọc route mà controller CI3 thực sự gọi `admin_can`. `users`, `slider`, `posts`, `store`, `inventory`, `receipts`… chỉ cần đăng nhập admin — đúng CI3 (menu ẩn với User, URL vẫn vào được). Module chưa migrate vẫn `abort(501)` sau khi qua quyền.

### Metric dashboard

Kỳ: `today`, `7d`, `30d` (mặc định), `month`, `year`, `custom`. So sánh kỳ trước cùng độ dài.

- Đơn / doanh thu (status 3) / chờ (0+1) / đang giao (2) / hoàn thành / hủy
- Khách mới (`user.created`)
- Sắp hết (`product.quantity` ≤ 10), giá trị nhập (`stock_receipts` confirmed), giá trị tồn (`stock * cost_price`), COGS, lợi nhuận gộp
- Biểu đồ doanh thu, top sản phẩm, đơn chờ (status 0 hoặc 1, không lọc kỳ), top khách, top danh mục, sắp hết, đánh giá đã duyệt, thông báo

Một lần đọc `transaction` trong cửa sổ kỳ + kỳ trước, rồi gom KPI/biểu đồ trong PHP. Doanh thu COGS dùng lại bucket đó (không `SUM` lần hai).

### Kiểm thử (MySQL `webshop`, `DatabaseTransactions`)

```
php artisan test tests/Feature/Admin                                          →  8 passed (56 assertions)
php artisan test tests/Feature/Admin tests/Feature/Account tests/Feature/Cart tests/Feature/Order
                                                                              →  86 passed (636 assertions)
```

Sau lần gộp, `AdminDashboardService` bỏ `SUM` doanh thu trùng; chạy lại `tests/Feature/Admin` vẫn 8 passed (56 assertions).

Phủ: guest và customer bị chặn `/admin` · login + mirror `session.login` không có password · session.login giả không qua guard · User bị chặn products/voucher/orders và không thấy doanh thu · Mod bị chặn voucher, qua được orders (501 vì B10) · Admin qua voucher/products (501 vì B9/B12) · `?level=0` không nâng quyền · doanh thu hôm nay cộng đơn status 3 · logout xóa guard và giữ `custom_cart` · POST login thiếu CSRF → 419.

`/admin` từng bị route catalog `{slug}` bắt trước (404). `where` của `storefront.catalog.seo` loại trừ slug `admin`.

Cleanup: 0 admin `b8admin.*@example.test`, 0 user `b8*@example.test`, 0 đơn `b8buyer@example.test`. `user_saved_cart` user 10 = 335 byte, user 11 = 2 byte. JM10 used_count=0 is_active=1, JM20 used_count=2 is_active=1. Bảng `admin` là InnoDB.

**NEXT STEP:** B9 Admin Product + Catalog. Không triển khai trong task B8.

---

## B9 — Admin Product + Catalog (`DONE_WITH_ISSUES`)

Nguồn CI3: `controllers/admin/Product.php`, `controllers/admin/Catalog.php`, `libraries/Product_service.php` (`sync_variants`), `libraries/Upload_library.php`, `helpers/product_helper.php`, view `admin/products/*` và `admin/catalog/*`.

Không có cột slug. URL storefront vẫn `{tên}-p{id}` / `{tên}-c{id}` từ B4. Tên trùng được phép.

### Route (giữ URL CI3 `admin_url`)

| Method | URL | Việc |
|--------|-----|------|
| GET | `/admin/products`, `/admin/products/index`, `/admin/products/index/{offset}` | Danh sách, 10 dòng/trang, lọc `name` / `catalog_id` / `status` |
| GET, POST | `/admin/products/add` | Tạo |
| GET, POST | `/admin/products/edit/{id}` | Sửa |
| POST | `/admin/products/del` | Xóa một (AJAX JSON). GET → 404 |
| POST | `/admin/products/bulk_del` | Xóa nhiều. GET → 404 |
| GET | `/admin/catalog`, `/admin/catalog?q=` | Danh sách (thêm lọc tên phía server) |
| GET, POST | `/admin/catalog/add`, `/admin/catalog/edit/{id}` | Tạo / sửa |
| POST | `/admin/catalog/del/{id}` | Xóa. GET → 404 |

Middleware nhóm: `admin.session`, `admin.auth`, `product.manage` hoặc `catalog.manage`. Xóa sản phẩm (`product.delete_single`, `product.bulk_delete`) và xóa danh mục (`catalog.delete`) chỉ level 0, kiểm tra trong action. Thiếu quyền khu vực: flash `Bạn không có quyền truy cập khu vực này!` rồi về `/admin`.

### Hành vi

- Danh mục cây `parent_id`. Form cha: gốc (0), Thời trang (1), và catalog có `parent_id` < 2. Không cho chọn chính nó hoặc con làm cha.
- Sản phẩm gán danh mục lá trong nhóm `parent_id = 1`. Giá nguyên, giảm giá là % (0–100) lưu `discount` (số tiền), `discount_type=percent`, `discount_percent`.
- Tạo sản phẩm: mã `SP` + id 5 chữ số, tồn 0, status 1. Biến thể = màu × size, SKU, giá theo giá gốc, `stock` 0, `min_stock` từ `config/stock.php`. Bỏ màu/size thì biến thể cũ chuyển `status` 0, không xóa tồn.
- Sửa biến thể: giá, giá vốn, tồn tối thiểu, bán/ẩn. Không sửa `stock` (phiếu nhập là B11). Biến thể của sản phẩm khác bị bỏ qua.
- Ảnh: `upload/product`, JPG/PNG/GIF/WEBP/BMP, tối đa 5MB, tên file do server sinh, không ghi đè. Xóa file sau khi DB thành công và chỉ khi không sản phẩm nào khác còn dùng.
- Xóa sản phẩm còn xóa dòng `order`, `product_review`, `product_variants` — đúng CI3.
- `product-reviews`, đơn, voucher, kho vẫn 501.

### File

`ProductAdminService`, `ProductImageStore`, `ProductController`, `CatalogController`, FormRequest `Store/UpdateProductRequest`, `Store/UpdateCatalogRequest`, view `admin/products/*`, `admin/catalog/*`.

### Kiểm thử (MySQL `webshop`, `DatabaseTransactions`)

```
php artisan test tests/Feature/Admin/ProductCatalogTest.php   →  7 passed (119 assertions)
php artisan test tests/Feature/Admin tests/Feature/Account tests/Feature/Cart tests/Feature/Order
                                                              →  93 passed (756 assertions)
```

Cleanup sau test: 0 dòng tên `B9 %`, product 13, catalog 22, variant 86 (đúng số trước B9). `user_saved_cart` user 10 = 335 byte, user 11 = 2 byte. JM10 used_count=0, JM20 used_count=2.

**NEXT STEP:** B10 Admin Order + Customer. Không triển khai trong task B9.

---

## B10 — Admin Order + Customer (`DONE_WITH_ISSUES`)

Nguồn CI3: `controllers/admin/Transaction.php`, `controllers/admin/User.php`, `models/Transaction_model.php`, `models/Loyalty_model.php` (`credit_order_completed`), view `admin/transaction/*` và `admin/user/*`. Route `admin/orders/(:num)` là offset phân trang, không phải mã đơn. Chi tiết là `/admin/orders/detail/{id}`.

Không có quyền `customer.manage`. CI3 `User` không gọi `admin_can`. Mọi admin đã đăng nhập (kể cả level 2) mở được khách hàng. Đơn nằm trong `order.manage` (level 0 và 1).

### Trạng thái đơn (đúng code CI3, không theo docblock “đã đóng gói”)

| Status | Nghĩa trên admin | Chuyển được |
|--------|------------------|-------------|
| 0 | Chờ xử lý | `accept` → 1. Hủy được. Xóa dòng thì hoàn kho và trừ `amount` |
| 1 | Đã xác nhận | `ship` → 2. `complete` → 3 (nút UI chỉ hiện ở status 2, server vẫn cho từ 1). Hủy được |
| 2 | Đang giao | `complete` → 3. Không hủy |
| 3 | Thành công | Không hủy. Cộng `product.buyed` và tích điểm |
| 4 | Đã hủy / Hoàn | Không hoàn thành lại |

`accept` / `ship` / `complete` là GET (link CI3). `cancel`, `del`, `deldetail` là POST; GET → 404. `del` không xóa đơn, chỉ flash yêu cầu hủy. Hủy status 0/1 hoàn kho (`InventoryService::restoreForOrder`) và gọi `LoyaltyService::onOrderCancelled` (nhả voucher nếu có). `reverse_buyed` luôn false vì status 3 không hủy được. Giá dòng là `order.amount` đã lưu.

### Route

| Method | URL | Việc |
|--------|-----|------|
| GET | `/admin/orders`, `/admin/orders/{offset}` | Danh sách, 10 dòng/trang. Lọc `q` (tên hoặc SĐT), `status` 0–4, `payment` cod/transfer, `date_from` / `date_to` |
| GET | `/admin/orders/detail/{id}` | Chi tiết snapshot |
| GET | `/admin/orders/accept/{id}`, `ship/{id}`, `complete/{id}` | Đổi trạng thái nếu hợp lệ |
| POST | `/admin/orders/cancel/{id}` | Hủy, field `reason` |
| POST | `/admin/orders/del` | Từ chối xóa |
| POST | `/admin/orders/deldetail/{lineId}` | Xóa một dòng |
| GET | `/admin/orders/pending_count` | JSON `{ok, count}` status 0, 1, 2 |
| GET | `/admin/orders/chat` | Redirect `/admin/support-chat` (vẫn 501, B15) |
| GET | `/admin/users`, `/admin/users?q=` | Danh sách. `q` lọc tên, email, SĐT, địa chỉ. Rỗng = cả bảng, đúng CI3 không phân trang |
| GET | `/admin/users/view/{id}`, `order/{id}`, `detail/{orderId}` | Hồ sơ, đơn của khách, chi tiết chỉ xem |
| POST | `/admin/users/del/{id}` | Xóa user, không xóa đơn |
| POST | `/admin/users/deldetail/{lineId}` | Xóa dòng như CI3 `User::deldetail`: không hoàn kho, không trừ tổng |

Middleware đơn: `admin.session`, `admin.auth`, `admin.permission:order.manage`. Khách hàng chỉ `admin.session` + `admin.auth`. Menu Đơn ẩn với level 2 (`order.manage`). Menu Khách ẩn với level 2 vì `admin_sidebar_allowed` CI3 xếp `users` vào nhóm staff — URL vẫn mở được.

### File

`OrderAdminService`, `LoyaltyService::creditOrderCompleted`, `Admin\TransactionController`, `Admin\UserController`, view `admin/orders/*`, `admin/users/*`. Layout vẫn `layouts.admin.app`. Không FormRequest: CI3 không `form_validation` các action này; rule chuyển trạng thái nằm trong service. `/admin/voucher`, `/admin/report`, `/admin/product-reviews`, kho, AI vẫn 501.

### Kiểm thử (MySQL `webshop`, `DatabaseTransactions`)

```
php artisan test tests/Feature/Admin/OrderCustomerTest.php   →  4 passed (80 assertions)
php artisan test tests/Feature/Admin tests/Feature/Account tests/Feature/Cart tests/Feature/Order
                                                             →  97 passed (837 assertions)
```

Cleanup sau test: 0 user `b10*@example.test`, 0 sản phẩm `B10 %`, 0 đơn email `b10*`. Product 13, catalog 22, variant 86. `user_saved_cart` user 10 = 335 byte, user 11 = 2 byte. JM10 used_count=0 is_active=1, JM20 used_count=2 is_active=1.

**NEXT STEP:** B11 Inventory + Supplier. Không triển khai trong task B10.

---

## B11 — Admin Inventory + Supplier + Receipt (`DONE_WITH_ISSUES`)

### Stock source of truth

`product_variants.stock` là tồn thật. `product.quantity` là cache = tổng `stock` của biến thể `status = 1` (`sync_product_quantity_cache`). `stock_movements` là nhật ký (before/after), không dùng để tính lại tồn.

| Việc | Tồn | Movement |
|------|-----|----------|
| Nhập kho (tạo phiếu hoặc xác nhận nháp) | `stock += qty`, `cost_price` = giá nhập nếu > 0, rồi sync `product.quantity` | `in`, `reference_type=receipt` |
| Bán (checkout B6) | `UPDATE stock = stock - qty WHERE stock >= qty`, sync quantity sau commit | `out`, `reference_type=order` |
| Hủy đơn status 0/1 (B10, cùng `InventoryService::restoreForOrder`) | `stock += qty`, sync quantity | `in`, `reference_type=order` |
| Kiểm kê | đặt `stock` = số mới, delta ≠ 0, sync quantity | `adjust`, `reference_type=adjustment` |
| Sửa phiếu nháp | không đổi tồn | không |
| Hủy phiếu nháp | không đổi tồn | không |
| Hủy / xóa phiếu đã nhập | CI3 không cho. Không hoàn kho | không |

Sắp hết trên màn tồn kho: `stock > 0` và `stock <= min_stock` (mặc định biến thể mới = 5). Dashboard B8 vẫn đếm `product.quantity <= 10`, đúng helper dashboard CI3, không đổi.

### Route

Middleware nhóm: `admin.session`, `admin.auth`. Quyền kiểm trong action, cùng CI3 (không bọc `admin.permission` một mức vì level 2 vẫn được xem).

| Method | URL | Việc | Quyền |
|--------|-----|------|--------|
| GET | `/admin/inventory`, `/admin/inventory/index/{offset}` | Danh sách biến thể, 20 dòng. Lọc tên, SKU, danh mục, `stock` out/low/ok | `inventory.view` (0, 1, 2) |
| GET | `/admin/inventory/low-stock` | Cùng danh sách với `stock=low` | `inventory.view` |
| GET | `/admin/inventory/adjust/{id}` | Form kiểm kê | `inventory.count` (0, 1) |
| POST | `/admin/inventory/adjust/{id}` | Đặt tồn mới | `inventory.adjust` (chỉ 0) |
| GET | `/admin/stock-movements`, `/admin/stock-movements/index/{offset}` | Nhật ký, 25 dòng. Lọc SKU, type in/out/adjust | `stock.view` |
| GET | `/admin/suppliers`, `/admin/suppliers/add` | Danh sách / form. Không sửa, không xóa | xem: `stock.view`; thêm: `stock.manage` (0, 1) |
| POST | `/admin/suppliers/add` | Tạo, status = 1 | `stock.manage` |
| GET | `/admin/receipts`, `/admin/stock-receipts` | Danh sách phiếu. Lọc status, mã. Không phân trang | `stock.view` |
| GET, POST | `/admin/stock-receipts/add` | Tạo phiếu đã nhập và cộng tồn ngay | `stock.receipt` (0, 1) |
| GET, POST | `/admin/stock-receipts/edit/{id}` | Chỉ nháp | `stock.receipt` |
| GET | `/admin/stock-receipts/view/{id}` | Chi tiết | `stock.view` |
| POST | `/admin/stock-receipts/confirm`, `/cancel` | Xác nhận nháp (cộng tồn) / hủy nháp (không đổi tồn). GET → về danh sách | `stock.receipt` |
| GET | `/admin/receipts/search_variants`, `filter_products`, `filter_options`, `recent_variants` | JSON chọn biến thể | `stock.view` hoặc `stock.receipt` hoặc `stock.manage` |

`/admin/stock-inventory` mở cùng tồn kho. Alias `stock-receipts` mở cùng phiếu nhập.

### File

`InventoryService` thêm `adjustStock` và `receiveStock` (checkout/hủy đơn vẫn gọi `deductForOrder` / `restoreForOrder`). `StockReceiptService`. FormRequest `AdjustStockRequest`, `StoreSupplierRequest`, `StoreReceiptRequest`, `UpdateReceiptRequest`. Controller `InventoryController`, `StockReceiptController`, `SuppliersController`, `StockMovementsController`. View `admin/inventory/*`, `admin/receipts/*`, `admin/suppliers/*`, `admin/movements/index`.

### Kiểm thử (MySQL `webshop`, `DatabaseTransactions`)

```
php artisan test tests/Feature/Admin/InventoryTest.php   →  5 passed (106 assertions)
php artisan test tests/Feature/Admin tests/Feature/Account tests/Feature/Cart tests/Feature/Order
                                                         →  102 passed (943 assertions)
```

Cleanup: 0 sản phẩm `B11 %`, 0 NCC mã `B11%`, 0 phiếu `B11%`, 0 user `b11*`. Product 13, catalog 22, variant 86. Giỏ user 10 = 335 byte, user 11 = 2 byte. JM10 used_count=0, JM20 used_count=2.

**NEXT STEP:** B12 Voucher. Không triển khai trong task B11.

---

## B12 — Admin Voucher (`DONE_WITH_ISSUES`)

Không có service voucher thứ hai. Admin ghi bảng `voucher`. Checkout và hủy đơn vẫn gọi `VoucherService` (`validateForCheckout`, `recordUse`, `releaseForTransaction`). Số tiền giảm là `Loyalty::voucherDiscount`: phần trăm làm tròn rồi cắt `max_discount` nếu > 0; số tiền cố định không dùng `max_discount`; không vượt quá tổng giỏ. Tiền là số nguyên.

### Vòng đời

- Mã: `trim`, xóa khoảng trắng, viết hoa. `abc` và `ABC` là một mã.
- `usage_limit = 0` không giới hạn. `per_user_limit` tối thiểu 1 (CI3 `max(1, …)`).
- `used_count` tăng khi tạo đơn (cùng transaction với đơn), không tăng lúc bấm áp mã.
- Hủy đơn xóa `voucher_use` và giảm `used_count` một lần. Hủy lần hai không giảm tiếp.
- Hết hạn tính bằng `valid_to` (cuối ngày 23:59:59), không đổi `is_active`. Tắt mã là `is_active = 0`.
- Sửa được cả mã đã dùng. Sửa không đụng `used_count`. Xóa là xóa dòng voucher, không xóa đơn.
- Cần đăng nhập. Có đơn tối thiểu, hạng tối thiểu, khách riêng (`user_id > 0`), lượt toàn hệ thống và lượt mỗi khách.

### Route

Nhóm `admin.permission:voucher.manage` — chỉ level 0.

| Method | URL | Việc |
|--------|-----|------|
| GET | `/admin/voucher` | Danh sách, mới nhất trước. Không lọc, không phân trang |
| GET, POST | `/admin/voucher/add` | Tạo |
| GET, POST | `/admin/voucher/edit/{id}` | Sửa |
| POST | `/admin/voucher/delete/{id}` | Xóa. GET → 404 |
| GET, POST | `/admin/voucher/toggle_active/{id}` | Đảo `is_active`. AJAX trả `{ok, is_active, label}` |

Checkout giữ `POST /thanh-toan/apply_voucher`.

### File

`VoucherController`, `StoreVoucherRequest`, `UpdateVoucherRequest`, view `admin/voucher/*`. Model `Voucher` / `VoucherUse` và `VoucherService` giữ nguyên từ B6.

### Kiểm thử (MySQL `webshop`, `DatabaseTransactions`)

```
php artisan test tests/Feature/Admin/VoucherTest.php   →  5 passed (63 assertions)
php artisan test tests/Feature/Admin tests/Feature/Account tests/Feature/Cart tests/Feature/Order
                                                       →  107 passed (1007 assertions)
```

Cleanup: 0 mã `B12%`, 0 user `b12*`, 0 sản phẩm `B12`. Product 13, catalog 22, variant 86. Giỏ user 10 = 335 byte, user 11 = 2 byte. JM10 used_count=0 is_active=1, JM20 used_count=2 is_active=1.

**NEXT STEP:** B13 Reports + Export. Không triển khai trong task B12.

---

## B13 — Admin Reports + Export (`DONE_WITH_ISSUES`)

Chỉ port những gì CI3 có: một trang `/admin/report` và xuất file theo module. Không thêm báo cáo lợi nhuận, CRM, hay báo cáo voucher riêng. Dashboard, báo cáo và file export không có ba công thức doanh thu.

### Công thức

Trang báo cáo và export `revenue` / `top_products` chỉ tính đơn `transaction.status = 3`. Doanh thu là `SUM(transaction.amount)` — số đã lưu trên đơn, đã gồm giảm giá voucher, không lấy giá sản phẩm hiện tại. Đơn hủy (4) và đơn chưa hoàn thành (0, 1, 2) không tính. Không có cột phí ship để cộng thêm.

Kỳ: `type=day|week|month|year` thì bỏ qua `date_from` / `date_to`. Không có `type` thì lọc theo ngày. Đầu ngày `00:00:00`, cuối ngày `23:59:59`. Ngày không parse được thì bỏ mốc đó, giống CI3.

Số lượng bán là `SUM(order.qty)` của các đơn đó. Cột giá trên bảng sản phẩm là `amount/qty` của một dòng hoàn thành (`LIMIT 1`, CI3 không `ORDER BY`), không có dòng thì dùng `product.price`. Cột này không phải doanh thu. Nhãn tồn trên trang này dùng `product.quantity`: `<= 0` hết, `<= 5` sắp hết, còn lại an toàn. Không dùng `min_stock` của biến thể.

Mười hai tháng trên trang là doanh thu status 3 của năm hiện tại. Kỳ đang lọc không áp vào chuỗi tháng này. Đúng CI3.

Export dashboard gọi `AdminDashboardService` (doanh thu status 3, COGS = số lượng × `order.cost_price` trên đơn hoàn thành, lợi nhuận gộp = doanh thu − COGS, giá trị tồn = tồn × giá vốn biến thể). Không viết lại công thức đó.

Export đơn dùng `OrderAdminService::rowsForExport` (cùng lọc tên/SĐT, status, thanh toán, ngày). Export tồn kho dùng `InventoryController::rowsForExport` (cùng lọc, tồn lấy từ `product_variants.stock`). Export voucher đọc cột `used_count`, không `COUNT(voucher_use)`.

### Route và quyền

| Method | URL | Việc |
|--------|-----|------|
| GET | `/admin/report` | Báo cáo. `order.manage`: level 0 và 1. Level 2 về `/admin` |
| GET | `/admin/export/excel/{module}` | Excel. Chỉ level 0. Module lạ → 404 |
| GET | `/admin/export/pdf/{module}` | PDF. Chỉ level 0 |
| GET | `/admin/export/preview/{module}` | HTML xem trước. Chỉ level 0 |

Module: `orders`, `products`, `customers`, `inventory`, `voucher`, `revenue`, `top_products`, `dashboard`. Nút xuất nằm trên dashboard, đơn, sản phẩm, tồn kho, khách, voucher và báo cáo. Level 1 và 2 không thấy nút; gọi URL vẫn 403. `?level=0` và `session.login` giả không đổi quyền. GET giống CI3. POST thiếu CSRF → 419. Tên file do server đặt. Không nhận path từ trình duyệt.

`orders`, `products`, `inventory` có `scope=page` với `offset` / `per_page` (10, 10, 20). Mặc định `scope=all`.

### Gói và hiệu năng

`phpoffice/phpspreadsheet` 5.10.0 và `mpdf/mpdf` 8.2.6. CI3 dùng PhpSpreadsheet 1.30; bản đó bị chặn vì advisory, nên Laravel dùng dòng 5.10. Xuất đồng bộ, không queue — CI3 cũng xuất ngay và dữ liệu hiện tại nhỏ. Trang báo cáo: một câu SQL sản phẩm (subquery, không lặp theo từng sản phẩm) và 12 câu tổng tháng. Test chặn quá 40 query và đúng một câu có `da_ban`.

### Kiểm thử (MySQL `webshop`, `DatabaseTransactions`)

```
php artisan test tests/Feature/Admin/ReportTest.php tests/Feature/Admin/ReportExportTest.php
                                                       →  9 passed (100 assertions)
php artisan test tests/Feature/Admin tests/Feature/Account tests/Feature/Cart tests/Feature/Order
                                                       →  116 passed (1107 assertions)
```

Cleanup: không còn đơn `B13-%`. Product 13, catalog 22, variant 86. Giỏ user 10 = 335 byte, user 11 = 2 byte. JM10 used_count=0 is_active=1, JM20 used_count=2 is_active=1.

**NEXT STEP:** B14 Product Reviews / AI theo roadmap hiện tại. Không triển khai trong task B13.

---

## B14 — Product Reviews + Rating (`DONE_WITH_ISSUES`)

Giữ `POST /product/raty` và `POST /product/raty-undo`. Không thêm route thứ hai. Không bắt khách đã mua hàng — CI3 không kiểm tra đơn.

### Quy tắc

- Sao là số nguyên 1–5 sau `round`. 0, âm, > 5, chuỗi đều bị từ chối.
- Nội dung bắt buộc, tối đa 2000 ký tự.
- Khách được đánh giá. Người đã đăng nhập lấy từ guard `web`, không lấy `user_id` trong POST và không lấy `session.user`.
- Một user một review cho một sản phẩm. Khách thì một `session_token` một review. Gửi lại là sửa dòng cũ và đưa về chờ duyệt.
- Cùng sao, cùng nội dung, đang chờ thì không ghi lại.
- `raty-undo` xóa đúng review của người đang đăng nhập hoặc của session khách. Không xóa review người khác.
- Danh sách công khai và điểm trung bình chỉ tính `status = 1`. Review mới là `status = 0`.
- Điểm lưu `product.rate_count` / `product.rate_total` = COUNT và SUM sao đã duyệt. Trang chi tiết chỉ ghi đè hai số này trên bộ nhớ khi có review đã duyệt; không ghi DB khi xem trang.
- Trung bình hiển thị `round(rate_total / rate_count, 1)`.
- Không phân trang. Trang sản phẩm lấy 50 review mới nhất. Admin lấy 100.

### Admin

`product.manage`: level 0 và 1. Level 2 bị chặn.

| Method | URL | Việc |
|--------|-----|------|
| GET | `/admin/product-reviews` | Danh sách. Mặc định chờ duyệt. Lọc `q`, `status` |
| POST | `/admin/product-reviews/approve/{id}` | Duyệt, tính lại điểm |
| POST | `/admin/product-reviews/reject/{id}` | Từ chối, tính lại điểm |
| POST | `/admin/product-reviews/delete/{id}` | Xóa, tính lại điểm |

GET các action trên trả 404. POST thiếu CSRF trả 419.

### File

`ReviewService`, `ProductController::raty` / `ratyUndo`, `Admin\ProductReviewController`, view `admin/product-reviews/index`. Model `ProductReview` giữ nguyên. Nội dung review escape khi in.

### Kiểm thử (MySQL `webshop`, `DatabaseTransactions`)

```
php artisan test tests/Feature/Storefront/ReviewTest.php tests/Feature/Admin/ReviewTest.php
                                                       →  9 passed (90 assertions)
php artisan test tests/Feature/Admin tests/Feature/Account tests/Feature/Cart tests/Feature/Order tests/Feature/Storefront
                                                       →  125 passed (1197 assertions)
```

Cleanup: 0 review `B14-%`, 0 user `b14review`. Product 13, catalog 22, variant 86. Giỏ user 10 = 335 byte, user 11 = 2 byte. JM10 used_count=0, JM20 used_count=2.

**NEXT STEP:** B15 AI / Chat theo roadmap. Không triển khai trong task B14.

## B15 — AI / Chat (`DONE_WITH_ISSUES`)

Provider CI3 là Gemini (`gemini-3.1-flash-lite`, `generateContent`). Key CI3 nằm trong `application/config/ai.php`. Laravel không copy key đó. Key chỉ đọc `GEMINI_API_KEY` từ `.env`, gửi bằng header `x-goog-api-key`. Browser không gọi Gemini.

Chat storefront: `POST /ai-assistant/send`, `GET /ai-assistant/history`, `GET /ai-assistant/poll`. Bắt đăng nhập guard `web`. Khách và `session.user` giả không tạo hội thoại. Một user không đọc hội thoại user khác: history/poll trả `messages: []`. Đơn trong prompt chỉ của `Auth::id()`. Không chọn `cost_price`.

FAQ, chuyển nhân viên, đổi size và AI tắt không gọi Gemini. Câu còn lại gọi đúng một lần, timeout 10 giây, không retry 401/403/429/5xx. Rate limit giữ 8 tin / 30 giây. Tin nhắn tối đa 500 ký tự (widget CI3 đã giới hạn, server CI3 thì chưa).

Admin: `/admin/ai-assistant` quyền `ai.manage` (level 0 và 1). `/admin/support-chat` quyền `order.chat` (level 0, 1 và 2). Xóa toàn bộ lịch sử chỉ level 0. Form cấu hình không lưu API key. GET take/end/delete/purge trả 404. POST thiếu CSRF trả 419.

Bảng giữ nguyên: `ai_conversation`, `ai_message`, `ai_faq`, `ai_setting` (MyISAM, test hoàn lại cờ bằng tay). Không streaming, không upload, không queue, không bảng mới.

### Kiểm thử (MySQL `webshop`, `Http::fake`, không gọi API thật)

```
php artisan test tests/Feature/AI
                                                       →  9 passed (91 assertions)
php artisan test tests/Feature/AI tests/Feature/Admin tests/Feature/Account tests/Feature/Cart tests/Feature/Order tests/Feature/Storefront
                                                       →  134 passed (1288 assertions)
```

Cleanup: 0 user `b15chat`, 0 FAQ `B15-%`, 0 tin `zzb15`. Product 13, catalog 22, variant 86. Giỏ user 10 = 335 byte, user 11 = 2 byte. JM10 used_count=0, JM20 used_count=2. Welcome AI trả về câu cũ.

**NEXT STEP:** B16 theo roadmap sau khi xác nhận B15. Không triển khai trong task B15.

## B16 — Admin nhân viên (`DONE_WITH_ISSUES`)

Audit: `docs/B16-AUDIT.md`. Sau B15 còn 501 ở trang nội dung và nhân viên. Target là quản lý tài khoản admin vì menu `/admin/admin` đang sống và đây là chỗ ghi `level`.

CI3 `admin/Admin.php`: danh sách, thêm, sửa, xóa. Quyền `staff.manage` = level 0. Không xóa tài khoản level 0. Mật khẩu bcrypt, tối thiểu 8 ký tự, có chữ và số. Email unique `uniq_admin_email`. Logout giữ nguyên như B3.

Level ngoài 0/1/2 bị từ chối. CI3 ghi thẳng số post lên. Đây là chỗ siết thêm, ghi ISSUE-B16-01.

### Kiểm thử

```
php artisan test tests/Feature/Admin/StaffTest.php
                                                       →  3 passed (44 assertions)
php artisan test tests/Feature/Admin tests/Feature/Account tests/Feature/Cart tests/Feature/Order tests/Feature/Storefront tests/Feature/AI
                                                       →  137 passed (1332 assertions)
```

Cleanup: 0 admin `b16staff`. Product 13, catalog 22, variant 86. Giỏ user 10 = 335 byte, user 11 = 2 byte. JM10 used_count=0, JM20 used_count=2.

**NEXT STEP:** Tin tức (storefront + admin) theo `docs/B16-AUDIT.md`. Không triển khai trong task B16.

## B17 — Tin tức (`DONE_WITH_ISSUES`)

Audit: `docs/B17-AUDIT.md`. Mapping: `docs/B17-NEWS-MAPPING.md`.

Nguồn là `content_pages` type `news`, không phải bảng `news`. `/tin-tuc` lấy 8 bài, offset trên URL là OFFSET SQL. Chi tiết `/tin-tuc/{slug}-n{id}`. Sai id về trang chủ. `/tintuc` liệt kê mọi bài. `/posts` và `/admin/news` chuyển 301.

Admin thêm/sửa/xóa tại `/admin/posts`. CI3 không gọi `admin_can`, nên level 2 đăng nhập vẫn vào được. Ảnh tối đa 5MB, tên do server đặt. Không lưu tên file người dùng gửi.

`abort(501)`: 56 trước B17, 47 sau B17. Đã gỡ stub tin tức storefront, admin và `/tintuc`.

### Kiểm thử

```
php artisan test tests/Feature/Storefront/NewsTest.php tests/Feature/Admin/NewsAdminTest.php
                                                       →  5 passed (56 assertions)
php artisan test tests/Feature/Admin tests/Feature/Account tests/Feature/Cart tests/Feature/Order tests/Feature/Storefront tests/Feature/AI
                                                       →  142 passed (1388 assertions)
```

**NEXT STEP:** Trang tĩnh / giới thiệu theo `docs/B16-AUDIT.md`. Không triển khai trong task B17.

## B17.1 — Priority cleanup (`DONE_WITH_ISSUES`)

Báo cáo: `docs/B17-1-PRIORITY-CLEANUP.md`. Audit gốc: `docs/B17-FULL-AUDIT.md`.

Đã làm: bỏ literal Gemini key khỏi `application/config/ai.php` (đọc biến môi trường); 301 URL sản phẩm/danh mục cũ; `HtmlSanitizer` cho mô tả sản phẩm, tin và giới thiệu; migrate `GET /gioi-thieu`; xóa `welcome.blade.php` và `app/Policies/AdminPermission.php`. Không tách `ProductController` hay `ReportService`. Banner và slider admin để B18. Giữ `app/Models/Store.php`.

`abort(501)`: 47 trước B17.1, 44 sau.

### Kiểm thử

```
php artisan test tests/Feature/Storefront/PriorityCleanupTest.php
                                                       →  4 passed (35 assertions)
php artisan test tests/Feature/Admin tests/Feature/Account tests/Feature/Cart tests/Feature/Order tests/Feature/Storefront tests/Feature/AI
                                                       →  146 passed (1423 assertions)
```

Sau suite: product 13, catalog 22, variant 86, news 6, giỏ user 10 = 335 byte, user 11 = 2 byte, JM10 used_count=0 is_active=1, JM20 used_count=2 is_active=1. Không còn bài test `B171 tin`.

**NEXT STEP:** B18 — admin banner và slider. Không triển khai trong task B17.1.
