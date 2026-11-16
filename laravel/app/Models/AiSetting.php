<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

/**
 * Cấu hình AI. Chỉ có `updated_at` DATETIME, không có `created_at`.
 *
 * @property int $id
 * @property string $setting_key
 * @property string $setting_value
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class AiSetting extends BaseModel
{
    protected $table = 'ai_settings';

    public const CREATED_AT = null;

    public $timestamps = true;

    protected function casts(): array
    {
        return [
            'id' => 'int',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Đọc giá trị theo key.
     */
    public static function getValue(string $key, ?string $default = null): ?string
    {
        $setting = static::query()->where('setting_key', $key)->first();

        return $setting ? (string) $setting->setting_value : $default;
    }

    /**
     * Ghi/ghi đè giá trị theo key.
     */
    public static function setValue(string $key, string $value): void
    {
        static::query()->updateOrInsert(
            ['setting_key' => $key],
            ['setting_value' => $value, 'updated_at' => now()]
        );
    }

    /**
     * Lấy cờ bật/tắt (so sánh "1"/"true").
     */
    public static function isEnabled(string $key, bool $default = false): bool
    {
        $value = static::getValue($key);

        if ($value === null) {
            return $default;
        }

        return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
    }

    public function scopeByKey($query, string $key): Builder
    {
        return $query->where('setting_key', $key);
    }
}
