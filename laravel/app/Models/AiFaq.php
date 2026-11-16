<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

/**
 * FAQ trợ lý. `created_at` và `updated_at` là DATETIME.
 *
 * @property int $id
 * @property string $question
 * @property string $answer
 * @property string $keywords
 * @property string $category
 * @property int $sort_order
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class AiFaq extends BaseModel
{
    protected $table = 'ai_faqs';

    public $timestamps = true;

    protected function casts(): array
    {
        return [
            'id' => 'int',
            'sort_order' => 'int',
            'is_active' => 'bool',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
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
}
