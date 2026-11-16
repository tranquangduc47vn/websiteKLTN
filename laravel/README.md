# WebShop

Ứng dụng Laravel của cửa hàng. Hướng dẫn đầy đủ nằm ở [README gốc](../README.md).

## Cài đặt

```bash
composer install
npm install
copy .env.example .env
php artisan key:generate
```

Điền `DB_PASSWORD` và `GEMINI_API_KEY` trong `.env`. Không commit `.env`.

Database `webshop` đã có schema V2. `php artisan migrate` bị `MigrationSafetyGuard` chặn trên database này. Xem trạng thái bằng `php artisan webshop:migration-status`. Không chạy `migrate:fresh`. Database trống mới dùng baseline trong `database/schema/v2-baseline.sql`.
