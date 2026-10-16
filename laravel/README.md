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

Database `webshop` đã có schema. Không chạy `php artisan migrate` hoặc `migrate:fresh` trên database này.
