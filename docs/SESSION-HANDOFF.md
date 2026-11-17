# SESSION-HANDOFF — Webshop

**Cập nhật:** 2026-03-19 (chốt ngày)  
**Phạm vi phiên:** Widget chat AI storefront (Dark Luxury) + mua hàng trong hội thoại + ghi chú handoff tự động.

---

## Trạng thái tổng quát

- Storefront Laravel; giỏ **`custom_cart`** (không đổi).
- Widget chat: đăng nhập bắt buộc; AI + chuyển nhân viên giữ nguyên luồng cũ.
- **Chưa commit git** (user chưa yêu cầu).

---

## Đã hoàn thành hôm nay

### 1. UI chat Dark Luxury

- `assets/site/css/ai-chat.css` — theme tối, header vàng nâu, bubble khách/AI, grid product 2 cột, chip gợi ý, mobile `100dvh` + safe-area.
- `assets/site/js/ai-chat.js` — format text (bỏ markdown thô), product cards, typing/history.
- `laravel/resources/views/layouts/storefront/partials/ai-widget.blade.php` — dot online, cache **css v5**, **js v11**.

### 2. Sản phẩm thật trong chat

- `laravel/app/Support/Storefront/AiProductCards.php` — tìm DB, ảnh/giá/KM/màu-size/tồn, URL PDP.
- `laravel/app/Services/Ai/ChatService.php` — ngữ cảnh + `meta.products` + lịch sử poll/history.
- Test: `laravel/tests/Feature/AI/AiProductCardsTest.php`, `AiChatTest` pass.

### 3. Điều hướng mua hàng trong chat

- `laravel/app/Support/Storefront/AiChatCommerce.php` — biến thể cho picker, **actions** theo ngữ cảnh tin nhắn.
- `GET ai-assistant/product/{id}/options` — size/màu/tồn từ DB.
- `POST gio-hang/them/{id}` — **hỗ trợ AJAX** (JSON + `total_items`), tái dùng `CartSessionService::add`.
- UI: Thêm vào giỏ → picker → xác nhận → tin hệ thống + nút Xem giỏ / Thanh toán; cập nhật badge giỏ header.
- Config widget: `cartUrl`, `checkoutUrl`, `ordersUrl`, `cartAddUrl`, `productOptionsUrl`.
- Test: `laravel/tests/Feature/AI/AiChatCommerceTest.php` (5 tests).

---

## File then chốt (sửa / thêm gần đây)

| Khu vực | File |
|--------|------|
| CSS chat | `assets/site/css/ai-chat.css` |
| JS chat | `assets/site/js/ai-chat.js` |
| Blade widget | `laravel/resources/views/layouts/storefront/partials/ai-widget.blade.php` |
| Product cards | `laravel/app/Support/Storefront/AiProductCards.php` |
| Commerce chat | `laravel/app/Support/Storefront/AiChatCommerce.php` |
| AI chat service | `laravel/app/Services/Ai/ChatService.php` |
| AI controller | `laravel/app/Http/Controllers/Storefront/AiAssistantController.php` |
| Cart AJAX add | `laravel/app/Http/Controllers/Storefront/CartController.php` |
| Routes | `laravel/routes/web.php` (`ai-assistant/product/{id}/options`) |
| Tests | `laravel/tests/Feature/AI/AiProductCardsTest.php`, `AiChatCommerceTest.php` |

---

## Handoff tự động sang nhân viên (FAQ vận hành)

**Không phải** do khách gõ «Gặp nhân viên».

Khi **Gemini `complete()` trả `ok: false` hoặc nội dung rỗng** và admin bật **«Khi AI không trả lời được → chuyển nhân viên»** (`fallback_to_staff`, mặc định bật) → tin «Xin lỗi, mình chưa thể trả lời…» + `waiting_staff`.

**Kiểm tra:** `GEMINI_API_KEY`, `GEMINI_MODEL`, `GEMINI_TIMEOUT` (chat mặc định 10s trong `config/ai.php`), log `laravel/storage/logs/laravel.log` (`ai.gemini`).

**Tắt tạm:** Admin → Cấu hình AI → bỏ chọn fallback sang nhân viên.

**Việc có thể làm sau (chưa làm):** Không auto-handoff khi đã có `products` trong ngữ cảnh; chỉ card + picker.

---

## Việc còn lại / kiểm thử thủ công

- [ ] Hard refresh storefront (`ai-chat.css?v=5`, `ai-chat.js?v=11`).
- [ ] Desktop + mobile: tìm SP, picker, thêm giỏ, xem giỏ, checkout link.
- [ ] Handoff nhân viên chủ động vs lỗi Gemini (sau khi sửa key/timeout).
- [ ] Đồng bộ production: asset trỏ `assets/site/` qua `Shop::assetUrl()`.

---

## Lệnh test nhanh

```bash
cd laravel
php artisan test --filter=AiChatTest
php artisan test --filter=AiProductCardsTest
php artisan test --filter=AiChatCommerceTest
```

---

## Quy ước agent

- Đọc file này khi user nói **tiếp tục** / **continue**.
- Không `git commit` trừ khi user yêu cầu rõ.