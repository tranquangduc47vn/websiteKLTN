# PROJECT CONTEXT — Webshop Laravel (đồ án tốt nghiệp)

> Tài liệu tham chiếu ban đầu cho mọi phiên làm việc tiếp theo. **Luôn đối chiếu với code và DB thực tế** — file này không thay thế việc đọc source.

- **Cập nhật:** 2026-11-16 (khảo sát read-only, không migrate / không ghi DB live)
- **Đường dẫn:** `D:\webshop\laravel`
- **Domain local:** https://webshop.comm (ServBay, docroot `laravel/public`)
- **Stack:** Laravel 12, PHP 8.2+, MySQL 8, Blade, asset tĩnh tại `D:\webshop\assets`, upload tại `D:\webshop\upload`

---

## A. Tổng quan kiến trúc hiện tại

### A.1 Cấu trúc repository

| Thành phần | Vị trí | Ghi chú |
|------------|--------|---------|
| Ứng dụng Laravel | `laravel/` | Runtime chính; Nginx trỏ `laravel/public` |
| Asset storefront/admin | `assets/` | Không phụ thuộc Vite build; junction `laravel/public/assets` khi dev `artisan serve` |
| Upload ảnh | `upload/` | Ngoài `public`; branding đọc `application/config/branding.local.php` (legacy path, vẫn dùng) |
| CI3 source | Đã xóa khỏi working tree | Rollback: `ci3-archive-*.zip` ở repo gốc; `webshop_legacy_pre_v2` trên MySQL |
| Báo cáo audit/migration | `databaseaddphpmyadmin/*.md` | 27 file phase 8–10, V2 cutover, validation |
| Docs Laravel | `laravel/docs/`, `docs/MIGRATION-PROGRESS.md` | `docs/SESSION-HANDOFF.md`, `docs/PROJECT-STATUS.md` **không còn** (AGENTS.md vẫn trỏ tới) |

### A.2 Tầng ứng dụng

```
HTTP (web.php, session + CSRF)
  ├─ Storefront controllers (Home, Product, Cart, Order, User, News, Legacy, Seo, AI…)
  ├─ Admin group prefix admin + middleware admin.auth
  └─ SepayWebhookController (POST webhook, không session storefront)

Middleware web: SyncCartSession (đồng bộ custom_cart ↔ user_saved_carts)

Services (~26): CartSessionService, CheckoutService, OrderAdminService, InventoryService,
  StockReceiptService, VoucherService, ReportService, AiAssistantService, SiteLogoService, …

Models (32 Eloquent): map 32 bảng schema V2 (orders qua model Transaction, payments, stock_*, AI, …)

Views: ~93 Blade (storefront qD Design + admin shell 10D.x)

Tests: 34 file Feature/Unit, base LegacyMysqlTestCase + DatabaseTransactions
```

### A.3 Routing và tương thích CI3

- **Nguồn:** `laravel/routes/web.php` (~666 dòng), comment ghi **132/132 URL CI3** đã map.
- **Nguyên tắc:** thứ tự route có ý nghĩa; catch-all cuối file; giữ hoa/thường (`VanChuyen`, `hethongcuahang`, …).
- **AJAX:** giữ trong `web.php` (session + CSRF), không tách `api.php`.
- **Legacy:** `LegacyController` 301 `/cart` → `/gio-hang`, v.v.

### A.4 Authentication & phân quyền

**Storefront:** đăng ký/đăng nhập/đăng xuất (POST logout có CSRF), quên mật khẩu, throttle 5 lần/15 phút; đăng ký/reset 5 req/phút theo IP+email.

**Admin:** guard riêng, model `Admin`, level **0 = Admin, 1 = Mod, 2 = User** (`AdminPermission`). Sidebar/KPI/mutation theo `can()` — ví dụ chỉ level 0 quản voucher, xóa sản phẩm, grant admin; Mod+Admin quản nội dung/kho cơ bản; User chủ yếu dashboard + xem kho theo quyền.

### A.5 Giỏ hàng & đơn hàng (quyết định bắt buộc)

- Session **`custom_cart`** (không dùng `CI_Cart` / Laravel cart package).
- Cấu trúc key `"{product_id}_".md5("{size}_{color}")` — chi tiết trong `docs/MIGRATION-PROGRESS.md` B5.
- Đăng nhập: `user_saved_carts.cart_json` đồng bộ qua `CartSessionService` + `SyncCartSession`.
- Checkout: **bắt buộc đăng nhập**; guest checkout **cố ý không làm** (DECIDED MISSING).
- Thanh toán: **COD** + **QR/chuyển khoản** (`checkout_qr`, `payments.method = bank_transfer`); webhook **SePay** (`SepayWebhookController`, test `PaymentWebhookTest`).
- Hủy đơn: hoàn tồn kho + loyalty (`OrderTest`, `AccountTest` IDOR).

### A.6 Database V2

- Live: database **`webshop`**, **32 bảng** InnoDB utf8mb4, cutover **SUCCESS** (2026-10-21) — xem `databaseaddphpmyadmin/v2_live_cutover_report.md`.
- Bảng `migrations` có mặt nhưng **0 dòng** trên live (schema import SQL, không qua `artisan migrate`).
- **`webshop_legacy_pre_v2`**: rollback schema cũ (30 bảng).
- **`webshop_phase2_audit`**: DB PHPUnit (transactions rollback); baseline test **180 tests / 1941 assertions** (các báo cáo 10C–10D.3).

### A.7 An toàn migration

- File skeleton Laravel + migration `transaction` cũ: **`database/migrations_quarantine/`**.
- Chỉ còn baseline: `2026_10_21_210000_establish_v2_schema_baseline.php` (+ `V2SchemaBaseline`, `MigrationSafetyGuard`).
- `php artisan migrate` trên `webshop` → **MigrationBlockedException** (đã kiểm chứng phase 10B/10D.3).

---

## B. Những gì đã hoàn thiện (xác minh qua test + audit)

Ma trận chi tiết: `databaseaddphpmyadmin/phase10a_feature_matrix.md`. Tóm tắt:

| Nhóm | Trạng thái | Bằng chứng chính |
|------|------------|------------------|
| Storefront: home, catalog, PDP, filter, SEO, tin tức, trang tĩnh | IMPLEMENTED | `UrlCompatibilityTest`, `StorefrontRegressionTest`, `LegacyPagesTest` |
| Auth tài khoản, hồ sơ, địa chỉ, điện thoại | IMPLEMENTED | `AccountTest`, `AccountRegressionTest`, IDOR tests |
| Giỏ + sync `user_saved_carts` | IMPLEMENTED | `CartTest`, `CartSessionService` |
| Checkout COD/QR, voucher, loyalty | IMPLEMENTED | `OrderTest`, `VoucherTest` |
| Lịch sử đơn, hủy, payment status | IMPLEMENTED | `AccountTest`, `PaymentWebhookTest` |
| Admin: sản phẩm/danh mục/biến thể/màu/size | IMPLEMENTED | `ProductCatalogTest` |
| Admin: kho, phiếu nhập, movements, NCC | IMPLEMENTED | `InventoryTest` |
| Admin: đơn, KH, voucher, báo cáo export PDF/Excel | IMPLEMENTED | `OrderCustomerTest`, `VoucherTest`, `ReportExportTest` |
| Admin: banner/slider/news/pages/branding/stores/content | IMPLEMENTED | `ContentModulesTest` |
| Admin: nhân sự 0/1/2, reviews, AI FAQ/settings, support chat | IMPLEMENTED | `StaffTest`, `AiChatTest`, `AiSecurityTest` |
| AI storefront widget | IMPLEMENTED (provider thật NOT_VERIFIED) | Feature tests mock/null provider |
| Reviews storefront | IMPLEMENTED | `ReviewTest` (cho phép review không bắt buộc mua — khớp CI3/test) |
| V2 cutover + migration guard | IMPLEMENTED | `v2_live_cutover_report.md`, `MigrationSafetyTest` |
| Phase 10C: CSRF giỏ (POST-only add), mass assignment core models | IMPLEMENTED | `phase10c_business_feature_completion.md` PASS |

### Mốc lịch sử — đối chiếu thực tế

| Mốc | Kết luận khảo sát |
|-----|-------------------|
| B1 Database/Models | **Xong** — 32 model, schema V2 |
| B2 Routes URL cũ | **Xong** — comment 132/132 trong `web.php` |
| B3 Auth storefront | **Xong** — `MIGRATION-PROGRESS` DONE |
| B4–B17.* | **Xong với issue đã ghi** — xem `MIGRATION-PROGRESS.md` + `MIGRATION-ISSUES.md` |
| V2 migration & cutover | **SUCCESS** — 2026-10-21 |
| Phase 8C (UI/visual, Chart.js 1.x admin) | **PASS** — `post_cutover_visual_phase8c.md` |
| Phase 9C (dọn migration tạm) | **Hoàn thành** — `post_cutover_cleanup_phase9c.md` |
| Phase 10B (migration safety) | **PASS** |
| Phase 10C (business hardening) | **PASS** |
| Phase 10D.1 (admin shell) | **PASS** |
| Phase 10D.2 (admin module quản lý chính) | **PASS** |
| Phase 10D.3 (admin module còn lại) | **PASS_WITH_WARNINGS** — logo preview path |
| Phase 10D.4 (admin polish) | **PASS_WITH_WARNINGS** — `databaseaddphpmyadmin/phase10d4_admin_polish.md` |

---

## C. Đang triển khai dở dang

1. **Git working tree lớn (~774 path, 725 file diff stat)** — xóa toàn bộ `application/`, `system/` CI3; thêm Laravel migrations quarantine, Payment, test V2, admin UI 10D, `assets/admin/admin-manage.*`. **Chưa commit** (theo yêu cầu chủ repo).
2. **Phase 10D.4** — đã đóng báo cáo (`phase10d4_admin_polish.md`); PHPUnit **180/1941**; migrate live vẫn blocked; browser: voucher trùng OK, đơn #889 ICT khớp code (cảnh báo +7h nếu DB local). Logo live: path `site-logo-202611161433009742cbed.png` sau upload admin — không còn `site-logo.png` trên disk.
3. **Production checklist** (`laravel/docs/PRODUCTION_READINESS.md`) — viết xong, **chưa áp `.env` production** (APP_DEBUG vẫn true local).
4. **Roadmap 10E–10G** — chưa bắt đầu (tách controller, cache query, deploy).

---

## D. Chưa triển khai / cố ý không làm

| Hạng mục | Ghi chú |
|----------|---------|
| Guest checkout | DECIDED MISSING |
| Đổi điểm lấy thưởng (redeem) | DECIDED MISSING |
| Phiếu xuất kho (màn riêng) | Không có trong CI3/Laravel; chỉ `stock_movements` type `out` |
| Lưu ảnh cloud-first | Hướng tương lai; hiện filesystem `upload/` |
| Mobile storefront 390px | NOT_VERIFIED (phase 8C) |
| Redis / queue worker nghiệp vụ | Queue mặc định sync/file |
| `docs/SESSION-HANDOFF.md` | Thiếu — nên tái tạo hoặc thay bằng file này |

**Chỉ UI / chưa xác minh end-to-end:** mobile admin; AI provider production (API key thật); SePay trên live khi `SEPAY_ENABLED` (phụ thuộc secret).

---

## E. Lỗi kỹ thuật và rủi ro

### E.1 CRITICAL / HIGH

| Rủi ro | Mô tả |
|--------|--------|
| **Logo admin / demo** | Sau upload 2026-11-16, `branding.local.php` trỏ `site-logo-202611161433009742cbed.png`; `site-logo.png` (git restore) **không còn**. Cần quyết định logo qD cho đồ án. |
| **Working tree chưa commit** | Mất CI3 trong tree + hàng loạt thay đổi Laravel: rủi ro mất mát / khó rollback nếu không backup/commit có kế hoạch. |
| **Migrate trên live** | Đã guard; vẫn cần tránh `composer setup` migrate trên máy trỏ `webshop` (đã ghi README/10B). |

### E.2 MEDIUM

- `APP_ENV=local`, `APP_DEBUG=true` trên site đang chạy (`PRODUCTION_READINESS.md`).
- `Flash.php` có đoạn ghi debug `storage/logs/qa-flash-pull.log` — nên gỡ trước production.
- Logo preview admin từng FAIL khi thiếu junction `public/assets` hoặc path upload khi `artisan serve`.
- PHPUnit **bắt buộc** DB `webshop_phase2_audit` — xóa DB này sẽ gãy CI local.
- Nhiều model admin/content vẫn `$guarded = []` (ngoài phạm vi 10C) — rủi ro mass assignment tương lai.

### E.3 LOW / tech debt

- Chart.js admin **1.0.1-beta.4** — đã chỉnh API `.Line()`; không nâng version trong roadmap.
- Comment route `web.php` vẫn nói "Controller chưa implement (B3+)" — lỗi thời.
- `GET /admin/logout` không logout (cố ý) — cần nhớ khi test thủ công.

---

## F. Tình trạng database và dữ liệu

| Database | Vai trò | Trạng thái |
|----------|---------|------------|
| `webshop` | Live local | 32 bảng V2; cutover 2026-10-21; fingerprint gần nhất trong audit: orders **28**, migrations **0**, admins **5** (trước test admin UI) |
| `webshop_legacy_pre_v2` | Rollback | Giữ nguyên, không xóa |
| `webshop_phase2_audit` | Test | Dữ liệu cao hơn live sau test (orders 42, admins 10 trong báo cáo 10D.3 — chỉ audit) |

**Backup:** `databaseaddphpmyadmin/webshop_live_pre_v2_cutover_*.sql`, các báo cáo Phase 8A checksum.

**Quy tắc vận hành:** không `migrate:fresh`, không `db:wipe` trên live; không GET PDP trên live khi audit (tăng `view_count`).

---

## G. Tình trạng kiểm thử

| Hạng mục | Chi tiết |
|----------|----------|
| Framework | PHPUnit, `LegacyMysqlTestCase`, `DatabaseTransactions` |
| Số lượng | **34** file test; baseline **180 passed, 1941 assertions** (báo cáo 10C–10D.3 trên `webshop_phase2_audit`) |
| Phạm vi | Cart/CSRF, Order/Payment webhook, Account/Auth throttle, Admin modules, URL legacy, Migration safety, AI security, V2 timestamp, Model fillable |
| Lần chạy khảo sát 2026-11-16 | `php artisan test` được khởi chạy; **kết quả đầy đủ chưa thu xong trong phiên** — dùng baseline audit nếu cần quyết định ngay |
| E2E browser | Phase 8C/10D dùng snapshot có giới hạn; mobile NOT_VERIFIED |

---

## H. Tiến độ giao diện

### H.1 Storefront (qD Design)

- Blade + CSS trong `assets/site/`; layout `layouts/storefront/*`.
- Phase 8C đã sửa title search, filter, responsive một phần; **không redesign**.
- Add-to-cart: form **POST** + CSRF (10C); không còn GET thêm giỏ.

### H.2 Admin

| Phase | Nội dung |
|-------|----------|
| 10D.1 | Sidebar, topbar, breadcrumb, offcanvas, Chart revenue fix |
| 10D.2 | Card/badge/filter/table thống nhất: sản phẩm, kho, đơn, KH |
| 10D.3 | Voucher, banner/slider, news/pages, branding, suppliers, receipts, report, AI, reviews, staff, content modules, stores, support chat + `admin-manage.css/js` |
| 10D.4 | **PASS_WITH_WARNINGS** — báo cáo `phase10d4_admin_polish.md` |

Bootstrap 5 + `admin-theme.css`, Font Awesome CDN (admin only).

---

## I. Công việc tiếp theo (ưu tiên)

1. **Khôi phục `upload/branding/site-logo.png`** từ backup 8A / git (nếu còn trong commit `73bd221` hoặc archive) — trước demo đồ án.
2. **Quyết định logo demo** (qD restore vs giữ upload hiện tại).
3. **Quyết định commit:** gói cutover Laravel + xóa CI3 + 10D + migration guard — message rõ; không push trừ khi user yêu cầu.
4. **Tái tạo `docs/SESSION-HANDOFF.md`** ngắn trỏ `PROJECT_CONTEXT.md` + phase report mới nhất.
5. **Phase 10G prep:** APP_DEBUG false trên máy deploy, SePay secret, CSP/HSTS khi sẵn sàng (`PRODUCTION_READINESS.md`).
6. **Phase 10E** (tùy đồ án): tách `ProductController` / admin `handlePath` — giữ `UrlCompatibilityTest` xanh.
7. **Mobile audit** (nếu đồ án yêu cầu): checklist riêng, không đổi storefront lớn.
8. **Cloud storage ảnh:** thiết kế adapter; không đổi URL public đột ngột.

---

## J. Đề xuất lộ trình hoàn thiện đồ án tốt nghiệp

### J.1 Giai đoạn ổn định (1–2 tuần)

- Khóa dữ liệu live + backup mới có checksum.
- Commit snapshot “Laravel V2 + UI admin 10D.x”.
- Hoàn 10D.4 + sửa rủi ro logo/flash.
- Chạy full PHPUnit trên máy dev; ghi kết quả vào `phase10d4` hoặc appendix đồ án.
- Viết **tài liệu triển khai** ngắn: ServBay, docroot, DB tên, cấm migrate.

### J.2 Giai đoạn trình bày (đồ án)

- Kịch bản demo: đăng ký → duyệt sản phẩm → giỏ → voucher → COD/QR → admin duyệt đơn → báo cáo doanh thu.
- Slide kiến trúc: CI3 → Laravel, V2 schema, `custom_cart`, phân quyền 3 level, webhook tùy chọn.
- Nêu rõ **phạm vi không làm:** guest checkout, đổi điểm, phiếu xuất.

### J.3 Giai đoạn tùy chọn (nâng điểm)

- 10E refactor có test.
- 10F tối ưu query catalog.
- Mobile responsive pass có screenshot.
- Tích hợp storage cloud cho ảnh mới upload.

---

## Phụ lục — tài liệu nên đọc khi “tiếp tục”

1. **`PROJECT_CONTEXT.md`** (file này)
2. `docs/MIGRATION-PROGRESS.md` — tiến độ B3–B17
3. `databaseaddphpmyadmin/phase10a_feature_matrix.md`
4. Báo cáo phase mới nhất: `phase10d3_remaining_admin_ui.md` → tiếp `10D.4`
5. `laravel/docs/PRODUCTION_READINESS.md`
6. `laravel/routes/web.php` — URL map
7. `AGENTS.md` — **cần cập nhật** trỏ tới `PROJECT_CONTEXT.md`

---

## Phụ lục — quy tắc làm việc (tóm tắt từ chủ dự án)

- Đọc hiểu trước khi sửa; không đoán trạng thái.
- Giữ URL CI3, schema V2, nghiệp vụ giỏ/đơn/voucher/tồn kho.
- Không migrate/ghi DB live trừ kế hoạch rõ.
- Không đưa bảo mật về session legacy đã loại bỏ.
- Storefront qD: không redesign; admin: hiện đại hóa dần.
- Chỉ `git commit/push` khi user yêu cầu rõ.

---

*Báo cáo khảo sát tạo file này; không chỉnh sửa code nghiệp vụ trong phiên khảo sát (ngoại trừ tạo `PROJECT_CONTEXT.md`).*