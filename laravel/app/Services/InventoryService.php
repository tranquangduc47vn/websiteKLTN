<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Services\VariantStockService;
use Illuminate\Support\Facades\DB;

/**
 * Port application/libraries/Inventory_service.php (deduct_for_order / restore_for_order)
 * + Product_service::sync_product_quantity_cache().
 *
 * Luôn ghi stock_movements. Trừ kho dùng UPDATE ... WHERE stock >= qty (không tin stock client).
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
        $now = time();

        $affected = ProductVariant::query()
            ->where('id', $variantId)
            ->where('stock', '>=', $qty)
            ->update([
                'stock' => DB::raw('stock - ' . $qty),
                'updated' => $now,
            ]);

        if ($affected <= 0) {
            if (config('stock.allow_negative_stock')) {
                $after = $before - $qty;
                $variant->stock = $after;
                $variant->updated = $now;
                $variant->save();
            } else {
                $product = Product::query()->find((int) $variant->product_id);

                return [
                    'ok' => false,
                    'message' => $this->stock->stockMessage(
                        $product ? (string) $product->name : 'Sản phẩm',
                        (string) $variant->size,
                        (string) $variant->color,
                        $before
                    ),
                    'stock' => $before,
                    'variant_id' => $variantId,
                ];
            }
        } else {
            $after = $before - $qty;
        }

        StockMovement::query()->create([
            'variant_id' => $variantId,
            'product_id' => (int) $variant->product_id,
            'size' => (string) $variant->size,
            'color' => (string) $variant->color,
            'movement_type' => StockMovement::TYPE_OUT,
            'qty_change' => -$qty,
            'before_qty' => $before,
            'after_qty' => $after,
            'reference_type' => StockMovement::REFERENCE_ORDER,
            'reference_id' => $referenceId,
            'note' => $note !== '' ? $note : 'Xuất kho đơn hàng #' . $referenceId,
            'created_by' => $actorId,
            'created' => $now,
        ]);

        return [
            'ok' => true,
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
        $now = time();
        $variant->stock = $after;
        $variant->updated = $now;
        $variant->save();

        StockMovement::query()->create([
            'variant_id' => $variantId,
            'product_id' => (int) $variant->product_id,
            'size' => (string) $variant->size,
            'color' => (string) $variant->color,
            'movement_type' => StockMovement::TYPE_IN,
            'qty_change' => $qty,
            'before_qty' => $before,
            'after_qty' => $after,
            'reference_type' => StockMovement::REFERENCE_ORDER,
            'reference_id' => $referenceId,
            'note' => $note !== '' ? $note : 'Hoàn kho đơn hàng #' . $referenceId,
            'created_by' => $actorId,
            'created' => $now,
        ]);

        $this->syncProductQuantityCache((int) $variant->product_id);

        return ['ok' => true];
    }

    /**
     * Kiểm kê: đặt tồn biến thể về một số mới và ghi movement adjust.
     * CI3 Inventory_service::adjust_stock(). reference_type = adjustment.
     *
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
        if ($newQty < 0 && ! config('stock.allow_negative_stock')) {
            return ['ok' => false, 'message' => 'Không cho phép tồn kho âm.'];
        }

        $result = ['ok' => false, 'message' => 'Điều chỉnh thất bại.'];
        $productId = 0;

        DB::transaction(function () use ($variantId, $newQty, $note, $adminId, &$result, &$productId) {
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

            $now = time();
            $variant->stock = $newQty;
            $variant->updated = $now;
            $variant->save();

            StockMovement::query()->create([
                'variant_id' => $variantId,
                'product_id' => (int) $variant->product_id,
                'size' => (string) $variant->size,
                'color' => (string) $variant->color,
                'movement_type' => StockMovement::TYPE_ADJUST,
                'qty_change' => $delta,
                'before_qty' => $before,
                'after_qty' => $newQty,
                'reference_type' => StockMovement::REFERENCE_ADJUSTMENT,
                'reference_id' => 0,
                'note' => $note,
                'created_by' => $adminId,
                'created' => $now,
            ]);

            $productId = (int) $variant->product_id;
            $result = ['ok' => true, 'message' => 'Đã điều chỉnh tồn kho biến thể ' . $variant->sku . '.'];
        });

        if ($result['ok'] && $productId > 0) {
            $this->syncProductQuantityCache($productId);
        }

        return $result;
    }

    /**
     * Cộng tồn theo một dòng phiếu nhập. Gọi trong transaction của phiếu.
     * Ghi movement in / reference receipt. Cập nhật cost_price khi giá nhập > 0.
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
        $variant = ProductVariant::query()->where('id', $variantId)->lockForUpdate()->first();
        if (! $variant) {
            return ['ok' => false, 'message' => 'Biến thể không tồn tại trên phiếu.'];
        }

        $before = (int) $variant->stock;
        $after = $before + $qty;
        $variant->stock = $after;
        $variant->updated = $now;
        if ($unitCost > 0) {
            $variant->cost_price = $unitCost;
        }
        $variant->save();

        StockMovement::query()->create([
            'variant_id' => $variantId,
            'product_id' => (int) $variant->product_id,
            'size' => (string) $variant->size,
            'color' => (string) $variant->color,
            'movement_type' => StockMovement::TYPE_IN,
            'qty_change' => $qty,
            'before_qty' => $before,
            'after_qty' => $after,
            'reference_type' => StockMovement::REFERENCE_RECEIPT,
            'reference_id' => $receiptId,
            'note' => 'Nhập kho ' . $receiptCode,
            'created_by' => $adminId,
            'created' => $now,
        ]);

        $lineAmount = $subtotal > 0 ? $subtotal : ($unitCost * $qty);

        return [
            'ok' => true,
            'product_id' => (int) $variant->product_id,
            'line_amount' => $lineAmount,
        ];
    }

    /** CI3 Product_service::sync_product_quantity_cache(): tổng stock biến thể status=1. */
    public function syncProductQuantityCache(int $productId): int
    {
        $total = (int) ProductVariant::query()
            ->where('product_id', $productId)
            ->where('status', 1)
            ->sum('stock');

        Product::query()->where('id', $productId)->update(['quantity' => $total]);

        return $total;
    }
}
