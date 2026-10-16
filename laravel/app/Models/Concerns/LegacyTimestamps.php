<?php

namespace App\Models\Concerns;

use Illuminate\Support\Carbon;

/**
 * Legacy schema dùng cột timestamp kiểu INT (Unix seconds) và cột boolean TINYINT(1).
 * Trait này giữ nguyên tên cột như DB gốc nhưng trả về Carbon / bool cho tầng ứng dụng.
 */
trait LegacyTimestamps
{
    /**
     * Đọc cột int timestamp và trả Carbon (null nếu 0/rỗng).
     * Cache nằm ở BaseModel::$legacyTimestampCache.
     */
    protected function legacyTimestamp(string $column): ?Carbon
    {
        if (array_key_exists($column, $this->legacyTimestampCache)) {
            return $this->legacyTimestampCache[$column];
        }

        $value = $this->getAttributeFromArray($column);

        if ($value === null || $value === '' || (int) $value === 0) {
            return $this->legacyTimestampCache[$column] = null;
        }

        return $this->legacyTimestampCache[$column] = Carbon::createFromTimestamp((int) $value);
    }

    /**
     * Ghi Carbon|int|null vào cột int timestamp.
     */
    protected function setLegacyTimestamp(string $column, mixed $value): void
    {
        unset($this->legacyTimestampCache[$column]);

        if ($value === null || $value === '') {
            $this->attributes[$column] = 0;

            return;
        }

        if ($value instanceof \DateTimeInterface) {
            $this->attributes[$column] = $value->getTimestamp();

            return;
        }

        $this->attributes[$column] = (int) $value;
    }
}
