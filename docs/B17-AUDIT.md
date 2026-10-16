# B17 — Tin tức

CI3 không còn bảng `news`. `News_model` đọc `content_pages` với `type = news`. Cột ảnh là `image`, thời gian là `created_at`. `image_link` và `created` chỉ là tên cũ khi đọc.

| CI3 | Laravel | Ghi chú |
|-----|---------|---------|
| `News::index` | `Storefront\NewsController::index` | `/tin-tuc` và `/tin-tuc/{offset}`, 8 bài, sort mặc định `id DESC` |
| `News::view` | `NewsController::view` | `/tin-tuc/{slug}-n{id}`. Sai id thì về trang chủ |
| `Tintuc::index` | `TintucController::index` | `/tintuc`, mọi bài, có slider |
| `Legacy::posts`, `post_view` | `LegacyController` | 301 sang URL mới |
| `admin/News` | `Admin\NewsController` | `/admin/posts`. `/admin/news` 301 |
| `content_pages` | `ContentPage` | Không đổi schema |

Admin CI3 không gọi `admin_can`. Level 2 đăng nhập admin vẫn mở được. Menu thì ẩn. Laravel giữ vậy, không bọc `content.manage`.

Ảnh: JPG/PNG/GIF/WEBP/BMP, tối đa 5MB, tên do server đặt trong `upload/news`. Không lưu tên file người dùng gửi. Không xóa `default.jpg`.

`?sort=price_asc` của CI3 gọi cột `price` không có trên `content_pages`. Laravel bỏ sort đó và vẫn xếp theo `id`, tránh lỗi SQL.
