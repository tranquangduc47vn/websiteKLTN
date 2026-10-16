# B17 FULL PROJECT AUDIT

Ngày: 2026-10-16. Chỉ đọc code và đếm file. Không sửa route, controller, model, view, database.

Phạm vi: `D:\webshop\laravel` (Laravel 12, PHP 8.2) và phần CI3 còn sống ở `D:\webshop` khi Laravel còn phụ thuộc URL, session hoặc file dùng chung.

`docs/MIGRATION-PROGRESS.md` đã dùng chữ B17 cho module tin tức. Báo cáo này là audit toàn project, không đổi trạng thái tin tức.

## Cập nhật sau B17.1

Ảnh chụp bên dưới giữ nguyên số liệu lúc audit. Phần đã xử lý:

- `application/config/ai.php` không còn literal key. File đọc `getenv('GEMINI_API_KEY')`. File này nằm trong `.gitignore` và không có trong git. Vẫn nên ROTATE key vì giá trị từng nằm plaintext trên đĩa.
- `/product/view/{id}`, `/product/detail/{id}`, `/product/catalog/{id}`, `{slug}-c{id}`, `/product/search` trả 301 tới URL SEO. Sản phẩm hoặc danh mục không có thì về trang chủ, không loop.
- Mô tả sản phẩm, nội dung tin và trang giới thiệu đi qua `App\Support\HtmlSanitizer` khi hiển thị và khi admin lưu. Không viết lại hàng cũ trong database.
- `GET /gioi-thieu` trả 200 từ `content_pages` type `page`.
- Banner và slider admin vẫn 501. Để B18.
- Đã xóa `welcome.blade.php` và `app/Policies/AdminPermission.php`. Giữ `app/Models/Store.php`.
- `abort(501)`: 47 lúc audit, 44 sau B17.1, 15 controller. `GioithieuController` không còn stub.

## 1. Project Overview

Luồng đang chạy:

`routes/web.php` → middleware `web` (`SyncCartSession`) hoặc `admin.auth` / `admin.permission` → controller → Form Request (một phần) → service → Eloquent / query builder → MySQL `webshop`.

Schema thật không nằm trong `database/migrations`. Các migration Laravel mặc định vẫn còn trong repo.

Module bán hàng (auth, home, sản phẩm, giỏ, thanh toán, đơn, tài khoản, admin, kho, voucher, báo cáo, đánh giá, AI, nhân viên, tin tức) đã có code thật và test MySQL. Phần còn `abort(501)` là trang nội dung và một số URL legacy sản phẩm.

Không có P0 trong code đang phục vụ checkout. Có một rủi ro vận hành nếu ai chạy `php artisan migrate` trên database `webshop`.

## 2. File Statistics

Đếm file `.php` (Blade tính file kết thúc bằng `.php`).

| Nhóm | File | Dòng |
|------|------|------|
| Controllers | 42 | 5217 |
| Models | 31 | 2407 |
| Services | 24 | 6533 |
| Middleware | 6 | 209 |
| Form Requests | 21 | 879 |
| Support | 17 | 1721 |
| Policies | 1 | 34 |
| Rules | 3 | 97 |
| View composers | 2 | 182 |
| Blade | 76 | 5898 |
| Tests | 31 | 4958 |
| Config | 13 | 1263 |
| Routes | 2 (`web.php`, `console.php`) | 638 |
| database/ | 5 | 216 |

Không có `routes/api.php`. Không có Job, Event, Listener, Mailable, Notification.

`Route::` trong `routes/web.php`: 100 chỗ khai báo.

JS/CSS storefront nằm ở `D:\webshop\assets\site`, không nằm trong `laravel/resources/js`. Đếm được 15 file `.js` và 17 file `.css` trong `assets/site`, `laravel/public`, `laravel/resources`.

## 3. Controller Audit

| Controller | Vấn đề | Mức | Đề xuất |
|------------|--------|-----|---------|
| `Storefront\ProductController.php` (790 dòng) | Danh sách, lọc giá, tìm kiếm, chi tiết, đánh giá, query catalog nằm trong controller | P2 / MEDIUM | Giữ URL. Tách query danh sách sang một service khi đụng lại module này |
| `Storefront\UserController.php` (592) | Nhiều action tài khoản trong một class. Logic chính đã ở `AccountService` | P3 | KEEP. Chỉ tách khi sửa account |
| `Storefront\CartController.php` (251) | Mỏng. Logic ở `CartSessionService` | — | KEEP |
| `Storefront\OrderController.php` | Checkout mỏng, logic ở `CheckoutService` | — | KEEP |
| `Storefront\HomeController.php` | `index` chạy thật. `handlePath` và `__call` `abort(501)` không có route | P3 | Method 501 là dead stub |
| `Storefront\LegacyController.php` | `cart`, `order`, `posts` đã 301. `catalog`, `product`, `productSearch`, `catalogC` không có method, rơi `__call` → 501 | P1 / MEDIUM | Viết 301 đúng URL SEO, hoặc ghi rõ URL cũ cố ý 501 |
| `Storefront\NewsController.php`, `TintucController.php` | Tin tức đã chạy. Validation nằm trong controller | P3 | Hợp lý vì form nhỏ |
| `Admin\NewsController.php` | CRUD chạy. Không Form Request. Level 2 vào được vì CI3 không gọi `admin_can` | P2 / MEDIUM | Giữ behavior. Không bọc `content.manage` nếu chưa quyết đổi quyền |
| `Admin\ProductController.php` | Mỏng, gọi `ProductAdminService` | — | KEEP |
| `Admin\TransactionController.php` | Mỏng, gọi `OrderAdminService` | — | KEEP |
| `Admin\UserController.php` | Query danh sách khách trong controller | P3 | Chấp nhận |
| `Admin\InventoryController.php` | `rowsForExport` nằm trên controller vì export B13 gọi lại đúng filter | P3 | KEEP cho đến khi gom export |
| `Admin\StockReceiptController.php` (485) | Dài. Rule phiếu nhập nằm ở `StockReceiptService` | P2 | SPLIT phần HTTP lặp `handlePath` khi sửa phiếu |
| `Admin\VoucherController.php` | Form Request + `VoucherService` | — | KEEP |
| `Admin\ReportController.php`, `ExportController.php` | Mỏng. Công thức ở `ReportService` | — | KEEP |
| `Admin\AiAssistantController.php`, `SupportChatController.php` | Gọi `ChatService` | — | KEEP |
| `Admin\AdminController.php` | Logout + CRUD nhân viên | — | KEEP |
| 8 controller admin stub | Banner, Slider, Pages, Store, Tuyendung, Hoptac, Vanchuyen, SiteBranding: mỗi file 3 `abort(501)` | P2 | Migrate từng module, không xóa stub khi URL còn trỏ tới |
| 6 controller storefront stub | Gioithieu, Hethongcuahang, Loyalty, Tuyendung, Hoptac, Vanchuyen | P2 | Cùng hướng |

`Admin\LoginController` có auth thật. Không thấy controller checkout riêng: checkout là `Storefront\OrderController`.

## 4. Model Audit

31 file, gồm `BaseModel` và `Concerns/LegacyTimestamps`.

| Model | Nhận xét |
|-------|----------|
| `Product`, `ProductVariant`, `Catalog` | Đang dùng. `Product` 253 dòng, có `imageList()` và scope `active`. Hợp lý |
| `Transaction`, `OrderItem` | Đơn hàng. Giá dòng là snapshot, không lấy lại giá hiện tại |
| `Voucher`, `VoucherUse` | `VoucherService` là chỗ tăng `used_count` |
| `StockMovement`, `StockReceipt`, `StockReceiptItem`, `Supplier` | Kho. `stock` trên variant là nguồn số lượng |
| `User`, `UserAddress`, `UserPhone`, `UserSavedCart` | Tài khoản và giỏ. `user_address` / `user_phone` là MyISAM |
| `ProductReview` | Đánh giá. Trung bình chỉ tính `status = 1` |
| `AiConversation`, `AiMessage`, `AiSetting`, `AiFaq` | Chat. `AiSetting` là MyISAM |
| `ContentPage` | Tin tức và trang tĩnh. Cast `updated_at` trong khi bảng không có cột đó. `scopeOfType`, `scopeBySlug`, `scopeLatest` không có chỗ gọi. `scopeLatest` xếp `created_at`, còn tin tức đang xếp `id` |
| `Banner`, `Slider` | Home đọc được. Admin sửa vẫn 501 |
| `Store` | Không có reference ngoài chính file model. Chờ module cửa hàng |
| `MasterColor`, `MasterSize` | `ProductController` và `ColorPalette` có đọc màu |
| `Admin` | Guard `admin` |
| `UserPointLog` | `LoyaltyService` ghi điểm. Trang `/tich-diem` vẫn 501 |
| `PasswordReset` | `PasswordResetService` dùng |

Không thấy relationship trùng gây query sai trong các model đang phục vụ đơn và kho. Nguy cơ N+1 chính nằm ở chỗ list không `with()`, không nằm ở relationship khai báo sai.

## 5. Service Audit

| Service | Loại | Ghi chú |
|---------|------|---------|
| `CartSessionService` (776) | A, cần giữ | Giỏ `custom_cart`. Không tách lúc này |
| `CheckoutService` (513) | A | Tạo đơn, voucher, trừ kho trong một transaction |
| `VoucherService` | A | Chỗ duy nhất tính giảm giá và `used_count` |
| `InventoryService` (276) | A | Trừ/cộng `product_variants.stock`, ghi `stock_movements` |
| `VariantStockService` | A | Resolve biến thể cho giỏ. Không trùng `InventoryService` |
| `LoyaltyService` | A | Điểm. Không tính tiền voucher |
| `AccountService` (442) | A | Hồ sơ, địa chỉ, điện thoại, lịch sử đơn |
| `ReviewService` (252) | A | Duyệt đánh giá và cập nhật `rate_*` |
| `AdminDashboardService` (524) | A | Doanh thu, COGS. Ngưỡng hết hàng dashboard là `quantity <= 10` |
| `ProductAdminService` (541) | A | CRUD sản phẩm, ảnh, variant |
| `OrderAdminService` (322) | A | Lọc đơn, trạng thái, export |
| `StockReceiptService` (424) | A | Phiếu nhập |
| `ReportService` (706) | A, nên tách sau | Nhiều module export trong một class |
| `ChatService` (595), `GeminiClient`, `FaqMatcher` | A | Một lần gọi Gemini. Key lấy từ config |
| `PasswordResetService`, `AuthMailService` | A | Quên mật khẩu |
| `CartActionResult`, `CartPageData`, `CheckoutResult`, `CheckoutFailedException`, `AccountResult` | DTO | Không phải service nghiệp vụ |
| `Export\ReportFileWriter` | A | Excel/PDF |

Không có service nào không được gọi trong nhóm trên. Không có service chỉ bọc một câu `SELECT` rồi bị bỏ không.

## 6. Route Audit

`routes/web.php` vẫn giữ URL CI3, kể cả hoa/thường (`VanChuyen`, `TichDiem`, `ThongTinTuyenDung`). Catch-all `{slug}` đứng sau route tĩnh. `GET /admin` không bị catalog nuốt vì slug loại `admin`.

Vấn đề:

- Comment đầu file và khối TODO cuối file vẫn nói controller là placeholder B2. Lệch code hiện tại. P3.
- `LegacyController@catalog`, `@product`, `@productSearch`, `@catalogC` được khai báo ở dòng 259–267 và 306–311 nhưng class không có các method đó. Request rơi `__call` và trả 501. P1.
- `/posts`, `/news`, `/cart`, `/order` đã 301. Giữ.
- `/tin-tuc/{offset}` là OFFSET SQL, không phải số trang. Đúng CI3. Không đổi.
- Nhiều URL admin dùng `GET` cho chuyển trạng thái đơn (accept/ship/complete) vì CI3 làm vậy. Mutation bằng GET là nợ bảo mật đã chấp nhận ở B10, không phải route chết.
- `routes/console.php` chỉ có lệnh `inspire` của skeleton. Không ảnh hưởng web.
- Không có route name trùng gây ghi đè trong các cặp đã đọc (`storefront.legacy.catalog` và `.page` là hai tên khác nhau).

## 7. View Audit

76 Blade. Các view sản phẩm `hot`, `new`, `views`, `discount`, `search`, `catalog`, `index` đều được `ProductController` render bằng `site/product/...`.

| View | Kết luận |
|------|----------|
| `resources/views/welcome.blade.php` | Không có `view('welcome')`. Skeleton Laravel. Candidate REMOVE, confidence HIGH |
| `layouts/app.blade.php` | Auth khách (`user-login`, register, forgot, reset). KEEP |
| `layouts/storefront/*`, `layouts/admin/*` | Đang dùng |
| `site/news/*`, `admin/news/*` | Tin tức đang dùng |
| `admin/users/view.blade.php` và `detail.blade.php` | `UserController` render cả hai cho hai URL khác nhau. Không trùng thừa |
| `layouts/storefront/partials/ai-widget.blade.php` | Gọi `AiSetting` ngay trong Blade |

`{!! !!}` đáng chú ý:

- `site/product/view.blade.php` in `$product->content` thô.
- `site/news/detail.blade.php` in `$post->content` thô.
- `admin/export/preview.blade.php` in `$html` thô (trang admin).
- JSON và badge HTML có `json_encode` kèm `JSON_HEX_*` hoặc helper tự escape. Hợp lệ.
- Comment đánh giá dùng `nl2br(e(...))`. Hợp lệ.

Form admin và storefront đã thấy `@csrf` trên các form POST đã migrate. Không rà từng form CI3 còn nằm trong JS tĩnh.

`site/user/index.blade.php` 676 dòng: một trang nhiều form. P3, chưa tách.

## 8. JS/CSS Audit

Storefront load CSS/JS từ `assets/site` qua `Shop::assetUrl()`. File lớn là Bootstrap và `style.css`, `style-detail.css`, `layout-chrome-2026.css`. Không thấy `console.log` trong `assets/site/js`.

`ai-chat.js` (506 dòng) gọi `/ai-assistant/send|history|poll`. Đúng route Laravel.

Không thấy secret trong JS đã quét. CSRF storefront vẫn dùng tên meta kiểu CI3 (`csrf_name` / `csrf_hash`) vì script cũ. Giữ đến khi bỏ JS CI3.

`laravel/resources/js` và `resources/css` không phải nơi giao diện shop. Không xóa Bootstrap.

## 9. Config Audit

13 file config. `config/shop.php`, `config/stock.php`, `config/ai.php` là config nghiệp vụ.

- `config/ai.php` đọc `GEMINI_API_KEY` từ env. Không hard-code key trong file Laravel này.
- `application/config/ai.php` (CI3, ngoài thư mục Laravel) còn key trong source. **SECRET PRESENT — DO NOT PRINT.** Đây là ISSUE-B15-01. Laravel không đọc file đó.
- `config/shop.php` mặc định VietQR `account_no` = `1234567890`, tên `NGUYEN VAN A` nếu env trống. Không phải secret, nhưng là dữ liệu giả có thể hiện trên QR. LOW.
- `config/database.php` trỏ MySQL qua env. Không in password ở báo cáo này.
- `.env` không được commit (`.gitignore`). `.env.example` không chứa key Gemini thật.

Stock migration Laravel (`database/migrations/0001_01_01_000000_create_users_table.php`) tạo bảng `users`, `password_reset_tokens`, `sessions`. App dùng bảng `user` và `password_reset`. Chạy migrate trên `webshop` có thể tạo bảng thừa hoặc lỗi. Không chạy.

## 10. Database/Query Audit

Không đổi schema, không đếm lại bằng query ghi.

| Bảng | Model | Ghi chú |
|------|--------|---------|
| `product`, `product_variants` | `Product`, `ProductVariant` | `quantity` là cache tổng `stock` |
| `transaction`, `order` | `Transaction`, `OrderItem` | `order` là dòng hàng, không phải header |
| `voucher`, `voucher_use` | `Voucher`, `VoucherUse` | `used_count` là cột đếm |
| `stock_movements` | `StockMovement` | Nhật ký, không phải số tồn |
| `user`, `user_address`, `user_phone`, `user_saved_cart` | các model tương ứng | Address/phone MyISAM |
| `product_review` | `ProductReview` | |
| `ai_*` | model AI | `ai_setting` MyISAM |
| `content_pages` | `ContentPage` | Không có `updated_at`, không có `price`, không có `status` |
| `banner`, `slider`, `stores` | `Banner`, `Slider`, `Store` | |

Raw SQL đã xem:

- `InventoryService` nối `stock - $qty` sau khi chữ ký đã là `int`. Không lấy số từ request. Chấp nhận.
- `ProductController` `orderByRaw("(price - discount) ASC|DESC")` chỉ khi `sort` là `price_asc` hoặc `price_desc`. Không ghép chuỗi người dùng. An toàn.
- `NewsController::view` `orderByRaw('(id = ?) desc', [$id])` có binding. An toàn.
- `VoucherService` `used_count + 1` trong transaction checkout. Đúng thiết kế.

Thiếu eager load trên một số list admin (đơn, sản phẩm) là nợ hiệu năng, chưa thấy vòng `foreach` gọi query từng dòng trong các service kho đã đọc. Dashboard gom query theo nhóm.

Index: không đo `EXPLAIN` trong vòng này. Bảng đơn và variant nhỏ (13 sản phẩm, 86 variant lúc các phase trước). Chưa kết luận thiếu index là HIGH.

## 11. Security Audit

| Mức | Việc |
|-----|------|
| BLOCKER | 0 trong Laravel runtime đã đọc |
| HIGH | Key Gemini nằm trong `application/config/ai.php` (CI3). Không in giá trị. Nên xoay key và bỏ khỏi source |
| MEDIUM | HTML sản phẩm và HTML tin tức in thẳng ra storefront. Admin level 2 viết được tin (đúng CI3) nên một tài khoản level 2 có thể nhét script vào bài |
| MEDIUM | URL legacy sản phẩm trả 501 thay vì 301. Không lộ dữ liệu, nhưng là lỗ hành vi |
| LOW | GET đổi trạng thái đơn, theo CI3 |
| LOW | VietQR mặc định giả trong config |
| LOW | Policy class không được dùng nên không phải lỗ hổng; quyền thật nằm ở middleware |

Đã đối chiếu các rule đã khóa từ B3–B16, không chạy lại exploit:

- Guard admin không tin `session.login` giả và không tin `?level=`.
- Giá giỏ lấy từ DB, không lấy `price` client.
- `user_id` đơn lấy từ user đăng nhập.
- CSRF còn trên POST. Test các module đã có case 419.
- Upload ảnh sản phẩm và tin từ chối MIME lạ, tên file do server đặt.
- `ProductImageStore::sanitize` dùng `basename`, chặn `..`.

## 12. Performance Audit

| Mức | Chỗ |
|-----|-----|
| HIGH | 0 trên dữ liệu hiện tại |
| MEDIUM | `ReportService` và export chạy đồng bộ trong request |
| MEDIUM | `ChatService` gọi Gemini trong request (timeout 10 giây đã có) |
| LOW | Home và một số list không eager load vì bảng nhỏ |
| LOW | `ai-widget.blade.php` đọc `ai_setting` mỗi trang storefront |
| LOW | Danh sách nhân viên và tin tức không phân trang, đúng CI3, ít dòng |

Không thấy cache sai scope trong các service đã đọc.

## 13. CI3 Legacy Audit

| Hạng | Phần | Lý do |
|------|------|--------|
| A giữ | `custom_cart`, `LegacyPassword`, flash `message_success`, URL SEO, `SyncLegacyUserSession`, `SyncLegacyAdminSession` | JS và dữ liệu cũ còn phụ thuộc |
| A giữ | 301 `/cart`, `/order`, `/posts`, `/news` | Bookmark |
| B bỏ sau khi migration xong | `handlePath` + `__call` trên controller đã viết đủ action | Chỉ còn để URL lạ không 500 |
| C dư | Comment TODO trong `routes/web.php` | Lệch thực tế |
| C dư | `app/Policies/AdminPermission.php` | Không ai import |
| D phức tạp | `LegacyController` vừa 301 vừa 501 | Dễ tưởng URL cũ đã chuyển hết |
| E rủi ro | Route `catalog` / `product` / `catalogC` gọi method không tồn tại | 501 thay vì redirect |
| E rủi ro | Migration `users` mặc định | Lệnh migrate có thể đụng DB thật |

## 14. Placeholder/501 Audit

`abort(501)` trong `laravel/app` lúc audit: **47** lời gọi, **16** controller. Sau B17.1: **44** lời gọi, **15** controller (`GioithieuController` đã có `index` thật).

| File | Số lần | Loại |
|------|--------|------|
| 8 admin stub (banner, slider, pages, store, tuyển dụng, hợp tác, vận chuyển, branding) | 3 mỗi file | REAL PLACEHOLDER |
| 6 storefront stub (giới thiệu, cửa hàng, loyalty, tuyển dụng, hợp tác, vận chuyển) | 3 mỗi file | REAL PLACEHOLDER |
| `LegacyController` `index`, `handlePath`, `__call` | 3 | REAL PLACEHOLDER cho method chưa viết. Các method 301 phía trên là VALID |
| `HomeController` `handlePath`, `__call` | 2 | DEAD CODE nếu không có route. Không thấy route gọi `handlePath` |

`TODO` trong comment stub và trong `routes/web.php`: thật, nhưng đoạn route đã cũ.

`FIXME`: 0 trong `laravel/app`.

`return false` trong service kho, voucher, review là nhánh nghiệp vụ, không phải stub.

## 15. Dead File Audit

| Path | Evidence | Referenced by | Recommendation |
|------|----------|---------------|----------------|
| `laravel/resources/views/welcome.blade.php` | Không có `view('welcome')`. `/` render `site.home.index` | Không | REMOVE sau, confidence HIGH |
| `laravel/app/Policies/AdminPermission.php` | Không có `use App\Policies\AdminPermission`. Gate không trỏ file này | Không | REMOVE sau, confidence HIGH. Quyền nằm ở `app/Support/Admin/AdminPermission.php` |
| `laravel/app/Models/Store.php` | Không có chỗ `Store::` | Không | REVIEW. Sẽ cần khi làm cửa hàng. Đừng xóa vội |
| `laravel/routes/console.php` lệnh `inspire` | Skeleton | Artisan | KEEP, vô hại |
| `database/migrations/0001_01_01_000000_create_users_table.php` và 2 file cache/jobs | Không mô tả schema `webshop` | `artisan migrate` nếu ai chạy | REVIEW. Không xóa cho đến khi có kế hoạch migration. Không chạy |

## 16. Duplicate Logic Audit

1. **Quyền.** Chuỗi quyền nằm trong `app/Policies/AdminPermission.php` và lặp lại dạng string trong `app/Support/Admin/AdminPermission.php`. Chỉ class Support được middleware gọi.
2. **Giá bán.** `Product::salePrice()` và `CartSessionService` đều lấy `price - discount`, sàn 1000 ở giỏ. Hai chỗ phải cùng công thức.
3. **Tồn kho.** `VariantStockService` cho giỏ, `InventoryService` cho đơn và admin. Dashboard dùng `product.quantity <= 10`. Màn kho dùng `stock <= min_stock`. Cố ý không gộp.
4. **Voucher.** Chỉ `VoucherService`. Admin không tự tăng `used_count`.
5. **Ảnh.** Sản phẩm đi `Product::imageUrl()`. Tin tức Blade ghép `url('upload/news/...')`. Hai kiểu, cùng thư mục `upload/`.
6. **Tin.** `NewsController` phân trang 8 bài. `TintucController` liệt kê mọi bài tại `/tintuc`. Đúng hai URL CI3, không phải copy nhầm.
7. **Điểm.** `LoyaltyService` ghi điểm khi đơn hoàn tất. Trang xem điểm vẫn 501.

## 17. Documentation Audit

| Tài liệu | Lệch |
|----------|------|
| `docs/MIGRATION-PROGRESS.md` | B3–B16 và tin tức (gọi là B17) khớp code đã có controller thật. Không nói migration đã xong toàn bộ |
| `docs/B16-AUDIT.md` | Đoạn đầu vẫn ghi 58 lần 501 và tin tức còn stub. Đoạn cuối nói nhân viên và tin tức đã migrate, còn 47 lần. Hai đoạn mâu thuẫn. Số 47 khớp đếm hôm nay. Số controller còn 501 thực tế là 16, không phải 19 |
| `docs/B17-AUDIT.md`, `docs/B17-NEWS-MAPPING.md` | Mô tả tin tức, khớp `NewsController` và bảng `content_pages`. Không phải audit toàn project |
| `routes/web.php` comment | Vẫn viết mọi controller là placeholder. Sai |
| `docs/MIGRATION-ISSUES.md` | ISSUE-B5-10 (ExampleTest sqlite), B5-13 (logout chung session), B7-05 (file địa giới), B15-01 (key CI3) vẫn mở. Không thấy code đã fix các mục đó |

Không có dòng nào trong progress bảo các trang 501 đã xong.

## 18. Test Audit

31 file test. Feature nghiệp vụ dùng `LegacyMysqlTestCase`: ép MySQL `webshop` và `DatabaseTransactions`.

| Nhóm | Đánh giá |
|------|----------|
| Cart, Order, Account, Admin product/order/kho/voucher/báo cáo/review/AI/staff/news | Test khóa behavior, có cleanup transaction |
| `tests/Feature/ExampleTest.php`, `tests/Unit/ExampleTest.php` | Skeleton. `phpunit.xml` ép sqlite. ISSUE-B5-10. Không tính vào regression B3–B16 |
| Test 501 | Không có. Đúng vì các URL đó chưa làm |
| Dữ liệu thật | Test tạo dòng `B9`/`B16`/`B17` rồi rollback. `user_address` và `ai_setting` MyISAM không rollback; các test đó tự xóa hoặc restore |
| Thiếu | Legacy 501 của product URL chưa có test. Trang 501 chưa có test |

Không chạy lại suite trong vòng audit này.

## 19. Architecture Audit

| Chỗ | Mức |
|-----|-----|
| Route không chứa nghiệp vụ | GOOD |
| Giỏ, checkout, voucher, kho, báo cáo, chat đi qua service | GOOD |
| Form Request cho sản phẩm, voucher, phiếu nhập, checkout, auth | GOOD |
| `ProductController` và `UserController` còn query và nhiều action | ACCEPTABLE |
| `Admin\NewsController` validate trong controller | ACCEPTABLE |
| Blade `ai-widget` đọc DB | NEEDS REFACTOR (P3) |
| `ContentPage` cast cột không tồn tại | NEEDS REFACTOR (P3) |
| `LegacyController` 501 cho route đã public | BAD đối với các method product/catalog |
| Migration mặc định không khớp schema | BAD nếu bị chạy |

## 20. Complexity Audit

| File | Dòng | Đề xuất |
|------|------|---------|
| `Storefront/ProductController.php` | 790 | SPLIT khi sửa catalog |
| `CartSessionService.php` | 776 | KEEP. Tách dễ làm lệch giỏ |
| `ReportService.php` | 706 | SPLIT theo module export sau |
| `site/user/index.blade.php` | 676 | KEEP đến khi đụng account UI |
| `routes/web.php` | 629 | KEEP. Thứ tự route là behavior |
| `ChatService.php` | 595 | KEEP |
| `UserController.php` | 592 | KEEP |
| `site/product/view.blade.php` | 538 | KEEP. PDP nhiều khối |
| `ProductAdminService.php` | 541 | KEEP |
| `AdminDashboardService.php` | 524 | KEEP |
| `CheckoutService.php` | 513 | KEEP |
| `StockReceiptController.php` | 485 | REFACTOR phần path khi sửa phiếu |
| `ai-chat.js` | 506 | KEEP |

Bootstrap CSS/JS lớn vì là thư viện, không tách.

## 21. Module Status

| Module | Status | Quality | Issues | Priority |
|--------|--------|---------|--------|----------|
| Authentication | DONE | GOOD | Logout chung session với admin (B5-13) | P3 |
| Home | DONE_WITH_ISSUES | GOOD | Banner admin chưa sửa được | P2 |
| Product / Catalog / Variant / Image | DONE_WITH_ISSUES | GOOD | Controller dài. Ảnh local | P3 |
| Cart / Checkout / Order | DONE_WITH_ISSUES | GOOD | GET một số action theo CI3 | P3 |
| User Account | DONE_WITH_ISSUES | GOOD | View dài. File địa giới thiếu (B7-05) | P2 |
| Admin Dashboard / Permission / Staff | DONE_WITH_ISSUES | GOOD | | P3 |
| Admin Product / Catalog / Order / Customer | DONE_WITH_ISSUES | GOOD | | P3 |
| Inventory / Supplier / Receipt / Movement | DONE_WITH_ISSUES | GOOD | Hai ngưỡng hết hàng, cố ý | P3 |
| Voucher / Reports / Reviews / AI | DONE_WITH_ISSUES | GOOD | Key CI3. Export đồng bộ | P1 cho key, P3 cho export |
| News | DONE_WITH_ISSUES | GOOD | HTML thô. Level 2 được sửa | P2 |
| Static pages / Banner / Slider admin | STUB | — | 501. Home vẫn đọc banner/slider | P2 |
| Transport / Store / Recruitment / Cooperation / Logo | STUB | — | 501 | P3 |
| Loyalty page | PARTIAL | — | Điểm đã ghi khi hoàn tất đơn. Trang xem 501 | P3 |
| Legacy product URLs | STUB | BAD | Route trỏ method không có | P1 |
| Legacy cart/order/news URLs | DONE | GOOD | 301 | — |

## 22. Priority Matrix

| ID | Priority | Severity | File | Problem |
|----|----------|----------|------|---------|
| A1 | P1 | HIGH | `application/config/ai.php` | Secret Gemini trong source CI3 |
| A2 | P1 | HIGH | `database/migrations/0001_01_01_000000_create_users_table.php` | Migrate có thể tạo schema Laravel đè lên quy ước DB hiện tại |
| A3 | P1 | MEDIUM | `LegacyController` + `routes/web.php` 259–311 | URL cũ sản phẩm/catalog trả 501 |
| A4 | P2 | MEDIUM | `site/news/detail.blade.php`, `site/product/view.blade.php` | HTML nội dung không escape |
| A5 | P2 | MEDIUM | Stub giới thiệu, banner, slider | Menu/home trỏ tới chỗ chưa quản trị được |
| A6 | P2 | LOW | `docs/B16-AUDIT.md`, comment `routes/web.php` | Tài liệu lệch số 501 |
| A7 | P3 | LOW | `ProductController.php`, `ReportService.php` | File dài |
| A8 | P3 | LOW | `welcome.blade.php`, `Policies/AdminPermission.php` | File không được gọi |
| A9 | P3 | LOW | `ContentPage` cast `updated_at`, scope không dùng | Model lệch bảng |
| A10 | P3 | LOW | `config/shop.php` VietQR mặc định | QR giả nếu thiếu env |

P0: không có.

## 23. Recommended Cleanup Order

### PHASE 1 — BLOCKER/HIGH

1. Không chạy `php artisan migrate` trên `webshop`.
2. Xoay key trong `application/config/ai.php` và bỏ key khỏi file. Không ghi giá trị vào git hoặc docs.

### PHASE 2 — MEDIUM

3. 301 cho `product/view`, `product/detail`, `product/catalog`, `{slug}-c{id}` hoặc bỏ route nếu cố ý không hỗ trợ.
4. Chốt chính sách HTML tin tức và mô tả sản phẩm (giữ HTML admin hay lọc thẻ).

### PHASE 3 — Architecture

5. Tách query khỏi `ProductController` khi có đợt sửa catalog.
6. Tách `ReportService` theo từng báo cáo khi sửa export.
7. Đưa đọc `AiSetting` ra view composer.

### PHASE 4 — Dead code cleanup

8. Xóa `welcome.blade.php` và `app/Policies/AdminPermission.php` sau khi xác nhận không có package ngoài repo gọi chúng.
9. Sửa comment route và `docs/B16-AUDIT.md`.
10. Bỏ `handlePath`/`__call` của `HomeController` khi chắc không có route động.

### PHASE 5 — Performance

11. Chỉ làm sau khi đo. Dữ liệu hiện tại nhỏ. Export và Gemini là chỗ chờ người dùng thấy chậm.

### PHASE 6 — Remaining migration

Thứ tự gợi ý: giới thiệu / trang tĩnh, rồi banner và slider (home đang đọc), rồi cửa hàng, vận chuyển, tuyển dụng, hợp tác, logo, trang tích điểm, cuối cùng là URL legacy sản phẩm nếu chưa làm ở phase 2.

### PHASE 7 — New features

Chưa làm. Audit không đề xuất tính năng mới.

## 24. Action list

| Priority | File/Module | Problem | Why | Recommended Action |
|----------|-------------|---------|-----|--------------------|
| P1 | `application/config/ai.php` | Key nhà cung cấp AI nằm trong file PHP CI3 | File có thể bị commit hoặc copy | Xoay key, để trống như `.example`. Không in key |
| P1 | `laravel/database/migrations/0001_01_01_000000_create_users_table.php` | Tạo bảng `users` không phải bảng `user` của shop | `migrate` nhầm sẽ đụng DB thật | Cấm migrate trên `webshop` đến khi có migration riêng, an toàn |
| P1 | `laravel/app/Http/Controllers/Storefront/LegacyController.php` method thiếu `catalog`, `product`, `productSearch`, `catalogC` | `routes/web.php` dòng 259–267 và 306–311 gọi các method này | `__call` trả 501 | Thêm 301 sang URL SEO hiện có, giữ query string |
| P2 | `laravel/resources/views/site/news/detail.blade.php` dòng content `{!! $post->content !!}` | HTML bài viết không escape | Level 2 admin sửa được tin | Giữ nếu cố ý cho rich text. Ghi rủi ro. Không cho khách gửi HTML |
| P2 | `laravel/resources/views/site/product/view.blade.php` `{!! $product->content !!}` | Mô tả sản phẩm là HTML | Cùng kiểu CMS | Cùng quyết định với tin tức |
| P2 | `GioithieuController`, `PagesController` | `/gioi-thieu` và admin trang tĩnh trả 501 | Menu có thể trỏ tới | Migrate một module, không gộp |
| P2 | `BannerController`, `SliderController` | Home đọc bảng, admin không sửa được | Ảnh banner/slider lệch nếu file thiếu trên đĩa | Migrate sau trang tĩnh |
| P3 | `laravel/app/Http/Controllers/Storefront/ProductController.php` | 790 dòng, query danh mục trong controller | Khó đọc, chưa sai behavior | Tách service lúc sửa catalog |
| P3 | `laravel/app/Services/ReportService.php` | 706 dòng, nhiều kiểu export | Sửa một báo cáo dễ chạm báo cáo khác | Tách khi có việc sửa report |
| P3 | `laravel/resources/views/welcome.blade.php` | Không được render | Skeleton | Xóa ở đợt cleanup |
| P3 | `laravel/app/Policies/AdminPermission.php` | Không được import | Trùng tên với class Support đang dùng | Xóa ở đợt cleanup, đừng đổi middleware |
| P3 | `laravel/app/Models/ContentPage.php` | Cast `updated_at`, scope không dùng | Bảng không có `updated_at`. Tin tức xếp theo `id` | Bỏ cast và scope chết khi đụng model |
| P3 | `docs/B16-AUDIT.md` | Đầu file nói 58 lần 501 và tin tức còn stub | Cuối file nói 47 và tin tức đã xong | Sửa cho một số đếm |

## Candidate files for removal

### `laravel/resources/views/welcome.blade.php`

- Evidence: không có `view('welcome')`. Route `/` dùng `HomeController@index`.
- Replacement: `resources/views/site/home/index.blade.php`.
- Risk: thấp.
- Confidence: HIGH.

### `laravel/app/Policies/AdminPermission.php`

- Evidence: search `App\Policies\AdminPermission` không có kết quả. Middleware alias `admin.permission` trỏ `App\Http\Middleware\AdminPermission`, class đó dùng `App\Support\Admin\AdminPermission`.
- Replacement: `app/Support/Admin/AdminPermission.php`.
- Risk: thấp nếu không có code ngoài repo `use` class Policy.
- Confidence: HIGH.

### `laravel/app/Models/Store.php`

- Evidence: không có `Store::` ngoài file model.
- Replacement: chưa có. Module cửa hàng còn 501.
- Risk: cao nếu xóa rồi viết lại tay sai cột `stores`.
- Confidence: HIGH là chưa dùng, LOW là nên xóa. Recommendation: không xóa.

## Core files — DO NOT REMOVE

- `app/Services/CartSessionService.php` — giỏ `custom_cart`.
- `app/Services/CheckoutService.php`, `app/Services/VoucherService.php`, `app/Services/InventoryService.php` — tiền, voucher, tồn.
- `app/Services/LoyaltyService.php` — điểm khi đơn hoàn tất.
- `app/Support/LegacyPassword.php` — MD5 cũ trước bcrypt.
- `app/Support/Storefront/Urls.php` — URL SEO.
- `app/Http/Middleware/SyncCartSession.php`, `SyncLegacyUserSession.php`, `SyncLegacyAdminSession.php`.
- `app/Support/Admin/AdminPermission.php` và `app/Http/Middleware/AdminPermission.php`.
- `routes/web.php` — thứ tự catch-all.
- `tests/LegacyMysqlTestCase.php`.
- Ảnh trong `upload/product` đang được DB trỏ tới.

## Không làm trong vòng này

Không xóa file, không sửa route, không chạy migrate, không đổi dữ liệu.
