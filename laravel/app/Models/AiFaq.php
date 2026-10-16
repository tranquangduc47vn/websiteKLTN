<?php

namespace App\Models;

use App\Models\Concerns\LegacyTimestamps;
use Illuminate\Database\Eloquent\Builder;

/**
 * Model cho bảng `ai_faq` (câu hỏi thường gặp, dùng cho FaqMatcher).
 *
 * @property int $id
 * @property string $question
 * @property string $answer
 * @property string $keywords
 * @property string $category
 * @property int $sort_order
 * @property int $is_active
 * @property int $created
 * @property int $updated
 */
class AiFaq extends BaseModel
{
    use LegacyTimestamps;

    protected $table = 'ai_faq';

    protected function casts(): array
    {
        return [
            'id' => 'int',
            'sort_order' => 'int',
            'is_active' => 'bool',
            'created' => 'int',
            'updated' => 'int',
        ];
    }

    public function scopeActive($query): Builder
    {
        return $query->where('is_active', 1);
    }

    public function scopeOrdered($query): Builder
    {
        return $query->orderBy('sort_order');
    }

    public function keywordsList(): array
    {
        if (trim((string) $this->keywords) === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', (string) $this->keywords))));
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
