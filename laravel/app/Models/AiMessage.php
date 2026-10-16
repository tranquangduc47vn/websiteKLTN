<?php

namespace App\Models;

use App\Models\Concerns\LegacyTimestamps;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model cho bảng `ai_message`.
 *
 * `sender`: customer | ai | staff | system.
 * `meta` là JSON string (intent handoff, staff_name, ...).
 *
 * @property int $id
 * @property int $conversation_id
 * @property string $sender
 * @property string $content
 * @property string $meta
 * @property int $created
 */
class AiMessage extends BaseModel
{
    use LegacyTimestamps;

    protected $table = 'ai_message';

    public const SENDER_CUSTOMER = 'customer';
    public const SENDER_AI = 'ai';
    public const SENDER_STAFF = 'staff';
    public const SENDER_SYSTEM = 'system';

    protected function casts(): array
    {
        return [
            'id' => 'int',
            'conversation_id' => 'int',
            'created' => 'int',
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

    public function createdAt(): ?\Illuminate\Support\Carbon
    {
        return $this->legacyTimestamp('created');
    }
}
