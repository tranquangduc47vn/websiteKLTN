<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Đơn hàng. Bảng V2: orders.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $customer_name
 * @property string $customer_email
 * @property string $customer_phone
 * @property string $shipping_address
 * @property string|null $note
 * @property string $subtotal
 * @property string $discount_amount
 * @property string $shipping_fee
 * @property string $total
 * @property string $status
 * @property string|null $cancel_reason
 * @property string|null $voucher_code
 */
class Transaction extends BaseModel
{
    protected $table = 'orders';

    public $timestamps = true;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'customer_name',
        'customer_email',
        'customer_phone',
        'shipping_address',
        'note',
        'subtotal',
        'discount_amount',
        'shipping_fee',
        'total',
        'voucher_code',
        'status',
    ];

    /** @var list<string> */
    protected $guarded = ['*'];

    public const STATUS_NEW = 'pending';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_PACKED = 'packed';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const PAYMENT_COD = 'cod';

    public const PAYMENT_TRANSFER = 'bank_transfer';

    public const PAYMENT_QR = 'QR';

    /** Giá trị form/storefront cũ, map sang method lúc ghi payment. */
    public const FORM_COD = 'COD';

    public const FORM_TRANSFER = 'Chuyển khoản';

    protected function casts(): array
    {
        return [
            'id' => 'int',
            'user_id' => 'int',
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'shipping_fee' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'order_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function paymentRow(): HasOne
    {
        return $this->hasOne(Payment::class, 'order_id');
    }

    public function voucherUses(): HasMany
    {
        return $this->hasMany(VoucherUse::class, 'order_id');
    }

    public function pointLogs(): HasMany
    {
        return $this->hasMany(UserPointLog::class, 'order_id');
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isBankTransfer(): bool
    {
        return (string) optional($this->paymentRow)->method === self::PAYMENT_TRANSFER;
    }

    public function scopeActive($query)
    {
        return $query->where('status', '!=', self::STATUS_CANCELLED);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Lọc liên hệ giao hàng. Không dùng để cấp quyền xem hoặc hủy đơn.
     */
    public function scopeForEmail($query, string $email)
    {
        return $query->where('customer_email', $email);
    }

    public static function methodFromForm(string $payment): string
    {
        $payment = trim($payment);
        if (in_array($payment, [self::FORM_TRANSFER, self::PAYMENT_TRANSFER, self::PAYMENT_QR, 'qr'], true)) {
            return self::PAYMENT_TRANSFER;
        }

        return self::PAYMENT_COD;
    }

    public static function formLabel(?string $method): string
    {
        return $method === self::PAYMENT_TRANSFER ? self::FORM_TRANSFER : self::FORM_COD;
    }

    public function getAmountAttribute(): string
    {
        return (string) ($this->attributes['total'] ?? '0');
    }

    public function getUserNameAttribute(): string
    {
        return (string) ($this->attributes['customer_name'] ?? '');
    }

    public function getUserEmailAttribute(): string
    {
        return (string) ($this->attributes['customer_email'] ?? '');
    }

    public function getUserPhoneAttribute(): string
    {
        return (string) ($this->attributes['customer_phone'] ?? '');
    }

    public function getUserAddressAttribute(): string
    {
        return (string) ($this->attributes['shipping_address'] ?? '');
    }

    public function getMessageAttribute(): ?string
    {
        return $this->attributes['note'] ?? null;
    }

    public function getReasonAttribute(): ?string
    {
        return $this->attributes['cancel_reason'] ?? null;
    }

    public function getPaymentAttribute(): string
    {
        return self::formLabel($this->paymentRow?->method);
    }

    public function getPaymentStatusAttribute(): ?string
    {
        $status = $this->paymentRow?->status;

        return $status === null || $status === '' ? null : (string) $status;
    }

    public function getPaymentRefAttribute(): ?string
    {
        return $this->paymentRow?->reference;
    }

    public function getPaymentGatewayIdAttribute(): ?string
    {
        return $this->paymentRow?->gateway_id;
    }

    public function getPaymentPaidAtAttribute(): mixed
    {
        return $this->paymentRow?->paid_at;
    }

    public function getPaymentReceivedAmountAttribute(): mixed
    {
        return $this->paymentRow?->received_amount;
    }

    public function getCreatedAttribute(): int
    {
        $at = $this->created_at;

        return $at ? $at->getTimestamp() : 0;
    }
}
