<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserAddress extends BaseModel
{
    protected $table = 'user_addresses';

    public const UPDATED_AT = null;

    public $timestamps = true;

    public const MAX_PER_USER = 3;

    protected function casts(): array
    {
        return [
            'id' => 'int',
            'user_id' => 'int',
            'is_default' => 'bool',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', 1);
    }

    public function getAddressNoteAttribute(): string
    {
        return (string) ($this->attributes['note'] ?? '');
    }

    public function setAddressNoteAttribute(string $value): void
    {
        $this->attributes['note'] = $value;
    }
}
