<?php

namespace App\Models;

use App\Models\Concerns\LegacyTimestamps;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Model cho bảng `transaction` (đơn hàng — header của đơn).
 *
 * Trạng thái đơn dùng mã int của CI3 (xem Transaction_model.php):
 *   0 = Mới / chờ xử lý
 *   1 = Đã xác nhận
 *   2 = Đã đóng gói
 *   3 = Đã giao / hoàn thành
 *   4 = Đã hủy (lưu lý do hủy trong `reason`)
 *
 * Cột `voucher_code`, `discount_amount` phục vụ module voucher.
 * Cột `payment` lưu phương thức: COD / QR.
 *
 * @property int $id
 * @property int $status
 * @property string|null $reason
 * @property int $user_id
 * @property string $user_name
 * @property string $user_email
 * @property string $user_phone
 * @property string $user_address
 * @property string $message
 * @property string $amount
 * @property string $voucher_code
 * @property int $discount_amount
 * @property string $payment
 * @property int $created  // INT Unix
 */
class Transaction extends BaseModel
{
    use LegacyTimestamps;

    protected $table = 'transaction';

    public const STATUS_NEW = 0;
    public const STATUS_CONFIRMED = 1;
    public const STATUS_PACKED = 2;
    public const STATUS_COMPLETED = 3;
    public const STATUS_CANCELLED = 4;

    public const PAYMENT_COD = 'COD';
    /** CI3 form value + DB: "Chuyển khoản". Constant PAYMENT_QR giữ tương thích tên cũ, không phải giá trị lưu DB. */
    public const PAYMENT_TRANSFER = 'Chuyển khoản';
    public const PAYMENT_QR = 'QR';

    protected function casts(): array
    {
        return [
            'id' => 'int',
            'status' => 'int',
            'user_id' => 'int',
            'amount' => 'decimal:2',
            'discount_amount' => 'int',
            'created' => 'int',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'transaction_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function voucherUses(): HasMany
    {
        return $this->hasMany(VoucherUse::class, 'transaction_id');
    }

    public function pointLogs(): HasMany
    {
        return $this->hasMany(UserPointLog::class, 'transaction_id');
    }

    public function createdAt(): ?\Illuminate\Support\Carbon
    {
        return $this->legacyTimestamp('created');
    }

    public function setCreatedAtAttribute(mixed $value): void
    {
        $this->setLegacyTimestamp('created', $value);
    }

    public function isCompleted(): bool
    {
        return (int) $this->status === self::STATUS_COMPLETED;
    }

    public function isCancelled(): bool
    {
        return (int) $this->status === self::STATUS_CANCELLED;
    }

    /**
     * Đơn đang hoạt động (dùng cho badge số đơn ở header).
     */
    public function scopeActive($query)
    {
        return $query->where('status', '!=', self::STATUS_CANCELLED);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForEmail($query, string $email)
    {
        return $query->where('user_email', $email);
    }
}
