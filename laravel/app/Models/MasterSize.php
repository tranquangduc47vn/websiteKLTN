<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

/**
 * Model cho bảng `master_sizes` (S/M/L/XL/XXL).
 *
 * @property int $id
 * @property string $name
 * @property int $sort_order
 * @property int $status
 */
class MasterSize extends BaseModel
{
    protected $table = 'master_sizes';

    protected function casts(): array
    {
        return [
            'id' => 'int',
            'sort_order' => 'int',
            'status' => 'int',
        ];
    }

    public function scopeActive($query): Builder
    {
        return $query->where('status', 1);
    }

    public function scopeOrdered($query): Builder
    {
        return $query->orderBy('sort_order');
    }
}
