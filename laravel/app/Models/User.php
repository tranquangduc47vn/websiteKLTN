<?php

namespace App\Models;

use Illuminate\Auth\Authenticatable as AuthenticatableTrait;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Khách hàng. Bảng V2: users. Phone và address nằm ở bảng con.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $password_hash
 * @property int $loyalty_points
 * @property string $loyalty_lifetime_spend
 * @property int $loyalty_completed_orders
 * @property string $loyalty_tier
 * @property string $status
 */
class User extends BaseModel implements Authenticatable
{
    use AuthenticatableTrait;

    protected $table = 'users';

    public $timestamps = true;

    /**
     * Parent để $guarded rỗng. Model này chỉ nhận cột dịch vụ thực sự ghi.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password_hash',
        'loyalty_points',
        'loyalty_lifetime_spend',
        'loyalty_completed_orders',
        'loyalty_tier',
        'status',
    ];

    /** @var list<string> */
    protected $guarded = ['*'];

    public const TIER_MEMBER = 'member';

    public const TIER_SILVER = 'silver';

    public const TIER_GOLD = 'gold';

    public const TIER_DIAMOND = 'diamond';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_DISABLED = 'disabled';

    protected $hidden = [
        'password_hash',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'int',
            'loyalty_points' => 'int',
            'loyalty_lifetime_spend' => 'decimal:2',
            'loyalty_completed_orders' => 'int',
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

    public function savedCart(): HasOne
    {
        return $this->hasOne(UserSavedCart::class, 'user_id', 'id');
    }

    public function pointLogs(): HasMany
    {
        return $this->hasMany(UserPointLog::class, 'user_id');
    }

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

    public function getAuthPasswordName(): string
    {
        return 'password_hash';
    }

    public function getPasswordAttribute(): string
    {
        return (string) ($this->attributes['password_hash'] ?? '');
    }

    public function setPasswordAttribute(string $value): void
    {
        $this->attributes['password_hash'] = $value;
    }

    public function hasLegacyMd5Password(): bool
    {
        return (bool) preg_match('/^[a-f0-9]{32}$/i', (string) $this->password_hash);
    }

    public function getPhoneAttribute(): string
    {
        return (string) ($this->defaultPhone?->phone_number ?? $this->phones()->value('phone_number') ?? '');
    }

    public function getAddressAttribute(): string
    {
        return (string) ($this->defaultAddress?->address_line ?? $this->addresses()->value('address_line') ?? '');
    }

    public function getCreatedAttribute(): int
    {
        $value = $this->created_at;

        return $value ? $value->getTimestamp() : 0;
    }
}
