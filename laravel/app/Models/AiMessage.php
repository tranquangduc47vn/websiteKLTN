<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tin nhắn AI. Chỉ có `created_at` DATETIME, không có `updated_at`.
 *
 * @property int $id
 * @property int $conversation_id
 * @property string $sender
 * @property string $content
 * @property string $meta
 * @property \Illuminate\Support\Carbon|null $created_at
 */
class AiMessage extends BaseModel
{
    protected $table = 'ai_messages';

    public const UPDATED_AT = null;

    public $timestamps = true;

    public const SENDER_CUSTOMER = 'customer';
    public const SENDER_AI = 'ai';
    public const SENDER_STAFF = 'staff';
    public const SENDER_SYSTEM = 'system';

    protected function casts(): array
    {
        return [
            'id' => 'int',
            'conversation_id' => 'int',
            'created_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'conversation_id');
    }

    /**
     * Giải mã meta an toàn.
     *
     * @return array<string, mixed>
     */
    public function metaArray(): array
    {
        $decoded = json_decode((string) $this->meta, true);

        return is_array($decoded) ? $decoded : [];
    }
}
