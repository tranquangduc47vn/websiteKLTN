<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\UserSavedCart;
use App\Services\Cart\CartActionResult;
use App\Services\Cart\CartPageData;
use App\Support\Storefront\Product as ProductHelper;
use App\Support\Storefront\Urls;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Collection;

/**
 * Giỏ hàng storefront (session `custom_cart`).
 *
 * Port từ CI3 application/controllers/Cart.php + application/helpers/cart_helper.php.
 *
 * Cấu trúc `custom_cart` PHẢI giữ y CI3 vì Order (CI3 lẫn Laravel) và `user_saved_cart`
 * đọc chung dữ liệu này:
 *
 *   [rowid => [
 *       'rowid' => "{product_id}_" . md5("{size}_{color}"),
 *       'id' => int, 'variant_id' => int, 'qty' => int,
 *       'price' => float, 'name' => string, 'subtotal' => float,
 *       'options' => ['image_link' => string, 'size' => string, 'color' => string],
 *   ]]
 *
 * Giá luôn lấy từ DB (`price - discount`, tối thiểu 1000 như CI3), không bao giờ từ client.
 */
class CartSessionService
{
    /** Session key giỏ hàng của CI3 — giữ nguyên theo AGENTS.md. */
    public const SESSION_KEY = 'custom_cart';

    /**
     * Giỏ đang đặt dở khi khách bị chuyển sang đăng nhập.
     * Middleware xóa custom_cart của guest; key này giữ lại để login nạp lại.
     */
    public const PENDING_CHECKOUT_KEY = 'checkout_pending_cart';

    /** Lựa chọn thêm giỏ của khách, được hoàn tất sau khi đăng nhập. */
    public const PENDING_ADD_KEY = 'pending_cart_add';

    /** CI3 Cart::_parse_product_variants(): giá trị hiển thị khi sản phẩm không khai báo màu/size. */
    public const DEFAULT_COLOR = 'Mặc định';
    public const DEFAULT_SIZE = 'Freesize';

    /** CI3: giá <= 0 thì ép về 1000. */
    public const FALLBACK_PRICE = 1000.0;

    public const NO_IMAGE = 'no-image.png';

    public const MSG_ADDED = 'Thêm vào giỏ hàng thành công';
    public const MSG_PRODUCT_MISSING = 'Sản phẩm không tồn tại trong hệ thống!';
    public const MSG_PRODUCT_INACTIVE = 'Sản phẩm này hiện không còn bán.';
    public const MSG_ROW_NOT_FOUND = 'Không tìm thấy sản phẩm trong giỏ hàng';
    public const MSG_PRODUCT_GONE = 'Sản phẩm không còn tồn tại';
    public const MSG_OPTIONS_UPDATED = 'Đã cập nhật size và màu sắc';
    public const MSG_INVALID_REQUEST = 'Yêu cầu không hợp lệ';
    public const MSG_ITEM_REMOVED = 'Xóa sản phẩm thành công';
    public const MSG_CART_CLEARED = 'Xóa giỏ hàng thành công';

    public function __construct(
        private readonly VariantStockService $stock,
        private readonly AuthFactory $auth,
    ) {
    }

    /* =====================================================================
     | Session + user_saved_cart (cart_helper.php)
     |====================================================================*/

    /**
     * CI3 cart_get_session(): luôn trả array.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getSessionCart(Session $session): array
    {
        $cart = $session->get(self::SESSION_KEY);

        return is_array($cart) ? $cart : [];
    }

    /**
     * Ghi session, KHÔNG đồng bộ DB.
     *
     * @param  array<string, array<string, mixed>>  $cart
     */
    public function putSessionCart(Session $session, array $cart): void
    {
        $session->put(self::SESSION_KEY, $cart);
    }

    /** CI3 unset_userdata('custom_cart'). */
    public function forgetSessionCart(Session $session): void
    {
        $session->forget(self::SESSION_KEY);
    }

    /**
     * CI3 cart_persist_session(): ghi session, và nếu đang đăng nhập thì đồng bộ `user_saved_cart`.
     *
     * @param  array<string, array<string, mixed>>  $cart
     */
    public function persist(Session $session, array $cart): void
    {
        $this->putSessionCart($session, $cart);

        $userId = (int) $this->auth->guard('web')->id();
        if ($userId > 0) {
            $this->syncToUser($session, $userId, $cart);
        }
    }

    /** CI3 cart_clear_session(). */
    public function clear(Session $session): void
    {
        $this->persist($session, []);
    }

    /**
     * CI3 cart_sync_to_user(): REPLACE 1 dòng / user (PK = user_id).
     *
     * @param  array<string, array<string, mixed>>|null  $cart  null = lấy từ session
     */
    public function syncToUser(Session $session, int $userId, ?array $cart = null): void
    {
        if ($userId <= 0) {
            return;
        }

        $cart ??= $this->getSessionCart($session);

        UserSavedCart::query()->upsert(
            [[
                'user_id' => $userId,
                'cart_json' => json_encode($cart, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_at' => now(),
            ]],
            ['user_id'],
            ['cart_json', 'updated_at']
        );
    }

    /**
     * CI3 cart_restore_for_user(): nạp giỏ đã lưu vào session.
     *
     * @return bool true nếu user có dòng `user_saved_cart` (kể cả giỏ rỗng)
     */
    public function restoreForUser(Session $session, int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $saved = UserSavedCart::query()->where('user_id', $userId)->first();

        if (! $saved || trim((string) $saved->cart_json) === '') {
            return false;
        }

        $session->put(self::SESSION_KEY, $saved->cartItems());

        return true;
    }

    /** CI3 cart_ensure_for_logged_in_user(): chỉ khôi phục khi giỏ session đang trống. */
    public function ensureForLoggedInUser(Session $session, User $user): void
    {
        if ((int) $user->id <= 0 || $this->getSessionCart($session) !== []) {
            return;
        }

        $this->restoreForUser($session, (int) $user->id);
    }

    /** CI3 build_cart_url(). */
    public function cartUrl(): string
    {
        return Urls::cart();
    }

    /* =====================================================================
     | Quy tắc dòng giỏ hàng (Cart.php private helpers)
     |====================================================================*/

    /** CI3 Cart::_build_rowid(): mỗi tổ hợp product + size + màu là một dòng. */
    public function rowId(int $productId, string $size, string $color): string
    {
        return $productId . '_' . md5($size . '_' . $color);
    }

    /**
     * CI3 Cart::_parse_product_variants(): lựa chọn hiển thị trên giỏ
     * (khác Product_service ở chỗ mặc định là 'Mặc định' / 'Freesize').
     *
     * @return array{colors: list<string>, sizes: list<string>}
     */
    public function variantOptions(Product $product): array
    {
        $colors = $product->colorList();
        $sizes = $product->sizeList();

        return [
            'colors' => $colors !== [] ? $colors : [self::DEFAULT_COLOR],
            'sizes' => $sizes !== [] ? $sizes : [self::DEFAULT_SIZE],
        ];
    }

    /**
     * CI3 Cart::_normalize_variant(): giá trị lạ → lựa chọn đầu tiên.
     *
     * @return array{size: string, color: string}
     */
    public function normalizeOptions(Product $product, string $size, string $color): array
    {
        $options = $this->variantOptions($product);
        $size = trim($size);
        $color = trim($color);

        if ($size === '' || ! in_array($size, $options['sizes'], true)) {
            $size = $options['sizes'][0];
        }
        if ($color === '' || ! in_array($color, $options['colors'], true)) {
            $color = $options['colors'][0];
        }

        return ['size' => $size, 'color' => $color];
    }

    /** Giá bán = list_price - discount_amount, không âm. */
    public function unitPrice(Product $product): float
    {
        return $product->finalPrice();
    }

    /**
     * CI3 Cart::_cart_totals().
     *
     * @param  array<string, array<string, mixed>>  $cart
     * @return array{total_items: int, total_price: float}
     */
    public function totals(array $cart): array
    {
        $items = 0;
        $price = 0.0;

        foreach ($cart as $item) {
            $items += (int) ($item['qty'] ?? 0);
            $price += (float) ($item['subtotal'] ?? 0);
        }

        return ['total_items' => $items, 'total_price' => $price];
    }

    /**
     * CI3 Order::_sanitize_cart(): làm sạch giỏ trước checkout.
     * Khác Cart::index(): không loại sản phẩm status ≠ 1 (chỉ dựa vào validate_cart_quantity).
     *
     * @return array{cart: array<string, array<string, mixed>>, removed: list<string>, changed: bool, subtotal: int, total: float}
     */
    public function sanitizeForCheckout(Session $session): array
    {
        $cart = $this->getSessionCart($session);
        $productIds = [];
        foreach ($cart as $item) {
            $id = is_array($item) ? (int) ($item['id'] ?? 0) : 0;
            if ($id > 0) {
                $productIds[$id] = $id;
            }
        }

        $products = $productIds === []
            ? collect()
            : Product::query()->whereIn('id', array_values($productIds))->get()->keyBy('id');
        $variants = $productIds === []
            ? collect()
            : ProductVariant::query()->whereIn('product_id', array_values($productIds))->get()->groupBy('product_id');

        $clean = [];
        $removed = [];
        $changed = false;

        foreach ($cart as $rowid => $item) {
            if (! is_array($item)) {
                $changed = true;
                continue;
            }

            $productId = (int) ($item['id'] ?? 0);
            if ($productId <= 0) {
                $changed = true;
                continue;
            }

            $product = $products->get($productId);
            if (! $product) {
                $removed[] = ! empty($item['name']) ? (string) $item['name'] : ('#' . $productId);
                $changed = true;
                continue;
            }

            $qty = max(1, (int) ($item['qty'] ?? 1));
            $check = $this->stock->validateCartQuantity(
                $product,
                (string) ($item['options']['color'] ?? ''),
                (string) ($item['options']['size'] ?? ''),
                $qty,
                0,
                $variants->get($productId, collect())
            );
            if (! $check['ok']) {
                $removed[] = (string) $product->name;
                $changed = true;
                continue;
            }

            $price = $this->unitPrice($product);
            $subtotal = $price * $qty;
            if (! isset($item['options']) || ! is_array($item['options'])) {
                $item['options'] = [];
            }

            $item['price'] = $price;
            $item['qty'] = $qty;
            $item['subtotal'] = $subtotal;
            $item['variant_id'] = (int) $check['variant_id'];
            $item['name'] = (string) $product->name;
            $item['options']['size'] = $check['size'];
            $item['options']['color'] = $check['color'];

            if ((float) ($cart[$rowid]['price'] ?? 0) !== $price
                || (float) ($cart[$rowid]['subtotal'] ?? 0) !== $subtotal
                || (int) ($cart[$rowid]['variant_id'] ?? 0) !== (int) $check['variant_id']) {
                $changed = true;
            }

            $clean[$rowid] = $item;
        }

        if (count($clean) !== count($cart)) {
            $changed = true;
        }

        if ($changed) {
            $this->persist($session, $clean);
        }

        $total = 0.0;
        foreach ($clean as $item) {
            $total += (float) ($item['subtotal'] ?? 0);
        }

        return [
            'cart' => $clean,
            'removed' => $removed,
            'changed' => $changed,
            'subtotal' => (int) $total,
            'total' => $total,
        ];
    }

    /* =====================================================================
     | Thao tác giỏ hàng
     |====================================================================*/

    public function rememberPendingAdd(Session $session, int $productId, int $qty, string $size, string $color): void
    {
        $session->put(self::PENDING_ADD_KEY, [
            'id' => $productId,
            'qty' => $qty,
            'size' => $size,
            'color' => $color,
        ]);
    }

    public function consumePendingAdd(Session $session): ?CartActionResult
    {
        $pending = $session->pull(self::PENDING_ADD_KEY);
        if (! is_array($pending) || (int) ($pending['id'] ?? 0) < 1) {
            return null;
        }

        return $this->add(
            $session,
            (int) $pending['id'],
            (int) ($pending['qty'] ?? 0),
            (string) ($pending['size'] ?? ''),
            (string) ($pending['color'] ?? ''),
        );
    }

    /** CI3 Cart::add(). */
    public function add(Session $session, int $productId, int $qty, string $size, string $color): CartActionResult
    {
        $cart = $this->getSessionCart($session);
        $qty = $qty > 0 ? $qty : 1;

        $product = $productId > 0 ? Product::query()->find($productId) : null;
        if (! $product) {
            return CartActionResult::failure(CartActionResult::PRODUCT_MISSING, self::MSG_PRODUCT_MISSING, $cart);
        }
        if ((string) $product->status !== Product::STATUS_ACTIVE) {
            return CartActionResult::failure(CartActionResult::PRODUCT_INACTIVE, self::MSG_PRODUCT_INACTIVE, $cart, [], $product);
        }

        $options = $this->normalizeOptions($product, $size, $color);
        $variant = $this->stock->resolveVariant($product, $options['color'], $options['size']);

        // rowid tính theo đúng size/màu sẽ được lưu (của biến thể), để số lượng đang có trong giỏ
        // được cộng vào khi kiểm tra tồn — CI3 tra bằng giá trị đã normalize nên bỏ sót với
        // sản phẩm không khai báo màu/size (xem docs/MIGRATION-ISSUES.md).
        $rowid = $variant
            ? $this->rowId($productId, $variant['size'], $variant['color'])
            : $this->rowId($productId, $options['size'], $options['color']);
        $existingQty = isset($cart[$rowid]) ? (int) $cart[$rowid]['qty'] : 0;

        $check = $this->stock->checkQuantity($product, $variant, $options['color'], $options['size'], $qty, $existingQty);
        if (! $check['ok']) {
            return CartActionResult::failure(CartActionResult::OUT_OF_STOCK, $check['message'], $cart, $check, $product);
        }

        $price = $this->unitPrice($product);

        if (isset($cart[$rowid])) {
            $cart[$rowid]['qty'] += $qty;
            $cart[$rowid]['variant_id'] = (int) $check['variant_id'];
            $cart[$rowid]['subtotal'] = $cart[$rowid]['qty'] * $price;
            $cart[$rowid]['options']['size'] = $check['size'];
            $cart[$rowid]['options']['color'] = $check['color'];
        } else {
            $cart[$rowid] = [
                'rowid' => $rowid,
                'id' => $productId,
                'variant_id' => (int) $check['variant_id'],
                'qty' => $qty,
                'price' => $price,
                'name' => (string) $product->name,
                'subtotal' => $price * $qty,
                'options' => [
                    'image_link' => ! empty($product->image_link) ? (string) $product->image_link : self::NO_IMAGE,
                    'size' => $check['size'],
                    'color' => $check['color'],
                ],
            ];
        }

        $this->persist($session, $cart);

        return CartActionResult::success($cart, self::MSG_ADDED, [], $product);
    }

    /** CI3 Cart::update_options(): đổi size/màu; trùng dòng khác thì gộp số lượng. */
    public function updateOptions(Session $session, string $rowid, string $size, string $color): CartActionResult
    {
        $cart = $this->getSessionCart($session);
        $rowid = trim($rowid);

        if ($rowid === '' || ! isset($cart[$rowid])) {
            return CartActionResult::failure(CartActionResult::NOT_FOUND, self::MSG_ROW_NOT_FOUND, $cart);
        }

        $item = $cart[$rowid];
        $product = Product::query()->find((int) ($item['id'] ?? 0));
        if (! $product) {
            return CartActionResult::failure(CartActionResult::PRODUCT_MISSING, self::MSG_PRODUCT_GONE, $cart);
        }

        $options = $this->normalizeOptions($product, $size, $color);
        $variant = $this->stock->resolveVariant($product, $options['color'], $options['size']);
        $newRowid = $variant
            ? $this->rowId((int) $item['id'], $variant['size'], $variant['color'])
            : $this->rowId((int) $item['id'], $options['size'], $options['color']);

        if ($newRowid === $rowid) {
            return CartActionResult::success($cart, '', [
                'action' => 'options',
                'old_rowid' => $rowid,
                'rowid' => $rowid,
                'qty' => (int) $item['qty'],
                'subtotal' => (float) $item['subtotal'],
                'size' => $options['size'],
                'color' => $options['color'],
                'product_name' => (string) $product->name,
            ], $product);
        }

        $movingQty = (int) $item['qty'];
        $existingTargetQty = isset($cart[$newRowid]) ? (int) $cart[$newRowid]['qty'] : 0;
        $check = $this->stock->checkQuantity($product, $variant, $options['color'], $options['size'], $movingQty, $existingTargetQty);

        if (! $check['ok']) {
            return CartActionResult::failure(CartActionResult::OUT_OF_STOCK, $check['message'], $cart, [
                'action' => 'options',
                'rowid' => $rowid,
                'product_id' => (int) $item['id'],
                'product_name' => (string) $product->name,
                'size' => $check['size'],
                'color' => $check['color'],
                'stock' => (int) $check['stock'],
            ], $product);
        }

        if (isset($cart[$newRowid])) {
            $mergedQty = $cart[$newRowid]['qty'] + $movingQty;
            $cart[$newRowid]['qty'] = $mergedQty;
            $cart[$newRowid]['variant_id'] = (int) $check['variant_id'];
            $cart[$newRowid]['subtotal'] = $mergedQty * $cart[$newRowid]['price'];
            $cart[$newRowid]['options']['size'] = $check['size'];
            $cart[$newRowid]['options']['color'] = $check['color'];
            unset($cart[$rowid]);
            $subtotal = (float) $cart[$newRowid]['subtotal'];
            $qty = (int) $mergedQty;
        } else {
            unset($cart[$rowid]);
            $item['rowid'] = $newRowid;
            $item['variant_id'] = (int) $check['variant_id'];
            $item['options']['size'] = $check['size'];
            $item['options']['color'] = $check['color'];
            $cart[$newRowid] = $item;
            $subtotal = (float) $item['subtotal'];
            $qty = (int) $item['qty'];
        }

        $this->persist($session, $cart);

        return CartActionResult::success($cart, self::MSG_OPTIONS_UPDATED, [
            'action' => 'options',
            'old_rowid' => $rowid,
            'rowid' => $newRowid,
            'qty' => $qty,
            'subtotal' => $subtotal,
            'size' => $check['size'],
            'color' => $check['color'],
            'product_name' => (string) $product->name,
        ], $product);
    }

    /** CI3 Cart::update(): action=sum tăng 1 (kiểm tồn), action=sub giảm 1 (về 0 thì xóa dòng). */
    public function changeQuantity(Session $session, string $rowid, string $action): CartActionResult
    {
        $cart = $this->getSessionCart($session);
        $rowid = trim($rowid);
        $action = trim($action);

        if ($rowid === '' || $action === '') {
            return CartActionResult::failure(CartActionResult::INVALID, self::MSG_INVALID_REQUEST, $cart);
        }
        if (! isset($cart[$rowid])) {
            return CartActionResult::failure(CartActionResult::NOT_FOUND, self::MSG_ROW_NOT_FOUND, $cart);
        }

        $item = $cart[$rowid];
        $product = Product::query()->find((int) ($item['id'] ?? 0));
        $productName = $product ? (string) $product->name : (string) ($item['name'] ?? '');
        $removed = false;

        if ($action === 'sum') {
            $check = $this->stock->validateCartQuantity(
                $product,
                (string) ($item['options']['color'] ?? ''),
                (string) ($item['options']['size'] ?? ''),
                1,
                (int) $item['qty']
            );

            if (! $check['ok']) {
                return CartActionResult::failure(CartActionResult::OUT_OF_STOCK, $check['message'], $cart, [
                    'action' => 'qty',
                    'rowid' => $rowid,
                    'product_id' => (int) $item['id'],
                    'product_name' => $productName,
                    'size' => $check['size'],
                    'color' => $check['color'],
                    'stock' => (int) $check['stock'],
                ], $product);
            }

            $cart[$rowid]['qty'] += 1;
            $cart[$rowid]['variant_id'] = (int) $check['variant_id'];
            $cart[$rowid]['subtotal'] = $cart[$rowid]['qty'] * $cart[$rowid]['price'];
        } elseif ($action === 'sub') {
            if ($cart[$rowid]['qty'] > 1) {
                $cart[$rowid]['qty'] -= 1;
                $cart[$rowid]['subtotal'] = $cart[$rowid]['qty'] * $cart[$rowid]['price'];
            } else {
                unset($cart[$rowid]);
                $removed = true;
            }
        }

        $this->persist($session, $cart);

        if ($removed) {
            return CartActionResult::success($cart, '', [
                'action' => 'qty',
                'rowid' => $rowid,
                'removed' => true,
            ], $product);
        }

        $item = $cart[$rowid];

        return CartActionResult::success($cart, '', [
            'action' => 'qty',
            'rowid' => $rowid,
            'removed' => false,
            'qty' => (int) $item['qty'],
            'subtotal' => (float) $item['subtotal'],
            'product_name' => $productName,
            'size' => $item['options']['size'] ?? '',
            'color' => $item['options']['color'] ?? '',
        ], $product);
    }

    /** CI3 Cart::del(): rowid rỗng / '0' / 'del' = xóa toàn bộ giỏ. */
    public function remove(Session $session, string $rowid): CartActionResult
    {
        $cart = $this->getSessionCart($session);
        $rowid = trim($rowid);
        $message = '';

        if ($rowid !== '' && $rowid !== '0' && $rowid !== 'del') {
            if (isset($cart[$rowid])) {
                unset($cart[$rowid]);
                $this->persist($session, $cart);
                $message = self::MSG_ITEM_REMOVED;
            }
        } else {
            $cart = [];
            $this->clear($session);
            $message = self::MSG_CART_CLEARED;
        }

        return CartActionResult::success($cart, $message, [
            'action' => 'delete',
            'rowid' => $rowid,
            'removed' => true,
            'empty' => $cart === [],
        ]);
    }

    /**
     * CI3 Cart::index(): _sanitize_cart_session() → _recalculate_cart_prices() → _enrich_cart_items().
     *
     * Sản phẩm + biến thể của cả giỏ được nạp bằng 2 query thay vì 3 lượt query / dòng như CI3.
     */
    public function preparePage(Session $session): CartPageData
    {
        $notices = [];
        $cart = $this->getSessionCart($session);

        $productIds = [];
        foreach ($cart as $item) {
            $id = is_array($item) ? (int) ($item['id'] ?? 0) : 0;
            if ($id > 0) {
                $productIds[$id] = $id;
            }
        }

        $products = $productIds === []
            ? collect()
            : Product::query()->whereIn('id', array_values($productIds))->get()->keyBy('id');
        $variants = $productIds === []
            ? collect()
            : ProductVariant::query()->whereIn('product_id', array_values($productIds))->get()->groupBy('product_id');

        // 1. _sanitize_cart_session(): bỏ dòng có sản phẩm đã bị xóa khỏi DB.
        $kept = [];
        foreach ($cart as $rowid => $item) {
            if (is_array($item) && $products->has((int) ($item['id'] ?? 0))) {
                $kept[$rowid] = $item;
            }
        }
        if (count($kept) !== count($cart)) {
            $this->persist($session, $kept);
            $notices[] = (count($cart) - count($kept)) . ' sản phẩm trong giỏ không còn bán và đã được xóa tự động.';
            $cart = $kept;
        }

        // 2. _recalculate_cart_prices(): giá + tồn theo DB, bỏ dòng ngừng bán / hết hàng.
        $recalc = $this->recalculate($cart, $products, $variants);
        $cart = $recalc['cart'];
        if ($recalc['changed']) {
            $this->persist($session, $cart);
        }
        if ($recalc['removed'] !== []) {
            $notices[] = 'Một số sản phẩm đã được cập nhật giá hoặc không còn khả dụng: ' . implode(', ', $recalc['removed']);
        }

        // 3. _enrich_cart_items(): thêm lựa chọn size/màu cho view.
        $items = [];
        foreach ($cart as $rowid => $item) {
            $product = $products->get((int) $item['id']);
            $item['variants'] = $this->variantOptions($product);
            $item['product_url'] = Urls::product($product);
            $item['image_url'] = ProductHelper::imageUrl($item['options']['image_link'] ?? '');
            $items[$rowid] = $item;
        }

        $totals = $this->totals($cart);

        return new CartPageData($items, $totals['total_items'], $totals['total_price'], $notices);
    }

    /**
     * CI3 Cart::_recalculate_cart_prices().
     *
     * @param  array<string, array<string, mixed>>  $cart
     * @param  Collection<int, Product>  $products
     * @param  Collection<int, Collection<int, ProductVariant>>  $variants
     * @return array{cart: array<string, array<string, mixed>>, removed: list<string>, changed: bool}
     */
    private function recalculate(array $cart, Collection $products, Collection $variants): array
    {
        $clean = [];
        $removed = [];
        $changed = false;

        foreach ($cart as $rowid => $item) {
            $productId = (int) ($item['id'] ?? 0);
            if ($productId <= 0) {
                $changed = true;
                continue;
            }

            $product = $products->get($productId);
            if (! $product || (string) $product->status !== Product::STATUS_ACTIVE) {
                $removed[] = ! empty($item['name']) ? (string) $item['name'] : ('#' . $productId);
                $changed = true;
                continue;
            }

            $qty = max(1, (int) ($item['qty'] ?? 1));
            $check = $this->stock->validateCartQuantity(
                $product,
                (string) ($item['options']['color'] ?? ''),
                (string) ($item['options']['size'] ?? ''),
                $qty,
                0,
                $variants->get($productId, collect())
            );
            if (! $check['ok']) {
                $removed[] = (string) $product->name;
                $changed = true;
                continue;
            }

            $price = $this->unitPrice($product);
            $subtotal = $price * $qty;

            if (! isset($item['options']) || ! is_array($item['options'])) {
                $item['options'] = [];
            }

            $item['price'] = $price;
            $item['qty'] = $qty;
            $item['subtotal'] = $subtotal;
            $item['variant_id'] = (int) $check['variant_id'];
            $item['name'] = (string) $product->name;
            $item['options']['size'] = $check['size'];
            $item['options']['color'] = $check['color'];
            if (empty($item['options']['image_link']) && ! empty($product->image_link)) {
                $item['options']['image_link'] = (string) $product->image_link;
            }

            if ((float) ($cart[$rowid]['price'] ?? 0) !== $price
                || (float) ($cart[$rowid]['subtotal'] ?? 0) !== $subtotal
                || (int) ($cart[$rowid]['variant_id'] ?? 0) !== (int) $check['variant_id']) {
                $changed = true;
            }

            $clean[$rowid] = $item;
        }

        if (count($clean) !== count($cart)) {
            $changed = true;
        }

        return ['cart' => $clean, 'removed' => $removed, 'changed' => $changed];
    }

    /**
     * @return list<string>
     */
    private function splitOptions(?string $csv): array
    {
        $out = [];

        foreach (explode(',', (string) $csv) as $value) {
            $value = trim($value);
            if ($value !== '') {
                $out[] = $value;
            }
        }

        return $out;
    }
}
