<?php

namespace App\Models;

/**
 * Nhà cung cấp. `created_at` và `updated_at` là DATETIME.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string $contact_name
 * @property string $phone
 * @property string $email
 * @property string $address
 * @property string|null $note
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Supplier extends BaseModel
{
    protected $table = 'suppliers';

    public $timestamps = true;

    protected function casts(): array
    {
        return [
            'id' => 'int',
            'status' => 'string',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
