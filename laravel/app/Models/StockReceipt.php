<?php

namespace App\Models;

use App\Models\Concerns\LegacyTimestamps;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Model cho bảng `stock_receipts` (phiếu nhập kho).
 *
 * Trạng thái: draft → confirmed (ghi tăng tồn) hoặc cancelled.
 * `total_qty` / `total_amount` được tính lại khi confirm.
 *
 * @property int $id
 * @property string $receipt_code   // PN20260723007
 * @property int|null $supplier_id
 * @property string $status          // draft|confirmed|cancelled
 * @property string|null $note
 * @property int $total_qty
 * @property int $created_by
 * @property int $confirmed_by
 * @property int $confirmed_at
 * @property int $cancelled_at
 * @property int $created
 * @property int $updated
 * @property string|null $receipt_date  // DATE
 * @property string $total_amount
 */
class StockReceipt extends BaseModel
{
    use LegacyTimestamps;

    protected $table = 'stock_receipts';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_CANCELLED = 'cancelled';

    protected function casts(): array
    {
        return [
            'id' => 'int',
            'supplier_id' => 'int',
            'total_qty' => 'int',
            'created_by' => 'int',
            'confirmed_by' => 'int',
            'confirmed_at' => 'int',
            'cancelled_at' => 'int',
            'created' => 'int',
            'updated' => 'int',
            'receipt_date' => 'date',
            'total_amount' => 'decimal:2',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'confirmed_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockReceiptItem::class, 'receipt_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'reference_id')
            ->where('reference_type', 'receipt');
    }

    public function scopeDraft($query): Builder
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    public function scopeConfirmed($query): Builder
    {
        return $query->where('status', self::STATUS_CONFIRMED);
    }

    public function scopeCancelled($query): Builder
    {
        return $query->where('status', self::STATUS_CANCELLED);
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isConfirmed(): bool
    {
        return $this->status === self::STATUS_CONFIRMED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function createdAt(): ?\Illuminate\Support\Carbon
    {
        return $this->legacyTimestamp('created');
    }

    public function updatedAt(): ?\Illuminate\Support\Carbon
    {
        return $this->legacyTimestamp('updated');
    }
}
