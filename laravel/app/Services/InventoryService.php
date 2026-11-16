<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

/**
 * Tồn kho chỉ nằm ở product_variants.stock.
 * Checkout trừ stock và ghi stock_movements type out. Hủy đơn hoàn stock và ghi type in.
 * Phiếu nhập ghi type in. Kiểm kê ghi type adjust. Không ghi products.quantity.
 */
class InventoryService
{
    public function __construct(private readonly VariantStockService $stock)
    {
    }

    /**
     * @return array{ok: bool, message: string, product_id?: int, variant_id?: int, stock?: int}
     */
    public function deductForOrder(int $variantId, int $qty, int $referenceId, int $actorId = 0, string $note = ''): array
    {
        if ($variantId <= 0 || $qty <= 0) {
            return ['ok' => false, 'message' => 'Dữ liệu xuất kho không hợp lệ.'];
        }

        $variant = ProductVariant::query()->where('id', $variantId)->lockForUpdate()->first();
        if (! $variant) {
            return ['ok' => false, 'message' => 'Biến thể không tồn tại.'];
        }

        $before = (int) $variant->stock;
        if ($before < $qty) {
            $product = Product::query()->find((int) $variant->product_id);

            return [
                'ok' => false,
                'message' => $this->stock->stockMessage(
                    $product ? (string) $product->name : 'Sản phẩm',
                    (string) $variant->size_name,
                    (string) $variant->color_name,
                    $before
                ),
                'stock' => $before,
                'variant_id' => $variantId,
            ];
        }

        $after = $before - $qty;
        ProductVariant::query()->where('id', $variantId)->update([
            'stock' => $after,
        ]);

        $this->writeMovement(
            $variant,
            StockMovement::TYPE_OUT,
            -$qty,
            $before,
            $after,
            StockMovement::REFERENCE_ORDER,
            $referenceId,
            $note !== '' ? $note : 'Xuất kho đơn hàng #' . $referenceId,
            $actorId
        );

        return [
            'ok' => true,
            'message' => '',
            'product_id' => (int) $variant->product_id,
            'variant_id' => $variantId,
        ];
    }

    /**
     * @return array{ok: bool, message?: string}
     */
    public function restoreForOrder(int $variantId, int $qty, int $referenceId, int $actorId = 0, string $note = ''): array
    {
        if ($variantId <= 0 || $qty <= 0) {
            return ['ok' => false, 'message' => 'Dữ liệu hoàn kho không hợp lệ.'];
        }

        $variant = ProductVariant::query()->where('id', $variantId)->lockForUpdate()->first();
        if (! $variant) {
            return ['ok' => false, 'message' => 'Biến thể không tồn tại.'];
        }

        $before = (int) $variant->stock;
        $after = $before + $qty;
        $variant->stock = $after;
        $variant->save();

        $this->writeMovement(
            $variant,
            StockMovement::TYPE_IN,
            $qty,
            $before,
            $after,
            StockMovement::REFERENCE_ORDER,
            $referenceId,
            $note !== '' ? $note : 'Hoàn kho đơn hàng #' . $referenceId,
            $actorId
        );

        return ['ok' => true];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function adjustStock(int $variantId, int $newQty, string $note, int $adminId): array
    {
        $note = trim($note);
        if ($variantId <= 0) {
            return ['ok' => false, 'message' => 'Biến thể không hợp lệ.'];
        }
        if ($note === '') {
            return ['ok' => false, 'message' => 'Vui lòng nhập lý do điều chỉnh.'];
        }
        if ($newQty < 0) {
            return ['ok' => false, 'message' => 'Không cho phép tồn kho âm.'];
        }

        $result = ['ok' => false, 'message' => 'Điều chỉnh thất bại.'];

        DB::transaction(function () use ($variantId, $newQty, $note, $adminId, &$result) {
            $variant = ProductVariant::query()->where('id', $variantId)->lockForUpdate()->first();
            if (! $variant) {
                $result = ['ok' => false, 'message' => 'Biến thể không tồn tại.'];

                return;
            }

            $before = (int) $variant->stock;
            $delta = $newQty - $before;
            if ($delta === 0) {
                $result = ['ok' => false, 'message' => 'Tồn kho không thay đổi.'];

                return;
            }

            $variant->stock = $newQty;
            $variant->save();

            $this->writeMovement(
                $variant,
                StockMovement::TYPE_ADJUST,
                $delta,
                $before,
                $newQty,
                StockMovement::REFERENCE_ADJUSTMENT,
                null,
                $note,
                $adminId
            );

            $result = ['ok' => true, 'message' => 'Đã điều chỉnh tồn kho biến thể ' . $variant->sku . '.'];
        });

        return $result;
    }

    /**
     * Cộng tồn theo một dòng phiếu nhập. Ghi movement in.
     *
     * @return array{ok: bool, message?: string, product_id?: int, line_amount?: float}
     */
    public function receiveStock(
        int $variantId,
        int $qty,
        float $unitCost,
        float $subtotal,
        int $receiptId,
        string $receiptCode,
        int $adminId,
        int $now
    ): array {
        unset($now);

        $variant = ProductVariant::query()->where('id', $variantId)->lockForUpdate()->first();
        if (! $variant) {
            return ['ok' => false, 'message' => 'Biến thể không tồn tại trên phiếu.'];
        }

        $before = (int) $variant->stock;
        $after = $before + $qty;
        $variant->stock = $after;
        if ($unitCost > 0) {
            $variant->cost_price = $unitCost;
        }
        $variant->save();

        $this->writeMovement(
            $variant,
            StockMovement::TYPE_IN,
            $qty,
            $before,
            $after,
            StockMovement::REFERENCE_RECEIPT,
            $receiptId,
            'Nhập kho ' . $receiptCode,
            $adminId
        );

        $lineAmount = $subtotal > 0 ? $subtotal : ($unitCost * $qty);

        return [
            'ok' => true,
            'product_id' => (int) $variant->product_id,
            'line_amount' => $lineAmount,
        ];
    }

    /**
     * Tổng stock biến thể đang bán. Không ghi cột quantity.
     */
    public function syncProductQuantityCache(int $productId): int
    {
        return (int) ProductVariant::query()
            ->where('product_id', $productId)
            ->where('status', ProductVariant::STATUS_ACTIVE)
            ->sum('stock');
    }

    private function writeMovement(
        ProductVariant $variant,
        string $type,
        int $qtyChange,
        int $before,
        int $after,
        ?string $referenceType,
        ?int $referenceId,
        string $note,
        int $actorId
    ): void {
        StockMovement::query()->create([
            'variant_id' => (int) $variant->id,
            'color_name' => (string) $variant->color_name,
            'size_name' => (string) $variant->size_name,
            'movement_type' => $type,
            'qty_change' => $qtyChange,
            'before_qty' => $before,
            'after_qty' => $after,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'note' => $note,
            'created_by' => $actorId > 0 ? $actorId : null,
        ]);
    }
}
