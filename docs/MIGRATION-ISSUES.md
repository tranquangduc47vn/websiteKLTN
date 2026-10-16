# Migration issues

Mức độ: `BLOCKER` / `HIGH` / `MEDIUM` / `LOW`. Trạng thái: `FIXED` / `OPEN` / `ACCEPTED` (cố ý giữ / cố ý lệch CI3).

## B5 — Storefront Cart

### Lỗi B4 phát hiện khi test giỏ hàng (đã sửa)

| ID | Mức | Trạng thái | Mô tả · Nguyên nhân · Xử lý |
|----|-----|-----------|------------------------------|
| ISSUE-B5-01 | BLOCKER | FIXED | **Mọi trang storefront trả 500 khi đã đăng nhập.** `StorefrontComposer::orderBadge()` lọc `transaction.email` (cột không tồn tại — đúng là `user_email`), dùng OR và `status NOT IN (3,99)`. Port lại đúng `Transaction_model::count_active_for_user`: `user_id` (chỉ dùng `user_email` khi không có id), `status IN (0,1,2)`. Test: `test_order_badge_counts_only_open_orders_of_current_user`. |
| ISSUE-B5-02 | HIGH | FIXED | **Flash không hiện trên layout.** `Flash::all()` pull flash ở view đầu tiên được compose, layout nhận rỗng. Memo theo request (`storefront.flash`). |
| ISSUE-B5-03 | HIGH | FIXED | **Modal tồn kho không hoạt động (PDP + giỏ).** Partial B4 dùng id `jm*`, `stock-notice-modal.js` cần `qdStockNotice*`. Thay bằng markup CI3. |
| ISSUE-B5-04 | MEDIUM | FIXED | Composer chạy lại toàn bộ query (menu, badge, giỏ) cho mỗi view/partial. Memo theo request (`storefront.shared`). |

### Sai lệch có chủ đích so với CI3

| ID | Mức | Trạng thái | Mô tả |
|----|-----|-----------|------|
| ISSUE-B5-05 | MEDIUM | ACCEPTED | **Rowid theo biến thể đã resolve.** CI3 tạo rowid từ size/màu client gửi trước khi chuẩn hóa → sản phẩm không khai báo màu/size: lần thêm sau không cộng số lượng cũ khi check tồn kho (oversell). Laravel tạo rowid từ size/màu của biến thể đã resolve (fallback giá trị đã chuẩn hóa). Format rowid không đổi: `{id}_md5("{size}_{color}")`. Test: `test_product_without_declared_options_respects_stock_on_repeat_add`. |
| ISSUE-B5-06 | LOW | ACCEPTED | **Khóa `variants` không ghi vào session.** CI3 `_sanitize_cart_session` làm rò khóa hiển thị `variants` vào `custom_cart`/`user_saved_cart`. Laravel chỉ gắn khi render. Giỏ cũ có sẵn `variants` vẫn đọc được. |

### Hành vi CI3 giữ nguyên (ghi nhận)

| ID | Mức | Trạng thái | Mô tả |
|----|-----|-----------|------|
| ISSUE-B5-07 | LOW | ACCEPTED | `GET /gio-hang/them/{id}` (link "thêm nhanh" sản phẩm liên quan) thêm vào giỏ không cần CSRF — như CI3. Chỉ thêm biến thể mặc định cho chính user đó, giá/tồn kho vẫn kiểm tra server. Muốn chặn: đổi link sang form POST (cần đổi view CI3-compatible). |
| ISSUE-B5-08 | LOW | ACCEPTED | Khi gộp dòng lúc thêm, CI3 chỉ cập nhật `subtotal` theo giá mới, không cập nhật `price`; mở trang giỏ sẽ đồng bộ lại. Flash `store_message_fail` hai lần trong một request → lần sau ghi đè (chỉ hiện thông báo tính lại giá). Flash hiện cả ở panel giỏ và layout. |
| ISSUE-B5-09 | LOW | ACCEPTED | `json_encode` không có `JSON_PRESERVE_ZERO_FRACTION` (như CI3) → `user_saved_cart` lưu `300000` thay vì `300000.0`; khôi phục sẽ là int cho tới khi mở trang giỏ. |

### Còn mở (ngoài phạm vi B5)

| ID | Mức | Trạng thái | Mô tả |
|----|-----|-----------|------|
| ISSUE-B5-10 | LOW | OPEN | `tests/Feature/ExampleTest` (scaffold) fail: `phpunit.xml` ép sqlite `:memory:` không có schema legacy. Test MySQL dùng `Tests\LegacyMysqlTestCase`. Đề xuất: xóa hoặc chuyển ExampleTest sang base MySQL. |
| ISSUE-B5-11 | MEDIUM | FIXED | `/user` và các action tài khoản (`UserController`) vẫn `abort(501)` → đã migrate ở B7. |
| ISSUE-B5-12 | MEDIUM | OPEN | Session CI3 và Laravel không chia sẻ: giỏ đang nằm trong session CI3 chỉ sang Laravel qua `user_saved_cart` (khi login). Root `.htaccess` vẫn route vào CI3; asset `assets/site` chưa có trong `laravel/public`. Cần quyết định khi cutover. |
| ISSUE-B5-13 | MEDIUM | OPEN | Logout B3 `session()->invalidate()` xóa cả session admin nếu admin dùng chung trình duyệt (CI3 chỉ unset key user). |
| ISSUE-B5-14 | LOW | OPEN | `Flash::inferType` ít từ khóa hơn bản CI3 → vài thông báo legacy có thể sai màu success/fail. |
| ISSUE-B5-15 | HIGH | FIXED | B6 Order đọc `custom_cart` qua `CartSessionService::sanitizeForCheckout` / `clear` — đã làm trong B6. |
| ISSUE-B5-16 | LOW | OPEN | Test chạy trong transaction (rollback, không còn dữ liệu test — đã kiểm tra) nhưng AUTO_INCREMENT của `user`/`product`/`product_variants`/`transaction` vẫn tăng. Không ảnh hưởng dữ liệu. |
| ISSUE-B5-17 | LOW | OPEN | Không chạy được `git status/diff` trong môi trường agent (lệnh treo). Kiểm tra phạm vi bằng mtime: thay đổi B5 chỉ trong `laravel/` + `docs/`. Một số file `application/` và `assets/` có mtime 2026-10-16 09:49/09:57 — trước lần ghi B5 đầu tiên, không do B5. Nên chạy `git status` thủ công để xác nhận. |
| ISSUE-B5-18 | LOW | OPEN | `product_message` flash (thông báo hết hàng khi thêm từ PDP) không hiển thị trên PDP — giống CI3 (view CI3 cũng không in). |

## B6 — Order + Checkout

| ID | Mức | Trạng thái | Mô tả · Nguyên nhân · Xử lý |
|----|-----|-----------|------------------------------|
| ISSUE-B6-01 | HIGH | FIXED | INSERT bảng `order` lỗi `Unknown column 'legacyTimestampCache'`. `BaseModel::newInstance()` gán property này trên model không dùng `LegacyTimestamps` → Eloquent nhét vào attributes. Khai báo `protected $legacyTimestampCache` trên `BaseModel`. |
| ISSUE-B6-02 | MEDIUM | FIXED | Hủy đơn / nút "Xem lịch sử" trên QR redirect `/user` (CI3). B7 đã hoàn thiện trang tài khoản + lịch sử + chi tiết đơn. |
| ISSUE-B6-03 | MEDIUM | ACCEPTED | **Xóa giỏ ngay sau khi tạo đơn** cho cả COD và chuyển khoản (chống double submit). CI3 chỉ xóa giỏ chuyển khoản khi mở trang QR. Trang QR vẫn gọi `clearCart` như CI3. |
| ISSUE-B6-04 | LOW | ACCEPTED | Payment lạ (không phải `COD` / `Chuyển khoản`) bị ép về COD. CI3 lưu nguyên chuỗi POST. |
| ISSUE-B6-05 | LOW | ACCEPTED | Form checkout dùng `old()` khi validate fail. CI3 ghi name/email/phone/address từ profile, không từ POST. |
| ISSUE-B6-06 | LOW | OPEN | VietQR dùng account placeholder CI3 (`vietcombank` / `1234567890` / `NGUYEN VAN A`) — đã đưa vào `config/shop.php`. Không phải cổng thanh toán thật; không có callback. |
| ISSUE-B6-07 | LOW | OPEN | Checkout khách xem được form nhưng giỏ luôn trống vì `SyncCartSession` (đúng `MY_Controller`). Guest checkout thực tế không đặt được đơn. |
| ISSUE-B6-08 | LOW | OPEN | Không có email đặt hàng — CI3 `Order.php` cũng không gửi. |
| ISSUE-B6-09 | LOW | OPEN | Không dùng bảng `user_address` / `user_phone` lúc checkout — CI3 chỉ lấy `user.name/email/phone/address`. |

## B7 — User Account / Order History

| ID | Mức | Trạng thái | Mô tả · Nguyên nhân · Ảnh hưởng · Workaround / bước tiếp |
|----|-----|-----------|----------------------------------------------------------|
| ISSUE-B7-01 | LOW | ACCEPTED | **Flash auth hiện hai lần** (panel tài khoản + layout). `Flash::all()` gộp `message_success/fail` với store flash; CI3 layout chỉ đọc store flash. Không sai thông báo. |
| ISSUE-B7-02 | LOW | OPEN | Link "Xem chính sách tích điểm" trỏ `/tich-diem` (đúng CI3) nhưng `LoyaltyController` storefront vẫn 501. Không chặn trang tài khoản. Bước sau: migrate trang loyalty. |
| ISSUE-B7-03 | MEDIUM | FIXED | **`user_address` là MyISAM** — `DatabaseTransactions` không rollback. Test B3/B7 để lại 31 dòng orphan. `CartTestCase::tearDown` xóa address/phone theo user test; đã dọn orphan. Không đổi engine (không sửa schema). |
| ISSUE-B7-04 | LOW | ACCEPTED | Guest gọi AJAX `order_detail` bị redirect HTML `/dang-nhap` (CI3 `_require_login`). JS `.fail()` hiện lỗi tải. Không 401 JSON. |
| ISSUE-B7-05 | MEDIUM | OPEN | File `assets/site/data/vn-hanh-chinh.json` không nằm trong snapshot workspace (có thể gitignore). `VnAddress` đọc path CI3; test dùng `tests/fixtures/vn-hanh-chinh.json`. Nếu file thiếu lúc deploy: thêm địa chỉ / đăng ký fail validate VN. Cần đảm bảo file có trên server. |
| ISSUE-B7-06 | LOW | ACCEPTED | `GET/POST /user/check_email`, `validate_vn_address`, `validate_user_phone`, `get_info_user` trả **404**. CI3 là callback `form_validation`, không phải API trang account. |
| ISSUE-B7-07 | MEDIUM | FIXED | `Hash::check()` (Laravel 12) ném exception nếu hash không phải bcrypt → đổi mật khẩu với MD5 legacy 500. `LegacyPassword::verify()` kiểm tra MD5 **trước** bcrypt. Login B3 dùng cùng helper nên cũng hết rủi ro này. |

## B8 — Admin Dashboard + Admin Framework

| ID | Mức | Trạng thái | Mô tả · Nguyên nhân · Ảnh hưởng · Bước tiếp |
|----|-----|-----------|-----------------------------------------------|
| ISSUE-B8-01 | LOW | FIXED | `GET /admin` trả 404 vì route storefront `{slug}` đăng ký trước nhóm admin và khớp slug `admin`. Đã loại `admin` khỏi `storefront.catalog.seo`. Login `/admin/login` không bị ảnh hưởng. |
| ISSUE-B8-02 | LOW | OPEN | Thông báo "Hệ thống" là dòng tĩnh "hoạt động bình thường". CI3 `admin_dashboard_notify_backup_line` quét file backup. Không chặn dashboard. Bước sau: chỉ thêm nếu có thư mục backup thật. |
| ISSUE-B8-03 | LOW | FIXED | Thanh export dashboard đã port ở B13 (`/admin/export/*/dashboard`). |
| ISSUE-B8-04 | LOW | OPEN | Trang admin có meta `no-store`. CI3 còn gửi header `Cache-Control` trên mọi response admin. Logout B3 đã gửi header. Trình duyệt vẫn có thể cache HTML dashboard. |
| ISSUE-B8-05 | LOW | ACCEPTED | Menu trỏ module chưa migrate và sau khi đủ quyền vẫn `abort(501)`. Sản phẩm/danh mục đã migrate ở B9. Đơn và khách hàng đã migrate ở B10. Kho đã migrate ở B11. Voucher admin đã migrate ở B12. Báo cáo và export đã migrate ở B13. Đánh giá sản phẩm đã migrate ở B14. AI và chat đã migrate ở B15. URL không có `admin_can` ở CI3 (users, …) cũng chỉ cần đăng nhập. |

## B9 — Admin Product + Catalog

| ID | Mức | Trạng thái | Mô tả · Nguyên nhân · Ảnh hưởng · Bước tiếp |
|----|-----|-----------|-----------------------------------------------|
| ISSUE-B9-01 | LOW | ACCEPTED | Bảng `product` / `catalog` không có cột slug. URL storefront B4 dựng từ tên + id. Tên trùng vẫn lưu được, đúng CI3. |
| ISSUE-B9-02 | LOW | ACCEPTED | Ảnh lưu tên do server sinh (`p` + thời gian + ngẫu nhiên), không dùng tên file gốc và không ghi đè. URL vẫn `/upload/product/{file}`. |
| ISSUE-B9-03 | LOW | ACCEPTED | Sửa danh mục không cho `parent_id` là chính nó hoặc danh mục con. CI3 cho phép, dễ tạo vòng lặp cây. |
| ISSUE-B9-04 | LOW | ACCEPTED | Sản phẩm mới để `rate_total` / `rate_count` = 0. Default schema là 4 / 1 (số giả). Không ảnh hưởng sản phẩm cũ. |
| ISSUE-B9-05 | LOW | FIXED | `admin_export_toolbar('products')` đã port ở B13. |
| ISSUE-B9-06 | LOW | FIXED | `/admin/product-reviews` đã migrate ở B14. |
| ISSUE-B9-07 | LOW | ACCEPTED | Xóa sản phẩm xóa luôn dòng `order`, review và biến thể, rồi xóa file ảnh. Đúng `Product::_delete_products_by_ids`. File chỉ xóa khi không còn sản phẩm khác tham chiếu. |

## B10 — Admin Order + Customer

| ID | Mức | Trạng thái | Mô tả · Nguyên nhân · Ảnh hưởng · Bước tiếp |
|----|-----|-----------|-----------------------------------------------|
| ISSUE-B10-01 | LOW | ACCEPTED | `accept` / `ship` / `complete` là GET, không có CSRF, vì view CI3 dùng thẻ `<a>`. POST hủy, từ chối xóa, xóa dòng bắt CSRF (thiếu token → 419). |
| ISSUE-B10-02 | LOW | ACCEPTED | Level 2 mở và POST-xóa được `/admin/users`. CI3 `User` không gọi `admin_can`. Sidebar vẫn ẩn menu Khách hàng với level 2, đúng `admin_sidebar_allowed`. |
| ISSUE-B10-03 | LOW | ACCEPTED | `User::deldetail` xóa dòng đơn, không hoàn kho và không trừ `transaction.amount`. Đúng controller khách hàng CI3. Xóa dòng trong quản lý đơn thì hoàn kho khi status 0 và trừ tổng. |
| ISSUE-B10-04 | LOW | ACCEPTED | Chi tiết đơn LEFT JOIN `product`. Dòng vẫn hiện `order.amount` / `qty` khi sản phẩm đã bị xóa. CI3 inner join làm mất dòng đó. |
| ISSUE-B10-05 | LOW | ACCEPTED | Danh sách khách có `?q=` lọc tên, email, SĐT, địa chỉ trên SQL. CI3 chỉ lọc trên trình duyệt. `q` rỗng vẫn trả cả bảng, không thêm phân trang. |
| ISSUE-B10-06 | LOW | FIXED | Thanh export đơn đã port ở B13. |
| ISSUE-B10-07 | LOW | ACCEPTED | Trang khách hiện thêm sổ `user_phone` (chỉ đọc). View CI3 chỉ có cột `user.phone`. Không thêm/sửa/xóa số. |

## B11 — Admin Inventory + Supplier + Receipt

| ID | Mức | Trạng thái | Mô tả · Nguyên nhân · Ảnh hưởng · Bước tiếp |
|----|-----|-----------|-----------------------------------------------|
| ISSUE-B11-01 | LOW | ACCEPTED | Dashboard vẫn cảnh báo `product.quantity <= 10`. Màn tồn kho dùng `stock <= min_stock`. Cả hai rule đều có trong CI3 (`admin_dashboard_helper` và `Inventory_model`). Không gộp hai ngưỡng. |
| ISSUE-B11-02 | LOW | FIXED | `admin_export_toolbar('inventory')` đã port ở B13. |
| ISSUE-B11-03 | LOW | ACCEPTED | Dòng phiếu không có `variant_id` chỉ tìm biến thể đang bán. CI3 `resolve_variant_id(..., auto_create=true)` có thể gọi `sync_variants`. Form admin luôn gửi `variant_id`. |
| ISSUE-B11-04 | LOW | ACCEPTED | Thêm nhà cung cấp bắt buộc mã và tên trên server. CI3 chỉ `required` ở HTML; POST trống vẫn có thể insert chuỗi rỗng. |

## B12 — Admin Voucher

| ID | Mức | Trạng thái | Mô tả · Nguyên nhân · Ảnh hưởng · Bước tiếp |
|----|-----|-----------|-----------------------------------------------|
| ISSUE-B12-01 | LOW | ACCEPTED | Mod/user bị chặn voucher bằng flash chung `Bạn không có quyền truy cập khu vực này!`. CI3 flash riêng `Bạn không có quyền quản lý voucher!`. Cùng redirect `/admin`, cùng chỉ level 0 vào được. |
| ISSUE-B12-02 | LOW | FIXED | `admin_export_toolbar('voucher')` đã port ở B13. |
| ISSUE-B12-03 | LOW | ACCEPTED | Xóa voucher không xóa đơn và không xóa `voucher_use`. Đúng `voucher_model->delete`. Đơn cũ vẫn giữ `voucher_code` đã lưu. |

## B13 — Admin Reports + Export

| ID | Mức | Trạng thái | Mô tả · Nguyên nhân · Ảnh hưởng · Bước tiếp |
|----|-----|-----------|-----------------------------------------------|
| ISSUE-B13-01 | LOW | ACCEPTED | Trang báo cáo hiện doanh thu 12 tháng bằng bảng. CI3 vẽ canvas Chart.js v1. Cùng 12 tổng status 3 của năm hiện tại, và cùng không ăn bộ lọc ngày. |
| ISSUE-B13-02 | LOW | ACCEPTED | Ô tiền/số trong Excel là số. CI3 ghi chuỗi đã format kèm ₫. Giá trị số không đổi. |
| ISSUE-B13-03 | LOW | ACCEPTED | Export khách là toàn bộ bảng `user`. Ô tìm trên `/admin/users` không áp vào file. `CustomerExport` CI3 cũng không đọc ô tìm. |
| ISSUE-B13-04 | LOW | ACCEPTED | Export sản phẩm lọc tên và danh mục. Lọc trạng thái trên màn sản phẩm không có trong `ProductExport` CI3 nên không áp vào file. |
| ISSUE-B13-05 | LOW | ACCEPTED | Giá trên bảng sản phẩm của báo cáo là `amount/qty` của một dòng (`LIMIT 1`, không `ORDER BY`), không có thì `product.price`. Đúng CI3. Doanh thu không dùng cột này. |
| ISSUE-B13-06 | LOW | ACCEPTED | Export chạy đồng bộ, không queue. CI3 cũng xuất ngay. Dữ liệu hiện tại nhỏ. |

## B14 — Product Reviews + Rating

| ID | Mức | Trạng thái | Mô tả · Nguyên nhân · Ảnh hưởng · Bước tiếp |
|----|-----|-----------|-----------------------------------------------|
| ISSUE-B14-01 | LOW | ACCEPTED | Flash duyệt/từ chối/xóa dùng `message_success`. CI3 dùng `admin_flash_info`. Layout admin Laravel chỉ hiện success/fail, nên chữ vẫn hiện đúng. |
| ISSUE-B14-02 | LOW | ACCEPTED | Danh sách công khai tối đa 50, admin tối đa 100, không phân trang. Đúng `get_by_product` và `list_admin` của CI3. |

## B15 — AI / Chat

| ID | Mức | Trạng thái | Mô tả · Nguyên nhân · Ảnh hưởng · Bước tiếp |
|----|-----|-----------|-----------------------------------------------|
| ISSUE-B15-01 | LOW | FIXED | Source CI3 không còn literal key. `application/config/ai.php` đọc `getenv('GEMINI_API_KEY')`. File nằm trong `.gitignore`, `git ls-files` không thấy. Laravel đọc `GEMINI_API_KEY` từ `.env`. `.env` không commit. Vẫn nên ROTATE/REVOKE trên Google vì key từng nằm plaintext trên đĩa máy local. |
| ISSUE-B15-02 | LOW | ACCEPTED | Server từ chối tin dài hơn 500 ký tự. Widget CI3 đã có `maxlength="500"` nhưng controller CI3 không kiểm tra. Thêm để chặn request quá lớn. |
| ISSUE-B15-03 | LOW | OPEN | Chưa gọi Gemini thật. Key nằm trong `.env` local (không commit). Test vẫn dùng `Http::fake()`. |
| ISSUE-B15-04 | LOW | ACCEPTED | Flash lưu cấu hình / FAQ / xóa chat dùng `message_success`. CI3 dùng `admin_flash_info`. Layout admin Laravel chỉ hiện success/fail. |
| ISSUE-B15-05 | LOW | ACCEPTED | Dòng hệ thống "Đang kết nối nhân viên..." bỏ emoji. Cột `ai_message` là utf8mb3 nên emoji 4 byte không ghi được. |
| ISSUE-B15-06 | LOW | ACCEPTED | Prompt không kèm đoạn HTML trang giới thiệu và địa chỉ cửa hàng đầu tiên như `ShopKnowledgeProvider` CI3. Vẫn có chính sách tĩnh, danh mục, đơn của đúng user đang đăng nhập, và sản phẩm công khai (không `cost_price`). |

## B16 — Admin nhân viên

| ID | Mức | Trạng thái | Mô tả · Nguyên nhân · Ảnh hưởng · Bước tiếp |
|----|-----|-----------|-----------------------------------------------|
| ISSUE-B16-01 | LOW | ACCEPTED | Level ngoài 0, 1, 2 bị từ chối. CI3 ghi thẳng số post vào cột `level`. Chặn để không tạo role lạ. |
| ISSUE-B16-02 | LOW | ACCEPTED | Flash thêm/sửa/xóa dùng `message_success`. CI3 dùng `admin_flash_success`. Layout admin Laravel chỉ hiện success/fail. |
| ISSUE-B16-03 | LOW | FIXED | `LegacyPassword::strengthMessage` trả chuỗi `\uXXXX` thô vì PHP không đọc escape đó. Đã đổi thành tiếng Việt đúng câu CI3. |

## B17 — Tin tức

| ID | Mức | Trạng thái | Mô tả · Nguyên nhân · Ảnh hưởng · Bước tiếp |
|----|-----|-----------|-----------------------------------------------|
| ISSUE-B17-01 | LOW | ACCEPTED | `?sort=price_asc` không đổi thứ tự. CI3 xếp theo cột `price` nhưng `content_pages` không có cột đó, câu SQL sẽ lỗi. Laravel giữ xếp `id DESC`. |
| ISSUE-B17-02 | LOW | FIXED | Intro vẫn escape. `content` là rich text của admin. B17.1 lọc server-side bằng `HtmlSanitizer` khi lưu và khi hiển thị. Tag an toàn giữ nguyên. Script và event handler bị bỏ. Không ghi đè hàng cũ trong database. |
| ISSUE-B17-03 | LOW | ACCEPTED | Ảnh không hợp lệ thì không tạo bài. CI3 có nhánh lưu nguyên tên file upload khi thư viện trả về rỗng. Laravel không nhận tên file từ client. |
| ISSUE-B17-04 | LOW | ACCEPTED | Flash admin dùng `message_success`. CI3 dùng `admin_flash_success`. |

## B17.1 — Priority cleanup

| ID | Mức | Trạng thái | Mô tả · Nguyên nhân · Ảnh hưởng · Bước tiếp |
|----|-----|-----------|-----------------------------------------------|
| ISSUE-B17.1-01 | MEDIUM | OPEN | Admin banner và slider vẫn `abort(501)`. Home đã đọc hai bảng. Upload, quyền và thứ tự đủ lớn nên không làm trong B17.1. Bước tiếp: B18. |
| ISSUE-B17.1-02 | LOW | OPEN | `ProductController` khoảng 790 dòng và `ReportService` khoảng 706 dòng. Chưa tách vì B17.1 cấm refactor lớn. |
| ISSUE-B17.1-03 | LOW | OPEN | Admin trang tĩnh (`PagesController`) và các trang cửa hàng, vận chuyển, tuyển dụng, hợp tác, logo, tích điểm vẫn 501. Storefront `/gioi-thieu` đã chạy. |
