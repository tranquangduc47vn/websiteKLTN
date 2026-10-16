<?php

namespace App\Models;

use App\Models\Concerns\LegacyTimestamps;

/**
 * Model cho bảng `suppliers`.
 * Timestamp `created`, `updated` dạng INT Unix.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string $contact_name
 * @property string $phone
 * @property string $email
 * @property string $address
 * @property string|null $note
 * @property int $status
 * @property int $created
 * @property int $updated
 */
class Supplier extends BaseModel
{
    use LegacyTimestamps;

    protected $table = 'suppliers';

    protected function casts(): array
    {
        return [
            'id' => 'int',
            'status' => 'int',
            'created' => 'int',
            'updated' => 'int',
        ];
    }

    public function createdAt(): ?\Illuminate\Support\Carbon
    {
        return $this->legacyTimestamp('created');
    }

    public function updatedAt(): ?\Illuminate\Support\Carbon
    {
        return $this->legacyTimestamp('updated');
    }
}
