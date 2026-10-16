<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockReceipt;
use App\Models\StockReceiptItem;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Phiếu nhập. Port Stock_service.
 *
 * Tạo phiếu từ form admin là xác nhận ngay và cộng tồn trong cùng transaction.
 * Sửa / hủy chỉ phiếu nháp. Hủy nháp không đổi tồn. Phiếu đã nhập không hoàn kho.
 */
class StockReceiptService
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly VariantStockService $variants,
    ) {
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return array{ok: bool, message: string, receipt_id?: int, receipt_code?: string}
     */
    public function createConfirmed(int $supplierId, string $note, array $lines, int $adminId): array
    {
        return $this->store($supplierId, $note, $lines, $adminId, true);
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return array{ok: bool, message: string, receipt_id?: int}
     */
    public function updateDraft(int $receiptId, int $supplierId, string $note, array $lines): array
    {
        $supplier = $this->activeSupplier($supplierId);
        if (! $supplier['ok']) {
            return $supplier;
        }

        $receipt = StockReceipt::query()->find($receiptId);
        if (! $receipt) {
            return ['ok' => false, 'message' => 'Phiếu nhập không tồn tại.'];
        }
        if ((string) $receipt->status !== StockReceipt::STATUS_DRAFT) {
            return ['ok' => false, 'message' => 'Chỉ được sửa phiếu ở trạng thái nháp.'];
        }

        $validation = $this->validateLines($lines);
        if (! $validation['ok']) {
            return ['ok' => false, 'message' => implode(' ', $validation['errors'])];
        }

        $totals = $this->totals($validation['lines']);
        $now = time();

        DB::transaction(function () use ($receipt, $supplierId, $note, $validation, $totals, $now) {
            $receipt->supplier_id = $supplierId;
            $receipt->note = trim($note);
            $receipt->total_qty = $totals['qty'];
            $receipt->total_amount = $totals['amount'];
            $receipt->updated = $now;
            $receipt->save();

            StockReceiptItem::query()->where('receipt_id', $receipt->id)->delete();
            $this->insertLines((int) $receipt->id, $validation['lines']);
        });

        return ['ok' => true, 'message' => 'Đã cập nhật phiếu nháp.', 'receipt_id' => $receiptId];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function confirm(int $receiptId, int $adminId): array
    {
        try {
            return DB::transaction(function () use ($receiptId, $adminId) {
                $receipt = StockReceipt::query()->where('id', $receiptId)->lockForUpdate()->first();
                if (! $receipt) {
                    return ['ok' => false, 'message' => 'Phiếu nhập không tồn tại.'];
                }
                if ((string) $receipt->status !== StockReceipt::STATUS_DRAFT) {
                    return ['ok' => false, 'message' => 'Phiếu đã xác nhận hoặc đã hủy, không thể xác nhận lại.'];
                }

                $items = StockReceiptItem::query()->where('receipt_id', $receiptId)->orderBy('id')->get();
                if ($items->isEmpty()) {
                    return ['ok' => false, 'message' => 'Phiếu không có dòng sản phẩm.'];
                }

                $now = time();
                $lines = [];
                foreach ($items as $item) {
                    $lines[] = [
                        'variant_id' => (int) $item->variant_id,
                        'product_id' => (int) $item->product_id,
                        'size' => (string) $item->size,
                        'color' => (string) $item->color,
                        'qty' => (int) $item->qty,
                        'unit_cost' => (float) $item->unit_cost,
                        'subtotal' => (float) $item->subtotal,
                    ];
                }

                $applied = $this->applyLines((int) $receipt->id, (string) $receipt->receipt_code, $lines, $adminId, $now);
                $receipt->status = StockReceipt::STATUS_CONFIRMED;
                $receipt->confirmed_by = $adminId;
                $receipt->confirmed_at = $now;
                $receipt->total_amount = $applied;
                $receipt->updated = $now;
                $receipt->save();

                return ['ok' => true, 'message' => 'Đã xác nhận phiếu ' . $receipt->receipt_code . ' và cộng tồn kho.'];
            });
        } catch (RuntimeException $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function cancel(int $receiptId): array
    {
        $receipt = StockReceipt::query()->find($receiptId);
        if (! $receipt) {
            return ['ok' => false, 'message' => 'Phiếu nhập không tồn tại.'];
        }
        if ((string) $receipt->status !== StockReceipt::STATUS_DRAFT) {
            return ['ok' => false, 'message' => 'Chỉ hủy được phiếu nháp.'];
        }

        $now = time();
        $receipt->status = StockReceipt::STATUS_CANCELLED;
        $receipt->cancelled_at = $now;
        $receipt->updated = $now;
        $receipt->save();

        return ['ok' => true, 'message' => 'Đã hủy phiếu ' . $receipt->receipt_code . '.'];
    }

    public function statusLabel(string $status): string
    {
        return match ($status) {
            StockReceipt::STATUS_DRAFT => 'Nháp',
            StockReceipt::STATUS_CONFIRMED => 'Đã nhập',
            StockReceipt::STATUS_CANCELLED => 'Đã hủy',
            default => $status,
        };
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return array{ok: bool, message: string, receipt_id?: int, receipt_code?: string}
     */
    private function store(int $supplierId, string $note, array $lines, int $adminId, bool $confirm): array
    {
        $supplier = $this->activeSupplier($supplierId);
        if (! $supplier['ok']) {
            return $supplier;
        }

        $validation = $this->validateLines($lines);
        if (! $validation['ok']) {
            return ['ok' => false, 'message' => implode(' ', $validation['errors'])];
        }

        $totals = $this->totals($validation['lines']);

        try {
            return DB::transaction(function () use ($supplierId, $note, $adminId, $confirm, $validation, $totals) {
                $now = time();
                $code = $this->nextCode();
                $receipt = StockReceipt::query()->create([
                    'receipt_code' => $code,
                    'supplier_id' => $supplierId,
                    'status' => $confirm ? StockReceipt::STATUS_CONFIRMED : StockReceipt::STATUS_DRAFT,
                    'note' => trim($note),
                    'total_qty' => $totals['qty'],
                    'total_amount' => $totals['amount'],
                    'receipt_date' => date('Y-m-d'),
                    'created_by' => $adminId,
                    'confirmed_by' => $confirm ? $adminId : 0,
                    'confirmed_at' => $confirm ? $now : 0,
                    'cancelled_at' => 0,
                    'created' => $now,
                    'updated' => $now,
                ]);

                $this->insertLines((int) $receipt->id, $validation['lines']);

                if ($confirm) {
                    $amount = $this->applyLines((int) $receipt->id, $code, $validation['lines'], $adminId, $now);
                    if ($amount > 0) {
                        $receipt->total_amount = $amount;
                        $receipt->updated = $now;
                        $receipt->save();
                    }
                }

                return [
                    'ok' => true,
                    'message' => 'Đã nhập kho thành công. Mã phiếu ' . $code . '.',
                    'receipt_id' => (int) $receipt->id,
                    'receipt_code' => $code,
                ];
            });
        } catch (RuntimeException $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return array{ok: bool, errors: list<string>, lines: list<array<string, mixed>>}
     */
    private function validateLines(array $lines): array
    {
        $errors = [];
        $normalized = [];

        if ($lines === []) {
            return ['ok' => false, 'errors' => ['Phiếu nhập cần ít nhất một dòng sản phẩm.'], 'lines' => []];
        }

        foreach ($lines as $idx => $line) {
            $variantId = (int) ($line['variant_id'] ?? 0);
            $qty = (int) ($line['qty'] ?? 0);
            $unitCost = (float) ($line['unit_cost'] ?? 0);

            if ($variantId <= 0) {
                $productId = (int) ($line['product_id'] ?? 0);
                if ($productId > 0) {
                    $product = Product::query()->find($productId);
                    if ($product) {
                        $resolved = $this->variants->resolveVariant(
                            $product,
                            (string) ($line['color'] ?? ''),
                            (string) ($line['size'] ?? '')
                        );
                        $variantId = (int) ($resolved['variant_id'] ?? 0);
                    }
                }
            }

            if ($variantId <= 0) {
                $errors[] = 'Dòng ' . ($idx + 1) . ': chọn biến thể (SKU).';

                continue;
            }

            $variant = ProductVariant::query()->find($variantId);
            if (! $variant || (int) $variant->status !== 1) {
                $errors[] = 'Dòng ' . ($idx + 1) . ': biến thể không tồn tại hoặc đã ngưng.';

                continue;
            }

            if ($qty < 0) {
                $errors[] = 'Dòng ' . ($idx + 1) . ': số lượng không được âm.';

                continue;
            }
            if ($qty <= 0 || $qty > 999999) {
                $errors[] = 'Dòng ' . ($idx + 1) . ': số lượng phải từ 1 đến 999.999.';

                continue;
            }
            if ($unitCost < 0) {
                $errors[] = 'Dòng ' . ($idx + 1) . ': giá nhập không được âm.';

                continue;
            }
            if ($unitCost <= 0) {
                $errors[] = 'Dòng ' . ($idx + 1) . ': giá nhập phải lớn hơn 0.';

                continue;
            }

            $key = (string) $variantId;
            if (isset($normalized[$key])) {
                $normalized[$key]['qty'] += $qty;
            } else {
                $normalized[$key] = [
                    'variant_id' => $variantId,
                    'product_id' => (int) $variant->product_id,
                    'size' => (string) $variant->size,
                    'color' => (string) $variant->color,
                    'qty' => $qty,
                    'unit_cost' => $unitCost,
                    'subtotal' => $unitCost * $qty,
                ];
            }
        }

        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors, 'lines' => []];
        }

        foreach ($normalized as &$row) {
            $row['subtotal'] = $row['unit_cost'] * $row['qty'];
        }
        unset($row);

        return ['ok' => true, 'errors' => [], 'lines' => array_values($normalized)];
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    private function applyLines(int $receiptId, string $code, array $lines, int $adminId, int $now): float
    {
        $total = 0.0;
        $products = [];
        foreach ($lines as $line) {
            $applied = $this->inventory->receiveStock(
                (int) $line['variant_id'],
                (int) $line['qty'],
                (float) $line['unit_cost'],
                (float) $line['subtotal'],
                $receiptId,
                $code,
                $adminId,
                $now
            );
            if (empty($applied['ok'])) {
                throw new RuntimeException((string) ($applied['message'] ?? 'Nhập kho thất bại.'));
            }
            $total += (float) $applied['line_amount'];
            $products[(int) $applied['product_id']] = true;
        }

        foreach (array_keys($products) as $productId) {
            $this->inventory->syncProductQuantityCache($productId);
        }

        return $total;
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    private function insertLines(int $receiptId, array $lines): void
    {
        foreach ($lines as $line) {
            StockReceiptItem::query()->create([
                'receipt_id' => $receiptId,
                'variant_id' => $line['variant_id'],
                'product_id' => $line['product_id'],
                'size' => $line['size'],
                'color' => $line['color'],
                'qty' => $line['qty'],
                'unit_cost' => $line['unit_cost'],
                'subtotal' => $line['subtotal'],
                'note' => '',
            ]);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return array{qty: int, amount: float}
     */
    private function totals(array $lines): array
    {
        $qty = 0;
        $amount = 0.0;
        foreach ($lines as $line) {
            $qty += (int) $line['qty'];
            $amount += (float) $line['subtotal'];
        }

        return ['qty' => $qty, 'amount' => $amount];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    private function activeSupplier(int $supplierId): array
    {
        if ($supplierId <= 0) {
            return ['ok' => false, 'message' => 'Vui lòng chọn nhà cung cấp trước khi nhập kho.'];
        }
        $supplier = Supplier::query()->find($supplierId);
        if (! $supplier || (int) $supplier->status !== 1) {
            return ['ok' => false, 'message' => 'Nhà cung cấp không hợp lệ hoặc đã ngưng hoạt động.'];
        }

        return ['ok' => true, 'message' => ''];
    }

    private function nextCode(): string
    {
        $date = date('Ymd');
        $prefix = 'PN' . $date;
        $seq = 1;
        $row = DB::table('stock_receipts')
            ->where('receipt_code', 'like', $prefix . '%')
            ->orderByDesc('receipt_code')
            ->lockForUpdate()
            ->first();
        if ($row && preg_match('/^PN' . $date . '(\d{3})$/', (string) $row->receipt_code, $match)) {
            $seq = (int) $match[1] + 1;
        }

        $code = $prefix . str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
        $attempts = 0;
        while (StockReceipt::query()->where('receipt_code', $code)->exists() && $attempts < 5) {
            $attempts++;
            $seq++;
            $code = $prefix . str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
        }

        return $code;
    }
}
