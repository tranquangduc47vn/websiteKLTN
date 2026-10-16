<?php

namespace App\Models;

/**
 * Model cho bảng `stores` (charset utf8mb4, map_link là text).
 *
 * @property int $id
 * @property string $name
 * @property string $address
 * @property string|null $phone
 * @property string|null $map_link
 * @property string|null $city
 */
class Store extends BaseModel
{
    protected $table = 'stores';

    protected function casts(): array
    {
        return [
            'id' => 'int',
        ];
    }
}
