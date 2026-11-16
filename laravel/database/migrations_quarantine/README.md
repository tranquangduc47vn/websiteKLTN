# Migration cách ly, không tự chạy

Các file trong thư mục này **không** nằm trong `database/migrations`, nên `php artisan migrate` không nạp chúng.

Chúng chưa từng có dòng trong bảng `migrations` của `webshop` (bảng đó đang trống). Schema V2 được tạo từ SQL, không từ các file này.

| File | Phân loại | Nếu chạy trên V2 |
|---|---|---|
| `0001_01_01_000000_create_users_table.php` | Skeleton Laravel, không tương thích | `Schema::create('users')` đụng bảng `users` đã có dữ liệu. Trên database trống sẽ tạo sai cột (`password` thay vì `password_hash`). |
| `0001_01_01_000001_create_cache_table.php` | Không dùng. Cache hiện là `file`. | Thêm bảng `cache` ngoài schema V2. |
| `0001_01_01_000002_create_jobs_table.php` | Không dùng. Queue hiện là `file`. | Thêm bảng `jobs` ngoài schema V2. |
| `2026_10_20_120000_add_bank_transfer_payment_columns_to_transaction_table.php` | Legacy CI3 | `Schema::table('transaction')` trong khi bảng đã đổi thành `orders` / `payments`. |

Không chuyển các file này trở lại `database/migrations`. Guard sẽ từ chối `migrate` nếu tên file nguy hiểm xuất hiện lại ở thư mục migration đang dùng.
