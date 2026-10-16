<?php

namespace App\Models;

use App\Models\Concerns\LegacyTimestamps;
use Illuminate\Database\Eloquent\Builder;

/**
 * Model cho bảng `ai_setting` (MyISAM — không FK, khóa duy nhất `setting_key`).
 *
 * Cột `setting_value` lưu chuỗi, đa số là "0"/"1" (cờ bật/tắt) hoặc văn bản.
 *
 * @property int $id
 * @property string $setting_key
 * @property string $setting_value
 * @property int $updated
 */
class AiSetting extends BaseModel
{
    use LegacyTimestamps;

    protected $table = 'ai_setting';

    protected function casts(): array
    {
        return [
            'id' => 'int',
            'updated' => 'int',
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
            ['setting_value' => $value, 'updated' => now()->timestamp]
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

    public function updatedAt(): ?\Illuminate\Support\Carbon
    {
        return $this->legacyTimestamp('updated');
    }
}
