# Production readiness

Checklist cho webshop Laravel. Môi trường đang chạy local là `https://webshop.comm`, web root `laravel/public`. File này không đổi cấu hình đang chạy.

## Đang chạy local

- `APP_ENV=local`
- `APP_DEBUG=true`
- `APP_URL=https://webshop.comm`
- Debug bật vì đây vẫn là máy local ServBay, không phải bản deploy production.

## Trước khi deploy production

1. Đặt `APP_ENV=production`.
2. Đặt `APP_DEBUG=false`. Laravel khi debug bật có thể lộ stack trace, đường dẫn và câu query.
3. `APP_URL` phải là HTTPS của domain thật. Không hard-code domain trong business logic.
4. `APP_KEY` giữ bí mật, không commit, không đưa vào log.
5. Thông tin database chỉ nằm trong environment.
6. API key và secret (mail, AI, SePay, storage) chỉ nằm trong environment. `SEPAY_ENABLED` chỉ bật khi đã có `SEPAY_WEBHOOK_SECRET`.
7. `Strict-Transport-Security` chỉ bật khi toàn site đã ổn định trên HTTPS, không còn endpoint HTTP cần giữ.
8. `Content-Security-Policy` chỉ bật sau khi đã rà script, style và tài nguyên ngoài (font, CDN, bản đồ, QR). Header hiện có: `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`.
9. Queue: cấu hình mặc định đọc `QUEUE_CONNECTION`. Nếu production không dùng `sync`, phải chạy queue worker. Không có job nghiệp vụ bắt buộc worker ở local.
10. Scheduler: project không đăng ký scheduled job. Lệnh `inspire` trong `routes/console.php` là mẫu của Laravel, không cần cron.
11. Backup database `webshop` trước mọi thay đổi schema.
12. Quyền ghi cho `storage/` và `bootstrap/cache/`. Ảnh sản phẩm nằm ở `D:\webshop\upload`, ngoài `laravel/public`.
13. Theo dõi `storage/logs/laravel.log`. Không log mật khẩu, API key, token.
14. Chứng chỉ HTTPS còn hạn.
15. Nginx document root phải là `laravel/public`. Không trỏ lại thư mục gốc CI3.
16. Không public các đường sau: `.env`, `storage` nội bộ, source, `vendor`.
17. Không chạy `migrate:fresh`, `migrate:refresh`, `db:wipe`, `db:seed` trên database production nếu chưa có kế hoạch migration riêng. Schema shop là schema hiện có, không phải bộ migration mặc định của Laravel.
18. `APP_DEBUG` production phải là `false`.

## Giữ nguyên khi vận hành

- Giỏ hàng dùng session `custom_cart`.
- Source CodeIgniter đã gỡ khỏi runtime. Rollback dùng archive `ci3-archive-*.zip` ở thư mục gốc, không trỏ Nginx về đó. Laravel vẫn đọc `application/config/branding.local.php` cho logo và `application/config/database.php` cho script CLI cũ.
- Đăng xuất khách chỉ POST có CSRF. `GET /user/logout` không đăng xuất.
- Đăng nhập khách và admin đã giới hạn 5 lần / 15 phút trong controller.
- Đăng ký, quên mật khẩu và đặt lại mật khẩu: 5 request/phút theo IP và email.
- Gửi tin AI: 30 request/phút theo user.
