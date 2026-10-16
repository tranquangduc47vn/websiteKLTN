<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

/**
 * Model cho bảng `master_colors` (bảng màu dùng cho form thêm sản phẩm).
 *
 * @property int $id
 * @property string $name
 * @property string $hex_code
 * @property int $sort_order
 * @property int $status
 */
class MasterColor extends BaseModel
{
    protected $table = 'master_colors';

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
