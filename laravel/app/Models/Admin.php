<?php

namespace App\Models;

use Illuminate\Auth\Authenticatable as AuthenticatableTrait;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Quản trị. Bảng V2: admins.
 * level: 0 admin, 1 moderator, 2 staff.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $password_hash
 * @property int $level
 */
class Admin extends BaseModel implements Authenticatable
{
    use AuthenticatableTrait;

    protected $table = 'admins';

    public $timestamps = true;

    public const ROLE_ADMIN = 0;

    public const ROLE_MOD = 1;

    public const ROLE_USER = 2;

    protected $hidden = [
        'password_hash',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'int',
            'level' => 'int',
        ];
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

    public function isAdmin(): bool
    {
        return (int) $this->level === self::ROLE_ADMIN;
    }

    public function isMod(): bool
    {
        return (int) $this->level === self::ROLE_MOD;
    }

    public function isRegular(): bool
    {
        return (int) $this->level === self::ROLE_USER;
    }

    public function isStaff(): bool
    {
        return in_array((int) $this->level, [self::ROLE_ADMIN, self::ROLE_MOD], true);
    }

    public function hasLegacyMd5Password(): bool
    {
        return (bool) preg_match('/^[a-f0-9]{32}$/i', (string) $this->password_hash);
    }
}
