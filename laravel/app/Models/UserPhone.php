<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPhone extends BaseModel
{
    protected $table = 'user_phones';

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

    public function getPhoneLabelAttribute(): string
    {
        return (string) ($this->attributes['label'] ?? '');
    }

    public function setPhoneLabelAttribute(string $value): void
    {
        $this->attributes['label'] = $value;
    }
}
