<?php

namespace App\Services;

use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Checkout\CheckoutFailedException;
use App\Services\Checkout\CheckoutResult;
use App\Support\Payment\BankTransfer;
use App\Support\Storefront\Loyalty;
use Illuminate\Contracts\Session\Session;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Port application/controllers/Order.php (đặt hàng, voucher session, QR access, hủy đơn).
 *
 * Giá / tồn / voucher luôn lấy lại từ DB. Giỏ đi qua CartSessionService (custom_cart).
 */
class CheckoutService
{
    public const SESSION_VOUCHER = 'checkout_voucher';
    public const SESSION_ACCESS = 'order_access_ids';

    public const MSG_EMPTY_CART = 'Giỏ hàng trống, không thể đặt hàng.';
    public const MSG_EMPTY_CART_AJAX = 'Giỏ hàng trống.';
    public const MSG_LOGIN_REQUIRED = 'Bạn cần đăng nhập để đặt hàng.';
    public const MSG_LOGIN_VOUCHER = 'Vui lòng đăng nhập để dùng voucher.';
    public const MSG_VOUCHER_CLEARED = 'Đã bỏ mã voucher.';
    public const MSG_COD_SUCCESS = 'Đặt hàng thành công! Đơn hàng của bạn sẽ được thanh toán bằng hình thức COD (Tiền mặt).';
    public const MSG_ORDER_FAIL = 'Đặt hàng thất bại. Vui lòng thử lại sau.';
    public const MSG_STOCK_FAIL = 'Không đủ tồn kho để đặt hàng. Vui lòng giảm số lượng hoặc thử lại sau.';
    public const MSG_QR_DENIED = 'Không thể xem mã QR cho đơn hàng này.';
    /** Giữ chuỗi cũ. Response từ chối quyền dùng cùng lối với đơn không tồn tại. */
    public const MSG_QR_FORBIDDEN = 'Bạn không có quyền xem thông tin thanh toán đơn hàng này.';
    public const MSG_CANCEL_MISSING_ID = 'Không tìm thấy mã đơn hàng!';
    public const MSG_CANCEL_MISSING = 'Đơn hàng không tồn tại trên hệ thống!';
    /** Giữ chuỗi cũ. Từ chối hủy đơn trả MSG_CANCEL_MISSING để không xác nhận đơn tồn tại. */
    public const MSG_CANCEL_FORBIDDEN = 'Bạn không có quyền xử lý đơn hàng này!';
    public const MSG_CANCEL_STATUS = 'Đơn hàng không thể hủy ở trạng thái hiện tại.';
    public const MSG_CANCEL_OK = 'Hủy đơn hàng thành công! Sản phẩm đã được hoàn lại kho hàng.';
    public const MSG_CANCEL_ALREADY = 'Đơn hàng này đã được hủy từ trước.';
    public const MSG_DEFAULT_REASON = 'Khách hàng tự hủy đơn';

    public function __construct(
        private readonly CartSessionService $cart,
        private readonly VariantStockService $stock,
        private readonly VoucherService $vouchers,
        private readonly InventoryService $inventory,
        private readonly LoyaltyService $loyalty,
    ) {
    }

    /**
     * @return array{cart: array<string, array<string, mixed>>, removed: list<string>, changed: bool, subtotal: int, total: float}
     */
    public function sanitizeCart(Session $session): array
    {
        return $this->cart->sanitizeForCheckout($session);
    }

    /**
     * @param  array{name: string, email: string, phone: string, address: string, message?: string, payment?: string, voucher_code?: string}  $input
     */
    public function place(Session $session, ?User $user, array $input): CheckoutResult
    {
        if (! $user instanceof User || (int) $user->id <= 0) {
            return CheckoutResult::fail(self::MSG_LOGIN_REQUIRED, CheckoutResult::REDIRECT_LOGIN);
        }

        $sanitize = $this->cart->sanitizeForCheckout($session);
        $carts = $sanitize['cart'];

        if ($sanitize['removed'] !== []) {
            return CheckoutResult::fail(
                'Không thể đặt hàng: một số sản phẩm không đủ tồn kho hoặc không còn bán ('
                . implode(', ', $sanitize['removed']) . '). Vui lòng kiểm tra lại giỏ hàng.',
                CheckoutResult::REDIRECT_CART
            );
        }
        if ($carts === []) {
            return CheckoutResult::fail(self::MSG_EMPTY_CART, CheckoutResult::REDIRECT_CART);
        }

        $cartSubtotal = $sanitize['subtotal'];
        $userId = $user ? (int) $user->id : 0;
        $payment = $this->normalizePayment($input['payment'] ?? '');
        $voucherCode = Loyalty::normalizeVoucherCode($input['voucher_code'] ?? '');

        if ($voucherCode !== '') {
            $check = $this->vouchers->validateForCheckout($voucherCode, $userId, $cartSubtotal);
            if (! $check['ok']) {
                $session->forget(self::SESSION_VOUCHER);

                return CheckoutResult::fail($check['message'], CheckoutResult::REDIRECT_CHECKOUT);
            }
        }

        $orderLines = [];
        foreach ($carts as $items) {
            $product = Product::query()->find((int) $items['id']);
            if (! $product) {
                return CheckoutResult::fail(
                    'Không thể đặt hàng vì sản phẩm không còn tồn tại. Vui lòng cập nhật giỏ hàng.',
                    CheckoutResult::REDIRECT_CART
                );
            }

            $size = (string) ($items['options']['size'] ?? '');
            $color = (string) ($items['options']['color'] ?? '');
            $qty = max(1, (int) $items['qty']);
            $check = $this->stock->validateCartQuantity($product, $color, $size, $qty, 0);
            if (! $check['ok']) {
                return CheckoutResult::fail($check['message'], CheckoutResult::REDIRECT_CHECKOUT);
            }
            if ((int) $check['variant_id'] <= 0) {
                return CheckoutResult::fail(
                    'Không thể đặt hàng: biến thể sản phẩm "' . $product->name . '" không còn khả dụng.',
                    CheckoutResult::REDIRECT_CART
                );
            }

            $orderLines[] = [
                'items' => $items,
                'product' => $product,
                'variant_id' => (int) $check['variant_id'],
                'size' => (string) $check['size'],
                'color' => (string) $check['color'],
                'qty' => $qty,
            ];
        }

        $deductByVariant = [];
        foreach ($orderLines as $line) {
            $vid = (int) $line['variant_id'];
            $deductByVariant[$vid] = ($deductByVariant[$vid] ?? 0) + (int) $line['qty'];
        }
        ksort($deductByVariant);

        $syncProductIds = [];
        $transaction = null;

        try {
            $transaction = DB::transaction(function () use (
                $userId,
                $input,
                $cartSubtotal,
                $voucherCode,
                $payment,
                $deductByVariant,
                $orderLines,
                &$syncProductIds
            ) {
                $discountAmount = 0;
                $appliedVoucher = null;
                if ($voucherCode !== '') {
                    $locked = $this->vouchers->validateForCheckout($voucherCode, $userId, $cartSubtotal, true);
                    if (! $locked['ok']) {
                        throw new CheckoutFailedException($locked['message'], CheckoutResult::REDIRECT_CHECKOUT);
                    }
                    $appliedVoucher = $locked['voucher'];
                    $discountAmount = (int) $locked['discount'];
                }
                $finalAmount = max(0, $cartSubtotal - $discountAmount);

                $note = trim((string) ($input['message'] ?? ''));
                $tx = Transaction::query()->create([
                    'user_id' => $userId > 0 ? $userId : null,
                    'customer_name' => (string) $input['name'],
                    'customer_email' => (string) $input['email'],
                    'customer_phone' => (string) $input['phone'],
                    'shipping_address' => (string) $input['address'],
                    'note' => $note !== '' ? $note : null,
                    'subtotal' => $cartSubtotal,
                    'discount_amount' => $discountAmount,
                    'shipping_fee' => 0,
                    'total' => $finalAmount,
                    'voucher_code' => $voucherCode !== '' ? $voucherCode : null,
                    'status' => Transaction::STATUS_NEW,
                ]);

                $isTransfer = $payment === Transaction::PAYMENT_TRANSFER;
                Payment::query()->create([
                    'order_id' => (int) $tx->id,
                    'method' => $isTransfer ? Payment::METHOD_BANK : Payment::METHOD_COD,
                    'status' => $isTransfer ? Payment::STATUS_PENDING : Payment::STATUS_COD,
                    'reference' => $isTransfer ? BankTransfer::referenceFor((int) $tx->id) : null,
                ]);

                if ($appliedVoucher) {
                    $this->vouchers->recordUse(
                        (int) $appliedVoucher->id,
                        $userId,
                        (int) $tx->id,
                        $discountAmount
                    );
                }

                foreach ($deductByVariant as $variantId => $deductQty) {
                    $deduct = $this->inventory->deductForOrder(
                        (int) $variantId,
                        (int) $deductQty,
                        (int) $tx->id,
                        0,
                        'Xuất kho đơn #' . (int) $tx->id
                    );
                    if (empty($deduct['ok'])) {
                        throw new CheckoutFailedException(
                            ! empty($deduct['message']) ? (string) $deduct['message'] : self::MSG_STOCK_FAIL,
                            CheckoutResult::REDIRECT_CHECKOUT
                        );
                    }
                    if (! empty($deduct['product_id'])) {
                        $syncProductIds[(int) $deduct['product_id']] = true;
                    }
                }

                foreach ($orderLines as $line) {
                    $variantId = (int) $line['variant_id'];
                    $size = (string) $line['size'];
                    $color = (string) $line['color'];
                    $cost = 0.0;
                    if ($variantId > 0) {
                        $cost = (float) (ProductVariant::query()->where('id', $variantId)->value('cost_price') ?? 0);
                    }

                    $qty = (int) $line['qty'];
                    $unitPrice = round((float) ($line['items']['price'] ?? 0), 2);
                    $sku = (string) (ProductVariant::query()->where('id', $variantId)->value('sku') ?? '');
                    OrderItem::query()->create([
                        'order_id' => (int) $tx->id,
                        'product_id' => (int) $line['items']['id'],
                        'variant_id' => $variantId,
                        'product_name' => (string) $line['product']->name,
                        'sku' => $sku !== '' ? $sku : ('SP' . (int) $line['items']['id']),
                        'size_name' => $size !== '' ? $size : 'Mặc định',
                        'color_name' => $color !== '' ? $color : 'Mặc định',
                        'qty' => $qty,
                        'unit_price' => $unitPrice,
                        'line_subtotal' => round($qty * $unitPrice, 2),
                        'cost_price' => max(0, $cost),
                    ]);
                }

                return $tx;
            });
        } catch (CheckoutFailedException $e) {
            return CheckoutResult::fail($e->getMessage(), $e->redirect);
        } catch (QueryException $e) {
            Log::error('checkout.place failed', ['exception' => $e->getMessage()]);

            return CheckoutResult::fail(self::MSG_ORDER_FAIL, CheckoutResult::REDIRECT_CHECKOUT);
        }

        foreach (array_keys($syncProductIds) as $productId) {
            $this->inventory->syncProductQuantityCache((int) $productId);
        }

        $this->rememberAccess($session, (int) $transaction->id);
        $session->forget(self::SESSION_VOUCHER);
        // CI3 COD xóa giỏ ngay; chuyển khoản xóa trên trang QR. Xóa ngay cả hai để chống double submit.
        $this->cart->clear($session);

        if ($payment === Transaction::PAYMENT_TRANSFER) {
            return CheckoutResult::success('', CheckoutResult::REDIRECT_QR, $transaction, '');
        }

        return CheckoutResult::success(self::MSG_COD_SUCCESS, CheckoutResult::REDIRECT_CART, $transaction);
    }

    /**
     * @return array<string, mixed>
     */
    public function applyVoucher(Session $session, ?User $user, string $code): array
    {
        $sanitize = $this->cart->sanitizeForCheckout($session);
        $subtotal = $sanitize['subtotal'];
        if ($subtotal <= 0) {
            return ['ok' => false, 'message' => self::MSG_EMPTY_CART_AJAX];
        }

        $userId = $user ? (int) $user->id : 0;
        if ($userId <= 0) {
            return ['ok' => false, 'message' => self::MSG_LOGIN_VOUCHER];
        }

        $code = Loyalty::normalizeVoucherCode($code);
        if ($code === '') {
            $session->forget(self::SESSION_VOUCHER);

            return [
                'ok' => true,
                'cleared' => true,
                'message' => self::MSG_VOUCHER_CLEARED,
                'subtotal' => $subtotal,
                'discount' => 0,
                'final' => $subtotal,
                'subtotal_fmt' => Loyalty::moneyFmt($subtotal),
                'final_fmt' => Loyalty::moneyFmt($subtotal),
            ];
        }

        $check = $this->vouchers->validateForCheckout($code, $userId, $subtotal);
        if (! $check['ok']) {
            $session->forget(self::SESSION_VOUCHER);

            return ['ok' => false, 'message' => $check['message']];
        }

        $discount = (int) $check['discount'];
        $final = max(0, $subtotal - $discount);
        $session->put(self::SESSION_VOUCHER, [
            'code' => $code,
            'discount' => $discount,
            'cart_subtotal' => $subtotal,
            'final' => $final,
        ]);

        $msg = 'Áp dụng mã thành công';
        $name = (string) ($check['voucher']->name ?? '');
        if ($name !== '') {
            $msg .= ': ' . $name;
        }
        $msg .= '.';

        return [
            'ok' => true,
            'message' => $msg,
            'subtotal' => $subtotal,
            'discount' => $discount,
            'final' => $final,
            'subtotal_fmt' => Loyalty::moneyFmt($subtotal),
            'discount_fmt' => Loyalty::moneyFmt($discount),
            'final_fmt' => Loyalty::moneyFmt($final),
        ];
    }

    /**
     * @return array{code: string, discount: int, cart_subtotal: int, final: int}|null
     */
    public function refreshVoucher(Session $session, int $userId, int $cartSubtotal): ?array
    {
        $applied = $session->get(self::SESSION_VOUCHER);
        if (! is_array($applied) || empty($applied['code'])) {
            $session->forget(self::SESSION_VOUCHER);

            return null;
        }
        if ((int) $applied['cart_subtotal'] !== $cartSubtotal || $userId <= 0) {
            $session->forget(self::SESSION_VOUCHER);

            return null;
        }

        $code = Loyalty::normalizeVoucherCode($applied['code']);
        $check = $this->vouchers->validateForCheckout($code, $userId, $cartSubtotal);
        if (! $check['ok']) {
            $session->forget(self::SESSION_VOUCHER);

            return null;
        }

        $discount = (int) $check['discount'];
        $payload = [
            'code' => $code,
            'discount' => $discount,
            'cart_subtotal' => $cartSubtotal,
            'final' => max(0, $cartSubtotal - $discount),
        ];
        $session->put(self::SESSION_VOUCHER, $payload);

        return $payload;
    }

    /** CI3 cart_clear_session() — gọi sau COD / trên trang QR. */
    public function clearCart(Session $session): void
    {
        $this->cart->clear($session);
    }

    public function rememberAccess(Session $session, int $transactionId): void
    {
        $ids = $session->get(self::SESSION_ACCESS);
        if (! is_array($ids)) {
            $ids = [];
        }
        $ids[$transactionId] = time();
        $session->put(self::SESSION_ACCESS, $ids);
    }

    public function canAccess(Session $session, ?Transaction $transaction, ?User $user): bool
    {
        if (! $transaction) {
            return false;
        }

        $orderId = (int) $transaction->id;
        if ($user && (int) $transaction->user_id > 0) {
            return (int) $transaction->user_id === (int) $user->id;
        }

        $ids = $session->get(self::SESSION_ACCESS);

        return is_array($ids) && ! empty($ids[$orderId]);
    }

    public function cancel(Session $session, ?User $user, int $transactionId, string $reason): CheckoutResult
    {
        if ($transactionId <= 0) {
            return CheckoutResult::fail(self::MSG_CANCEL_MISSING_ID, CheckoutResult::REDIRECT_HOME);
        }

        $transaction = Transaction::query()->find($transactionId);
        if (! $transaction || ! $this->canAccess($session, $transaction, $user)) {
            return CheckoutResult::fail(self::MSG_CANCEL_MISSING, CheckoutResult::REDIRECT_HOME);
        }

        $status = (string) $transaction->status;
        if (! in_array($status, [Transaction::STATUS_NEW, Transaction::STATUS_CONFIRMED], true)) {
            return new CheckoutResult(false, self::MSG_CANCEL_STATUS, CheckoutResult::REDIRECT_USER, 'fail');
        }

        $reason = $reason !== '' ? $reason : self::MSG_DEFAULT_REASON;
        if ($status === Transaction::STATUS_CANCELLED) {
            return new CheckoutResult(false, self::MSG_CANCEL_ALREADY, CheckoutResult::REDIRECT_USER, 'fail');
        }

        try {
            DB::transaction(function () use ($transaction, $reason) {
                $fresh = Transaction::query()->where('id', $transaction->id)->lockForUpdate()->first();
                if (! $fresh || (string) $fresh->status === Transaction::STATUS_CANCELLED) {
                    throw new CheckoutFailedException(self::MSG_CANCEL_ALREADY, CheckoutResult::REDIRECT_USER);
                }
                if (! in_array((string) $fresh->status, [Transaction::STATUS_NEW, Transaction::STATUS_CONFIRMED], true)) {
                    throw new CheckoutFailedException(self::MSG_CANCEL_STATUS, CheckoutResult::REDIRECT_USER);
                }

                $reverseBuyed = ((string) $fresh->status === Transaction::STATUS_COMPLETED);
                $this->releaseStock($fresh, $reverseBuyed);
                $this->loyalty->onOrderCancelled($fresh, $reverseBuyed);
                $fresh->status = Transaction::STATUS_CANCELLED;
                $fresh->cancel_reason = $reason;
                $fresh->save();
            });
        } catch (CheckoutFailedException $e) {
            return new CheckoutResult(false, $e->getMessage(), $e->redirect, 'fail');
        } catch (QueryException $e) {
            Log::error('checkout.cancel failed', ['exception' => $e->getMessage()]);

            return CheckoutResult::fail(self::MSG_ORDER_FAIL, CheckoutResult::REDIRECT_HOME);
        }

        return new CheckoutResult(true, self::MSG_CANCEL_OK, CheckoutResult::REDIRECT_USER, 'success');
    }

    /**
     * Số tiền và nội dung QR lấy từ đơn đã lưu, không lấy từ request.
     *
     * @return array{bank: string, account: string, name: string, amount: int, reference: string}
     */
    public function transferInstructions(Transaction $transaction): array
    {
        $sepayAccount = trim((string) config('sepay.account_number'));
        if ($sepayAccount !== '') {
            $bank = trim((string) config('sepay.bank_code'));
            $account = $sepayAccount;
            $name = trim((string) config('sepay.account_name'));
            if ($bank === '') {
                $bank = (string) config('shop.vietqr.bank_id', 'vietcombank');
            }
            if ($name === '') {
                $name = (string) config('shop.vietqr.account_name', 'NGUYEN VAN A');
            }
        } else {
            $bank = (string) config('shop.vietqr.bank_id', 'vietcombank');
            $account = (string) config('shop.vietqr.account_no', '1234567890');
            $name = (string) config('shop.vietqr.account_name', 'NGUYEN VAN A');
        }

        $reference = trim((string) $transaction->payment_ref);
        if ($reference === '') {
            $reference = BankTransfer::referenceFor((int) $transaction->id);
        }

        return [
            'bank' => $bank,
            'account' => $account,
            'name' => $name,
            'amount' => BankTransfer::amountVnd($transaction->amount),
            'reference' => $reference,
        ];
    }

    public function qrImageUrl(Transaction $transaction): string
    {
        $info = $this->transferInstructions($transaction);

        return 'https://img.vietqr.io/image/' . $info['bank'] . '-' . $info['account']
            . '-compact2.png?amount=' . $info['amount']
            . '&addInfo=' . rawurlencode($info['reference'])
            . '&accountName=' . rawurlencode($info['name']);
    }

    public function normalizePayment(mixed $payment): string
    {
        return Transaction::methodFromForm(trim((string) $payment));
    }

    private function releaseStock(Transaction $transaction, bool $reverseBuyed): void
    {
        $lines = OrderItem::query()->where('order_id', (int) $transaction->id)->get();
        foreach ($lines as $item) {
            $product = Product::query()->find((int) $item->product_id);
            if (! $product) {
                continue;
            }

            $qty = (int) $item->qty;
            $variantId = (int) $item->variant_id;
            if ($variantId <= 0) {
                $resolved = $this->stock->resolveVariant($product, (string) $item->color, (string) $item->size);
                $variantId = $resolved['variant_id'] ?? 0;
            }

            $restored = false;
            if ($variantId > 0) {
                $result = $this->inventory->restoreForOrder(
                    $variantId,
                    $qty,
                    (int) $item->transaction_id,
                    0,
                    'Hoàn kho đơn hàng #' . (int) $item->transaction_id
                );
                $restored = ! empty($result['ok']);
            }

            if ($reverseBuyed) {
                Product::query()->where('id', $product->id)->update([
                    'sold_count' => max(0, (int) $product->sold_count - $qty),
                ]);
            }
        }
    }
}
