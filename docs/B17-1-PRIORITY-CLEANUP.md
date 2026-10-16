# B17.1 PRIORITY CLEANUP

## Status

B17.1 STATUS: DONE_WITH_ISSUES

Không tách `ProductController` và `ReportService`. Không đổi schema. Không `migrate:fresh`, truncate, drop hay reset. Banner và slider admin chưa migrate.

## 1. Gemini Secret

Before: `application/config/ai.php` gán literal vào `ai_gemini_api_key`. Laravel đã đọc `env('GEMINI_API_KEY')`. `.env.example` đã có `GEMINI_API_KEY=` trống.

After: CI3 đọc `getenv('GEMINI_API_KEY')` và để chuỗi rỗng khi biến không có. Giá trị local nằm trong `laravel/.env` (gitignored). `.env.example` vẫn chỉ có `GEMINI_API_KEY=` trống. Không in key. Không commit `.env`.

Git: `application/config/ai.php` nằm trong `.gitignore`. Không có trong `git ls-files` và không có commit history cho path đó. Xóa literal khỏi file **không** thay cho việc xoay key: giá trị từng nằm plaintext trên đĩa máy local. Cần ROTATE/REVOKE trên Google.

Blade, JS và `config/ai.php` không gắn key vào HTML. Test AI hiện có vẫn kiểm tra widget và log không lộ key.

Status: source sạch. Rotation vẫn là việc vận hành, chưa gọi API thật.

## 2. Legacy Product URLs

Before: route `/product/view/{id}`, `/product/detail/{id}`, `/product/catalog/{id}`, `{slug}-c{id}`, `/product/search` gọi method không tồn tại và rơi `__call` → 501.

After: các method đó trả 301.

- Sản phẩm → `Urls::product($id)`. Không có sản phẩm thì về `/` (đúng CI3, không 404).
- Danh mục → `Urls::category`. Offset > 0 nối vào path. `price_range` đưa vào path. Query còn lại (ví dụ `sort`) được giữ. Slug trên URL `-c{id}` bị bỏ, dùng id.
- Slug danh mục rỗng sẽ ra `/product/catalog/{id}`. Trường hợp đó chuyển về `/` để không loop.
- Tìm kiếm → `Urls::search`.

Guest và user đăng nhập nhận cùng redirect. URL SEO hiện tại (`Urls::product`, `Urls::category`) vẫn 200.

Routes:

- `GET|POST product/view/{id}` → `LegacyController@product`
- `GET|POST product/detail/{id}` → `LegacyController@product`
- `GET|POST product/catalog/{catalogId}` và `/{offset}` → `LegacyController@catalog`
- `GET|POST {slug}-c{id}` và `/{offset}` → `LegacyController@catalogC`
- `GET|POST product/search` → `LegacyController@productSearch`

## 3. HTML/XSS

Before: `site/product/view.blade.php` và `site/news/detail.blade.php` in `{!! $content !!}`. Mô tả sản phẩm trong DB không có tag. Tin tức dùng `p`, `h2`, `h3`, `ul`, `li`, `strong`, `div`, `img`. Admin nhập bằng textarea đã escape, nhưng HTML lưu trong DB vẫn chạy trên storefront. Level 2 sửa được tin (CI3 không gọi `content.manage`). Không đổi quyền đó.

After: `App\Support\HtmlSanitizer` chạy server-side khi hiển thị và khi admin lưu sản phẩm hoặc tin. Không `UPDATE` hàng cũ. Trang admin không render body bằng `{!! !!}`.

Sanitization: giữ `p`, `br`, `strong`, `b`, `em`, `i`, `u`, `h1`–`h6`, `ul`, `ol`, `li`, `a`, `img`, `span`, `div`, `blockquote`, `hr`. Bỏ `script`, `style`, `iframe`, `object`, `embed` và tag form. Bỏ `on*`, `style`, `class`. `href`/`src` chỉ nhận đường dẫn tương đối, `http`, `https`, và với link thì thêm `mailto`/`tel`. Chặn `javascript:`, `vbscript:`, `data:`.

## 4. Static Pages

Status: `GET /gioi-thieu` → 200. Đọc `content_pages` type `page`, slug `gioi-thieu`. Thiếu bài thì dùng tiêu đề và câu dự phòng của CI3. Sáu tin mới nhất theo `id DESC`. Layout storefront đã có slider. Không tạo CMS. Admin `/admin/page` vẫn 501.

## 5. Banner

Status: chưa làm. Home vẫn đọc bảng. Admin `BannerController` vẫn 501. Upload và quyền đủ lớn. Ghi ISSUE-B17.1-01, làm ở B18.

## 6. Slider

Status: chưa làm. Cùng lý do banner. `SliderController` vẫn 501.

## 7. Dead Files

Removed:

- `laravel/resources/views/welcome.blade.php` — không có `view('welcome')`. `/` là `HomeController`.
- `laravel/app/Policies/AdminPermission.php` — không import. Middleware dùng `App\Support\Admin\AdminPermission`.

Kept:

- `laravel/app/Models/Store.php` — module cửa hàng chưa migrate.

Reason: hai file đầu không có reference trong route, controller, view, policy registration hay test. Model `Store` sẽ cần khi làm cửa hàng.

## 8. Documentation

Updated files:

- `docs/B16-AUDIT.md` — ghi rõ 58 là ảnh cũ; hiện còn 44.
- `docs/B17-FULL-AUDIT.md` — thêm mục cập nhật, không xóa ảnh audit.
- `docs/MIGRATION-PROGRESS.md` — thêm B17.1.
- `docs/MIGRATION-ISSUES.md` — B15-01 và B17-02 thành FIXED; thêm ISSUE-B17.1-01..03.
- `laravel/routes/web.php` — comment TODO không còn nói mọi controller là stub 501.

## 9. Tests

- PHP syntax: `php -l` sạch trên `HtmlSanitizer`, `LegacyController`, `GioithieuController`, `Admin\NewsController`, `ProductAdminService`.
- Routes: `gioi-thieu` → `GioithieuController@index`. Product legacy trỏ `LegacyController` như mục 2. `php artisan view:cache` thành công.
- Feature tests: `PriorityCleanupTest` 4 passed (35 assertions). Gồm redirect không loop, user đăng nhập, HTML giữ chữ và bỏ script, `/gioi-thieu` 200.
- Regression: `tests/Feature/Admin`, `Account`, `Cart`, `Order`, `Storefront`, `AI` → **146 passed (1423 assertions)**. Không tính `ExampleTest`.
- Assertions: secret không còn literal trong source CI3; redirect 301; URL SEO 200; script payload không còn trong HTML bài; CSRF các suite cũ vẫn pass; fake session admin vẫn bị chặn trong suite B3–B16.

## 10. Database Safety

Không đổi schema. Không xóa dữ liệu thật. Test nằm trong transaction. Bài tin tạo trong test bị xóa trong `finally`.

Sau regression:

| Mục | Số |
|-----|----|
| product | 13 |
| catalog | 22 |
| product_variants | 86 |
| user | 4 |
| transaction | 24 |
| content_pages type news | 6 |
| bài `B171 tin` | 0 |
| user_saved_cart user 10 | 335 byte |
| user_saved_cart user 11 | 2 byte |
| JM10 | used_count 0, is_active 1 |
| JM20 | used_count 2, is_active 1 |

`abort(501)`: 47 trước, 44 sau.

## 11. Remaining Issues

BLOCKER: không có.

HIGH: ROTATE/REVOKE Gemini key trên Google. Source và git đã sạch; key từng nằm trên đĩa local.

MEDIUM: ISSUE-B17.1-01 admin banner và slider vẫn 501.

LOW: ISSUE-B17.1-02 chưa tách `ProductController` / `ReportService`. ISSUE-B17.1-03 admin trang tĩnh và các trang nội dung còn lại vẫn 501. ISSUE-B15-03 chưa gọi Gemini thật.

## 12. Next Recommended Step

B18: admin banner và slider, sau một mapping ngắn (upload, active, thứ tự, quyền, CSRF, storefront). Không gộp cửa hàng, vận chuyển, tuyển dụng hay hợp tác vào bước đó.
