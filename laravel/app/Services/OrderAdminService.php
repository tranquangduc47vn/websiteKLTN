<?php

namespace App\Services;

use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

/**
 * Admin đơn hàng. Port admin/Transaction + Transaction_model (trạng thái, hoàn kho, buyed).
 * Không xóa đơn. Giá dòng lấy từ `order.amount` đã lưu.
 */
class OrderAdminService
{
    public const PER_PAGE = 10;

    public function __construct(
        private readonly InventoryService $inventory,
        private readonly VariantStockService $stock,
        private readonly LoyaltyService $loyalty,
    ) {
    }

    /**
     * @param  array{q?: string, status?: string, payment?: string, date_from?: string, date_to?: string}  $filters
     * @return array{total: int, rows: list<object>, offset: int}
     */
    public function list(array $filters, int $offset): array
    {
        $offset = max(0, $offset);
        $query = $this->filtered(DB::table('transaction'), $filters);
        $total = (int) (clone $query)->count();
        $rows = $query
            ->orderByDesc('id')
            ->offset($offset)
            ->limit(self::PER_PAGE)
            ->get([
                'id', 'user_name', 'user_phone', 'user_email', 'amount', 'payment', 'status', 'created',
            ])
            ->all();

        return ['total' => $total, 'rows' => $rows, 'offset' => $offset];
    }

    /** @return list<object> */
    public function lines(int $transactionId): array
    {
        return DB::table('order')
            ->leftJoin('product', 'product.id', '=', 'order.product_id')
            ->where('order.transaction_id', $transactionId)
            ->orderBy('order.id')
            ->get([
                'order.id as order_id',
                'order.product_id',
                'order.variant_id',
                'order.qty',
                'order.amount',
                'order.size',
                'order.color',
                'product.name',
                'product.image_link',
            ])
            ->all();
    }

    /** @return array{ok: bool, message: string, to: string} */
    public function accept(int $id): array
    {
        return $this->transition($id, function (Transaction $order) {
            if ((string) $order->status !== '0') {
                return 'Chỉ có thể xác nhận đơn đang ở trạng thái chờ xử lý.';
            }
            $order->status = Transaction::STATUS_CONFIRMED;
            $order->save();

            return null;
        }, 'Xác nhận đơn đặt hàng thành công');
    }

    /** @return array{ok: bool, message: string, to: string} */
    public function ship(int $id): array
    {
        return $this->transition($id, function (Transaction $order) {
            if ((string) $order->status !== '1') {
                return 'Chỉ có thể giao hàng khi đơn đã được xác nhận.';
            }
            $order->status = Transaction::STATUS_PACKED;
            $order->save();

            return null;
        }, 'Cập nhật trạng thái đơn hàng: Đang giao hàng');
    }

    /** @return array{ok: bool, message: string, to: string} */
    public function complete(int $id): array
    {
        return $this->transition($id, function (Transaction $order) {
            $status = (string) $order->status;
            if ($status === '3') {
                return 'Đơn hàng đã hoàn thành trước đó.';
            }
            if ($status === '4') {
                return 'Không thể hoàn thành đơn đã hủy.';
            }
            if (! in_array($status, ['1', '2'], true)) {
                return 'Đơn cần được xác nhận và chuyển giao trước khi hoàn thành.';
            }
            $this->applyBuyed((int) $order->id);
            $order->status = Transaction::STATUS_COMPLETED;
            $order->save();
            $this->loyalty->creditOrderCompleted($order);

            return null;
        }, 'Đơn đặt hàng đã hoàn thành xuất sắc!');
    }

    /** @return array{ok: bool, message: string, to: string} */
    public function cancel(int $id, string $reason): array
    {
        $reason = trim($reason);
        if ($reason === '') {
            $reason = 'Hủy do Admin hoặc lý do khách quan khác.';
        }

        return $this->transition($id, function (Transaction $order) use ($reason) {
            $status = (string) $order->status;
            if ($status === '4') {
                return 'Đơn hàng đã được hủy trước đó.';
            }
            if (in_array($status, ['2', '3'], true)) {
                return 'Không thể hủy đơn hàng đã bàn giao cho shipper hoặc đã hoàn thành.';
            }
            $this->releaseStock($order, false);
            $this->loyalty->onOrderCancelled($order, false);
            $order->status = Transaction::STATUS_CANCELLED;
            $order->reason = $reason;
            $order->save();

            return null;
        }, 'Đã cập nhật trạng thái đơn hàng: Hủy/Hoàn trả.');
    }

    /** @return array{ok: bool, message: string, to: string} */
    public function removeLine(int $lineId): array
    {
        $line = OrderItem::query()->find($lineId);
        if (! $line) {
            return $this->fail('Dòng sản phẩm trong đơn không tồn tại', url('admin/orders'));
        }
        $transactionId = (int) $line->transaction_id;

        DB::transaction(function () use ($line, $transactionId) {
            $order = Transaction::query()->where('id', $transactionId)->lockForUpdate()->first();
            $line->delete();
            if ($order && (string) $order->status === '0') {
                $this->releaseLine($line, false);
            }
            if ($order) {
                $order->amount = (float) $order->amount - (float) $line->amount;
                $order->save();
            }
        });

        return [
            'ok' => true,
            'message' => 'Xóa thành công',
            'to' => url('admin/orders/detail/' . $transactionId),
        ];
    }

    public function pendingCount(): int
    {
        return (int) Transaction::query()->whereIn('status', [0, 1, 2])->count();
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  array{q?: string, status?: string, payment?: string, date_from?: string, date_to?: string}  $filters
     */
    private function filtered($query, array $filters)
    {
        $keyword = trim((string) ($filters['q'] ?? ''));
        if ($keyword !== '') {
            $like = '%' . addcslashes($keyword, '%_\\') . '%';
            $query->where(function ($inner) use ($like) {
                $inner->where('user_name', 'like', $like)->orWhere('user_phone', 'like', $like);
            });
        }
        $status = (string) ($filters['status'] ?? '');
        if (in_array($status, ['0', '1', '2', '3', '4'], true)) {
            $query->where('status', $status);
        }
        $payment = (string) ($filters['payment'] ?? '');
        if ($payment === 'transfer') {
            $query->where('payment', 'Chuyển khoản');
        } elseif ($payment === 'cod') {
            $query->where(function ($inner) {
                $inner->whereNull('payment')->orWhere('payment', '!=', 'Chuyển khoản');
            });
        }
        $from = trim((string) ($filters['date_from'] ?? ''));
        if ($from !== '' && ($ts = strtotime($from . ' 00:00:00'))) {
            $query->where('created', '>=', $ts);
        }
        $to = trim((string) ($filters['date_to'] ?? ''));
        if ($to !== '' && ($ts = strtotime($to . ' 23:59:59'))) {
            $query->where('created', '<=', $ts);
        }

        return $query;
    }

    /**
     * Cùng bộ lọc danh sách đơn, dùng cho export. Không ghi dữ liệu.
     *
     * @param  array{q?: string, status?: string, payment?: string, date_from?: string, date_to?: string}  $filters
     */
    public function rowsForExport(array $filters, ?int $limit, ?int $offset)
    {
        $query = $this->filtered(DB::table('transaction'), $filters)->orderByDesc('id');
        if ($limit !== null) {
            $query->offset(max(0, (int) $offset))->limit($limit);
        }

        return $query->get();
    }

    /** @param  callable(Transaction): ?string  $apply  null = success */
    private function transition(int $id, callable $apply, string $success): array
    {
        $detail = url('admin/orders/detail/' . $id);
        if ($id <= 0 || ! Transaction::query()->where('id', $id)->exists()) {
            return $this->fail('Đơn đặt hàng không tồn tại!', url('admin/orders'));
        }

        $error = null;
        DB::transaction(function () use ($id, $apply, &$error) {
            $order = Transaction::query()->where('id', $id)->lockForUpdate()->first();
            if (! $order) {
                $error = 'Đơn đặt hàng không tồn tại!';

                return;
            }
            $error = $apply($order);
        });

        if ($error !== null) {
            return $this->fail($error, Transaction::query()->where('id', $id)->exists() ? $detail : url('admin/orders'));
        }

        return ['ok' => true, 'message' => $success, 'to' => $detail];
    }

    private function applyBuyed(int $transactionId): void
    {
        $lines = OrderItem::query()->where('transaction_id', $transactionId)->get();
        foreach ($lines as $line) {
            $product = Product::query()->find((int) $line->product_id);
            if (! $product) {
                continue;
            }
            Product::query()->where('id', $product->id)->update([
                'buyed' => (int) $product->buyed + (int) $line->qty,
            ]);
        }
    }

    private function releaseStock(Transaction $order, bool $reverseBuyed): void
    {
        $lines = OrderItem::query()->where('transaction_id', (int) $order->id)->get();
        foreach ($lines as $line) {
            $this->releaseLine($line, $reverseBuyed);
        }
    }

    private function releaseLine(OrderItem $item, bool $reverseBuyed): void
    {
        $product = Product::query()->find((int) $item->product_id);
        if (! $product) {
            return;
        }
        $qty = (int) $item->qty;
        $variantId = (int) $item->variant_id;
        if ($variantId <= 0) {
            $resolved = $this->stock->resolveVariant($product, (string) $item->color, (string) $item->size);
            $variantId = (int) ($resolved['variant_id'] ?? 0);
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
        if (! $restored) {
            $update = ['quantity' => (int) $product->quantity + $qty];
            if ($reverseBuyed) {
                $update['buyed'] = max(0, (int) $product->buyed - $qty);
            }
            Product::query()->where('id', $product->id)->update($update);

            return;
        }
        if ($reverseBuyed) {
            Product::query()->where('id', $product->id)->update([
                'buyed' => max(0, (int) Product::query()->where('id', $product->id)->value('buyed') - $qty),
            ]);
        }
    }

    /** @return array{ok: bool, message: string, to: string} */
    private function fail(string $message, string $to): array
    {
        return ['ok' => false, 'message' => $message, 'to' => $to];
    }
}
