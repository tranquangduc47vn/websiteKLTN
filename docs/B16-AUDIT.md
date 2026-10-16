# B16 audit — phần còn lại sau B15

Ảnh chụp này lấy trước B16/B17. Số hiện tại sau B17.1: **44** lời gọi `abort(501)` trong **15** controller. Nhân viên, tin tức, `/gioi-thieu` và URL legacy sản phẩm không còn 501. Xem `docs/B17-1-PRIORITY-CLEANUP.md`.

Đếm lúc viết audit: **58** lời gọi `abort(501)`, nằm ở **20** controller. Trang chủ storefront, logout admin, giỏ, thanh toán, đơn, sản phẩm, kho, voucher, báo cáo, đánh giá và chat đã có implementation. Các `abort(501)` dưới đây là phần chưa làm.

| Module | CI3 | Laravel | 501 | Nhóm | Priority |
|--------|-----|---------|-----|------|----------|
| Nhân viên admin | `admin/Admin.php` (index/add/edit/del) | `AdminController` chỉ có logout | Có | Core vận hành / quyền | **P1 — chọn B16** |
| Tin tức | `News.php`, `admin/News.php` | `NewsController` storefront + admin | Có | Nội dung | P2 |
| Trang tĩnh | `Gioithieu.php`, `admin/Pages.php` | `GioithieuController`, `PagesController` | Có | Nội dung | P2 |
| Slider + banner | `admin/Slider.php`, `admin/Banner.php` | stub; home đã đọc bảng | Có (admin) | Hiển thị trang chủ | P2 |
| Vận chuyển (trang chính sách) | `Vanchuyen.php`, `admin/Vanchuyen.php` | stub | Có | Nội dung | P3 |
| Cửa hàng / bản đồ | `Hethongcuahang.php`, `admin/Store.php` | stub | Có | Nội dung | P3 |
| Tuyển dụng | `Tuyendung.php`, `admin/Tuyendung.php` | stub | Có | Nội dung | P3 |
| Hợp tác | `Hoptac.php`, `admin/Hoptac.php` | stub | Có | Nội dung | P3 |
| Logo | `admin/Site_branding.php` | stub | Có | Tùy chọn | P3 |
| Tích điểm (trang xem) | `Loyalty.php` | stub; cộng điểm đơn đã có ở B6/B10 | Có | Tùy chọn | P3 |
| Tintuc | `Tintuc.php` trùng tin tức | stub | Có | Legacy URL | P3 |
| Legacy URL sản phẩm/tin | `Legacy.php` catalog, product, posts | cart và order đã 301; method kia rơi vào `__call` | Có | Tương thích URL | P2 |
| Home `handlePath` | không có action thêm | stub không gắn route | Có | Dead stub | Không migrate |

Không có P0. Checkout, tồn kho, giá, voucher, đơn không còn lỗ 501.

## TOP 3

1. **Nhân viên admin — P1.** Menu `/admin/admin` đang sống. Quyền `staff.manage` đã có từ B8 nhưng màn hình vẫn 501. Đây là chỗ tạo tài khoản và level 0/1/2.
2. **Tin tức — P2.** Footer và menu admin trỏ tới, cả storefront lẫn admin đều 501.
3. **Trang tĩnh / giới thiệu — P2.** `/gioi-thieu` và `/admin/page` đều 501.

## B16 target

**Quản lý nhân viên** (`admin` table). Đã migrate trong B16. Tin tức đã migrate trong B17. Sau B17 còn 47 `abort(501)`. Sau B17.1 còn 44 (đã bỏ stub `GioithieuController`).

Lý do chọn: đây là module chưa migrate duy nhất còn ghi dữ liệu quyền. Tin tức và trang tĩnh không chặn bán hàng. Không gộp nhiều module trong một bước.
