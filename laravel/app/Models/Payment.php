<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Một order có đúng một payment.
 *
 * @property int $id
 * @property int $order_id
 * @property string $method
 * @property string|null $status
 * @property string|null $reference
 * @property string|null $gateway_id
 * @property string|null $received_amount
 */
class Payment extends BaseModel
{
    protected $table = 'payments';

    public $timestamps = true;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'order_id',
        'method',
        'status',
        'reference',
        'gateway_id',
        'received_amount',
        'paid_at',
    ];

    /** @var list<string> */
    protected $guarded = ['*'];

    public const METHOD_COD = 'cod';

    public const METHOD_BANK = 'bank_transfer';

    public const STATUS_COD = 'cod';

    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_REVIEW = 'review';

    public const STATUS_MISMATCH = 'mismatch';

    protected function casts(): array
    {
        return [
            'id' => 'int',
            'order_id' => 'int',
            'received_amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'order_id');
    }
}
