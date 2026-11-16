# LEGACY AUDIT

Trạng thái: **NOT DONE**. Chưa chuyển entrypoint. Chưa xóa CodeIgniter.

Request hiện tại vẫn đi: Browser → `.htaccess` gốc dòng 11 → `index.php` CodeIgniter.

## Đã chuyển sang Laravel trong lượt này

Các URL storefront sau không còn `abort(501)`. Chúng đọc bảng hiện có, không đổi schema.

| URL | Controller Laravel | Dữ liệu |
|---|---|---|
| `/hethongcuahang` | `HethongcuahangController@index` | `stores` |
| `/vanchuyen`, `/VanChuyen`, `/vanchuyen/view/{slug}` | `VanchuyenController` | `content_pages` type `van_chuyen` |
| `/ThongTinTuyenDung`, `/tuyendung/view/{slug}` | `TuyendungController` | type `tuyendung` |
| `/LienHeHopTacKinhDoanh`, `/hoptac/view/{slug}` | `HoptacController` | type `hop_tac` |
| `/tich-diem`, `/TichDiem` | `LoyaltyController` | chính sách tĩnh, cùng quy tắc `Loyalty` |

Test: `tests/Feature/Storefront/LegacyPagesTest.php` và cả `tests/Feature/Storefront` — 13 passed.

## Còn runtime CodeIgniter — P0

| File | Chức năng | Thay thế Laravel | Trạng thái |
|---|---|---|---|
| `.htaccess` dòng 11 | Rewrite mọi URL động vào CI3 | `laravel/public/index.php` | KEEP TEMPORARILY. Chưa đổi vì admin bên dưới còn 501; đổi ngay sẽ làm hỏng các trang admin đang chạy trên CI3. |
| `index.php` | Entrypoint CI3 | `laravel/public/index.php` | KEEP TEMPORARILY |
| `application/controllers/**` | Controller CI3 | Một phần đã có trong `laravel/app/Http/Controllers` | KEEP TEMPORARILY |
| `application/models/**` | Model CI3 | Eloquent trong `laravel/app/Models` | KEEP TEMPORARILY |
| `application/views/**` | View CI3 | Blade | KEEP TEMPORARILY |
| `system/**` | Framework CI3 | Laravel | KEEP TEMPORARILY |

## abort(501) còn lại — P1, MIGRATE

Admin, route đã khai báo trong `laravel/routes/web.php`:

- `Admin/BannerController.php` 15, 20, 25 — banner
- `Admin/SliderController.php` 15, 20, 25 — slider
- `Admin/PagesController.php` 15, 20, 25 — trang tĩnh admin
- `Admin/StoreController.php` 15, 20, 25 — cửa hàng admin
- `Admin/TuyendungController.php` 15, 20, 25
- `Admin/HoptacController.php` 15, 20, 25
- `Admin/VanchuyenController.php` 15, 20, 25
- `Admin/SiteBrandingController.php` 15, 20, 25 — logo

Storefront, không gắn route chính nhưng vẫn 501 nếu bị gọi:

- `Storefront/HomeController.php` 63, 68
- `Storefront/LegacyController.php` 121, 126, 131 — các URL sản phẩm/đơn/tin cũ đã 301 ở method khác

## Session — P2, KEEP TEMPORARILY

- Giỏ `custom_cart` là session Laravel, không phải runtime CI3.
- `StorefrontComposer` vẫn đọc `session('user')` khi guard web trống.
- Middleware admin vẫn phản chiếu guard sang session tương thích.
- Chưa xóa lớp này vì chưa chứng minh hết consumer.

## Không xóa

Chưa có bằng chứng 0 reference. Không xóa `application/`, `system/`, `index.php`.
