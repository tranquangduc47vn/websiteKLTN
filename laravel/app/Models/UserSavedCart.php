<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Giỏ đã lưu. Khóa chính `user_id`. Chỉ có `updated_at` DATETIME.
 *
 * @property int $user_id
 * @property string $cart_json
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class UserSavedCart extends BaseModel
{
    protected $table = 'user_saved_carts';

    protected $primaryKey = 'user_id';

    public $incrementing = false;

    public const CREATED_AT = null;

    public $timestamps = true;

    protected function casts(): array
    {
        return [
            'user_id' => 'int',
            'updated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    /**
     * Giải mã cart_json an toàn (trả mảng rỗng nếu JSON lỗi).
     *
     * @return array<int, array<string, mixed>>
     */
    public function cartItems(): array
    {
        $decoded = json_decode((string) $this->cart_json, true);

        return is_array($decoded) ? $decoded : [];
    }
}
