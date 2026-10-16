<?php

namespace App\Models;

use Illuminate\Auth\Authenticatable as AuthenticatableTrait;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Model cho bảng `admin`.
 *
 * Bảng lưu tài khoản quản trị ở hệ thống CI3 (role 0/1/2).
 * Một số bản ghi còn hash MD5 (e10adc3949...), phải rehash khi login (xử lý ở B3).
 *
 * Cột timestamp: `created` = INT Unix.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $password  // bcrypt ($2y$) hoặc MD5 (cần rehash)
 * @property int $level       // ROLE_ADMIN=0, ROLE_MOD=1, ROLE_USER=2
 * @property int $created
 */
class Admin extends BaseModel implements Authenticatable
{
    use AuthenticatableTrait;

    use Concerns\LegacyTimestamps;

    protected $table = 'admin';

    /**
     * Hằng role — giữ nguyên giá trị CI3 (ROLE_ADMIN=0...), dùng để migrate permission_helper.php.
     */
    public const ROLE_ADMIN = 0;
    public const ROLE_MOD = 1;
    public const ROLE_USER = 2;

    protected function casts(): array
    {
        return [
            'id' => 'int',
            'level' => 'int',
            'created' => 'int',
        ];
    }

    /**
     * Role kiểm duyệt đơn hàng (xem `admin_can("order.manage")` trong permission_helper.php).
     */
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

    public function createdAt(): ?\Illuminate\Support\Carbon
    {
        return $this->legacyTimestamp('created');
    }

    public function setCreatedAtAttribute(mixed $value): void
    {
        $this->setLegacyTimestamp('created', $value);
    }
}

