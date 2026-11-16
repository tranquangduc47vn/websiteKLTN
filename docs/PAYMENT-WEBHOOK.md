# Thanh toán chuyển khoản — webhook SePay

Trạng thái đơn (`transaction.status`) và trạng thái thanh toán (`transaction.payment_status`) là hai việc khác nhau.

| | Giá trị | Ý nghĩa |
|---|---|---|
| Đơn hàng | 0 mới, 1 đã xác nhận, 2 đã đóng gói, 3 hoàn thành, 4 hủy | Giữ nguyên máy trạng thái B6/B10. Webhook không đổi các mã này. |
| Thanh toán | `pending`, `paid`, `mismatch`, `review`, hoặc NULL | Chỉ đơn chuyển khoản mới được gán. COD để NULL. |

Điểm thưởng vẫn chỉ cộng khi admin chuyển đơn sang trạng thái 3. Voucher và tồn kho vẫn trừ một lần lúc tạo đơn. Webhook không gọi lại các bước đó.

## Luồng khách

1. Khách chọn `Chuyển khoản` tại `/thanh-toan`.
2. `CheckoutService` tạo đơn như cũ, rồi ghi `payment_ref = WEB{id}` và `payment_status = pending`.
3. Trang `/thanh-toan/checkout_qr/{id}` hiện QR VietQR. Số tiền là `transaction.amount` đã lưu (sau voucher). Nội dung chuyển khoản là `WEB{id}`.
4. Nếu khách đã đăng nhập và là chủ đơn, trang hỏi `GET /thanh-toan/payment-status/{id}` mỗi 4 giây, tối đa 10 phút. Ẩn tab thì dừng. Thành công, hủy, hoặc cần đối soát thì dừng.
5. Khách không đăng nhập vẫn xem được QR của phiên mình, nhưng không gọi được API trạng thái. Tải lại trang vẫn đọc trạng thái từ server.

Không có URL để khách tự đánh dấu đã thanh toán.

## Luồng webhook

`POST /payment/webhook/sepay`

Không login, không session khách, không CSRF. Header bắt buộc:

`Authorization: Apikey {SEPAY_WEBHOOK_SECRET}`

Khi `SEPAY_ENABLED` tắt hoặc secret trống, mọi request bị từ chối.

Webhook chỉ nhận giao dịch `transferType = in`. Nó lấy mã `WEB` + số từ `code` hoặc nội dung chuyển khoản, khóa dòng đơn, rồi:

- Không thấy mã, hoặc không phải đơn chuyển khoản: không sửa đơn.
- Đúng số tiền, đơn chưa hủy, chưa thanh toán: `payment_status = paid`, lưu mã giao dịch SePay và thời điểm.
- Sai số tiền: `mismatch`, không đánh dấu đã thanh toán.
- Đơn đã hủy: `review`, giữ status 4, không đánh dấu đã thanh toán.
- Cùng mã giao dịch SePay gửi lại: không ghi lần thứ hai.

## Cấu hình

Trong `laravel/.env` (không commit file này):

```
SEPAY_ENABLED=false
SEPAY_WEBHOOK_SECRET=
SEPAY_ACCOUNT_NUMBER=
SEPAY_BANK_CODE=
SEPAY_ACCOUNT_NAME=
```

`SEPAY_BANK_CODE` là mã VietQR, ví dụ `vietcombank`. Khi số tài khoản SePay được điền, QR dùng bộ này. Khi để trống, QR dùng `SHOP_VIETQR_*` như hiện tại.

Trên SePay: bật webhook trỏ tới `https://<domain>/payment/webhook/sepay`, chọn xác thực API Key, và điền cùng giá trị với `SEPAY_WEBHOOK_SECRET`.

## Chống ghi trùng

`payment_gateway_id` là unique. Trong transaction, dòng đơn bị `lockForUpdate`. Nếu mã SePay đã gắn vào đơn, hoặc đơn đã `paid`, lần sau chỉ trả về `duplicate`.

## Log

Kênh log ghi provider, mã SePay, payment reference, số tiền và lý do. Không ghi secret, header Authorization, hay mật khẩu.

## Database

Migration thêm cột nullable vào `transaction`:

- `payment_ref`
- `payment_status`
- `payment_gateway_id`
- `payment_paid_at`
- `payment_received_amount`

Không sửa các đơn cũ. Không chạy `migrate` full: migration mặc định của Laravel tạo bảng `users`, trong khi shop dùng bảng `user`.

Chỉ chạy file migration này:

```
php artisan migrate --path=database/migrations/2026_10_20_120000_add_bank_transfer_payment_columns_to_transaction_table.php --force
```

## Test

`tests/Feature/Order/PaymentWebhookTest.php` giả lập payload. Không gọi ngân hàng.

## Checklist production

- Điền secret và số tài khoản thật trong `.env` trên server, không đưa vào Git.
- Bật `SEPAY_ENABLED=true` sau khi URL webhook đã là HTTPS.
- Đối soát tay các bản ghi `mismatch` và `review`.
- Không tạo route để admin hoặc khách tự xác nhận đã thanh toán.
