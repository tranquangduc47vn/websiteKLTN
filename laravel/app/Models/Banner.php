<?php

namespace App\Models;

/**
 * Model cho bảng `banner` (chưa dùng trong CI3 hiện tại nhưng có trong DB).
 *
 * @property int $id
 * @property string $name
 * @property string $image_link
 * @property string $link
 * @property int $sort_order
 */
class Banner extends BaseModel
{
    protected $table = 'banners';

    protected function casts(): array
    {
        return [
            'id' => 'int',
            'sort_order' => 'int',
        ];
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }
}
