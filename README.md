# WebShop

## Giới thiệu

Website bán hàng thời trang. Ứng dụng đang chạy là Laravel trong thư mục `laravel/`. Source CodeIgniter 3 đã gỡ khỏi runtime. Bản rollback nằm ở archive `ci3-archive-*.zip` cùng thư mục gốc.

## Công nghệ

- Laravel 12
- PHP 8.2+
- MySQL
- Blade
- JavaScript
- Bootstrap/CSS
- Gemini AI

Asset storefront và admin nằm ở `assets/` và `public/`, không phụ thuộc bản build Vite.

## Chức năng chính

### Khách hàng

- Đăng ký / đăng nhập
- Quản lý tài khoản
- Xem, tìm kiếm và lọc sản phẩm
- Chi tiết sản phẩm
- Giỏ hàng
- Đặt hàng
- Voucher
- Theo dõi đơn hàng
- Đánh giá sản phẩm
- Tích điểm
- AI hỗ trợ

### Quản trị

- Dashboard
- Quản lý sản phẩm
- Quản lý danh mục
- Quản lý đơn hàng
- Quản lý khách hàng
- Quản lý kho
- Quản lý nhập hàng
- Quản lý nhà cung cấp
- Quản lý voucher
- Báo cáo
- Quản lý đánh giá
- Quản lý nhân viên
- AI hỗ trợ

## Cài đặt

Từ thư mục `laravel`:

```bash
composer install
npm install
copy .env.example .env
php artisan key:generate
```

Sửa `.env`: điền `DB_PASSWORD` và `GEMINI_API_KEY` trên máy local. Không commit file `.env`.

Database tên `webshop` là MySQL đã có schema của cửa hàng. Không chạy `php artisan migrate`, `migrate:fresh` hay `db:seed` trên database này. Migration mặc định của Laravel tạo bảng `users`, trong khi cửa hàng dùng bảng `user`.

Nếu vẫn chạy song song CodeIgniter, sao chép các file mẫu rồi điền giá trị local (các file thật đã nằm trong `.gitignore`):

```bash
copy application\config\database.php.example application\config\database.php
copy application\config\ai.php.example application\config\ai.php
copy application\config\mail.local.example.php application\config\mail.local.php
```

`ai.php` đọc `GEMINI_API_KEY` từ môi trường. Không dán key vào source.

## Không đưa lên Git

- `.env` và mật khẩu database, SMTP, API key
- `vendor/`, `node_modules/`
- dump SQL trong `databaseaddphpmyadmin/`
