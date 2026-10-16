<?php

namespace App\Models;

use App\Models\Concerns\LegacyTimestamps;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model cho bảng `stock_movements` (lịch sử biến động tồn kho).
 *
 * `movement_type`: in (nhập) | out (xuất) | adjust (điều chỉnh kiểm kê).
 * `reference_type`: receipt (phiếu nhập) | order (đơn hàng) | manual (kiểm kê).
 * `variant_id` có FK ON DELETE SET NULL (nullable) — lịch sử không mất khi xóa variant.
 *
 * @property int $id
 * @property int $product_id
 * @property string $size
 * @property string $color
 * @property string $movement_type
 * @property int $qty_change
 * @property int $before_qty
 * @property int $after_qty
 * @property string $reference_type
 * @property int $reference_id
 * @property string $note
 * @property int $created_by
 * @property int $created
 * @property int|null $variant_id
 */
class StockMovement extends BaseModel
{
    use LegacyTimestamps;

    protected $table = 'stock_movements';

    public const TYPE_IN = 'in';
    public const TYPE_OUT = 'out';
    public const TYPE_ADJUST = 'adjust';

    public const REFERENCE_RECEIPT = 'receipt';
    public const REFERENCE_ORDER = 'order';
    public const REFERENCE_ADJUSTMENT = 'adjustment';
    public const REFERENCE_MANUAL = 'manual';

    protected function casts(): array
    {
        return [
            'id' => 'int',
            'product_id' => 'int',
            'qty_change' => 'int',
            'before_qty' => 'int',
            'after_qty' => 'int',
            'reference_id' => 'int',
            'created_by' => 'int',
            'created' => 'int',
            'variant_id' => 'int',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    public function scopeInbound($query): Builder
    {
        return $query->where('movement_type', self::TYPE_IN);
    }

    public function scopeOutbound($query): Builder
    {
        return $query->where('movement_type', self::TYPE_OUT);
    }

    public function scopeForVariant($query, int $variantId): Builder
    {
        return $query->where('variant_id', $variantId);
    }

    public function createdAt(): ?\Illuminate\Support\Carbon
    {
        return $this->legacyTimestamp('created');
    }
}
