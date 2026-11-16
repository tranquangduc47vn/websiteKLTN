<?php

namespace App\Models;

/**
 * Model cho bảng `slider` (MyISAM, không FK).
 * Timestamp `created` dạng DATETIME.
 *
 * @property int $id
 * @property string $name
 * @property string $image_link
 * @property string $link
 * @property int $sort_order
 * @property string|null $created // DATETIME
 */
class Slider extends BaseModel
{
    protected $table = 'sliders';

    protected function casts(): array
    {
        return [
            'id' => 'int',
            'sort_order' => 'int',
            'created' => 'datetime',
        ];
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }
}
