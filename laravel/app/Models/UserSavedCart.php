<?php

namespace App\Models;

use App\Models\Concerns\LegacyTimestamps;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model cho bảng `user_saved_cart`.
 *
 * Khóa chính là `user_id` (không phải `id`), 1 dòng / 1 user.
 * `cart_json` chứa giỏ hàng đã lưu (đồng bộ từ session `custom_cart`).
 *
 * @property int $user_id
 * @property string $cart_json
 * @property int $updated
 */
class UserSavedCart extends BaseModel
{
    use LegacyTimestamps;

    protected $table = 'user_saved_cart';

    protected $primaryKey = 'user_id';

    /**
     * PK không phải auto-increment.
     *
     * @var bool
     */
    public $incrementing = false;

    protected function casts(): array
    {
        return [
            'user_id' => 'int',
            'updated' => 'int',
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

    public function updatedAt(): ?\Illuminate\Support\Carbon
    {
        return $this->legacyTimestamp('updated');
    }
}
