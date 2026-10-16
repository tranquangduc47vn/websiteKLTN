<?php

namespace App\Models;

use App\Models\Concerns\LegacyTimestamps;
use Illuminate\Auth\Authenticatable as AuthenticatableTrait;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Model cho bảng `user` (khách hàng storefront — KHÁC bảng `admin`).
 *
 * Password có thể là bcrypt ($2y$...) hoặc MD5 32 ký tự (dữ liệu cũ).
 * Cột loyalty_* phục vụ module tích điểm.
 * Cột `created` là INT Unix (một số bản ghi cũ lưu năm dạng 2026).
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $password
 * @property string $phone
 * @property string $address
 * @property int $loyalty_points
 * @property int $loyalty_lifetime_spend
 * @property int $loyalty_completed_orders
 * @property string $loyalty_tier   // member|silver|gold|diamond
 * @property int $created
 */
class User extends BaseModel implements Authenticatable
{
    use AuthenticatableTrait;

    use LegacyTimestamps;

    protected $table = 'user';

    /**
     * Các hạng thành viên theo loyalty_helper.php.
     */
    public const TIER_MEMBER = 'member';
    public const TIER_SILVER = 'silver';
    public const TIER_GOLD = 'gold';
    public const TIER_DIAMOND = 'diamond';

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'int',
            'loyalty_points' => 'int',
            'loyalty_lifetime_spend' => 'int',
            'loyalty_completed_orders' => 'int',
            'created' => 'int',
        ];
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(UserAddress::class, 'user_id');
    }

    public function defaultAddress(): HasOne
    {
        return $this->hasOne(UserAddress::class, 'user_id')->where('is_default', 1);
    }

    public function phones(): HasMany
    {
        return $this->hasMany(UserPhone::class, 'user_id');
    }

    public function defaultPhone(): HasOne
    {
        return $this->hasOne(UserPhone::class, 'user_id')->where('is_default', 1);
    }

    /**
     * Giỏ hàng đã lưu (1-1 theo user_id, PK của bảng là user_id).
     */
    public function savedCart(): HasOne
    {
        return $this->hasOne(UserSavedCart::class, 'user_id', 'id');
    }

    public function pointLogs(): HasMany
    {
        return $this->hasMany(UserPointLog::class, 'user_id');
    }

    /**
     * Đơn hàng của khách — join theo `user_id` hoặc `user_email` (dữ liệu cũ có thể thiếu user_id).
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'user_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class, 'user_id');
    }

    public function passwordResets(): HasMany
    {
        return $this->hasMany(PasswordReset::class, 'user_id');
    }

    public function createdAt(): ?\Illuminate\Support\Carbon
    {
        return $this->legacyTimestamp('created');
    }

    public function setCreatedAtAttribute(mixed $value): void
    {
        $this->setLegacyTimestamp('created', $value);
    }

    /**
     * Kiểm tra hash cũ dạng MD5 (32 hex) để rehash khi login (dùng ở B3).
     */
    public function hasLegacyMd5Password(): bool
    {
        return (bool) preg_match('/^[a-f0-9]{32}$/i', (string) $this->password);
    }
}

