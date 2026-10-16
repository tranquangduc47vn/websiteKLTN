<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

/**
 * Model cho bảng `content_pages` (charset utf8mb4, có created_at chuẩn).
 *
 * Cột `type` phân loại nội dung tĩnh:
 *   news      → bài báo (route /tin-tuc)
 *   page      → trang tĩnh (vd: gioi-thieu)
 *   tuyendung → tuyển dụng
 *   hop_tac   → hợp tác kinh doanh
 *   van_chuyen→ chính sách vận chuyển
 *
 * `legacy_id` giữ id cũ để giữ URL cũ (route seo_url_helper.php).
 *
 * @property int $id
 * @property string $type
 * @property int $legacy_id
 * @property string $title
 * @property string $slug
 * @property string|null $intro
 * @property string|null $content
 * @property string|null $image
 * @property string|null $created_at  // DATETIME
 */
class ContentPage extends BaseModel
{
    protected $table = 'content_pages';

    public const TYPE_NEWS = 'news';
    public const TYPE_PAGE = 'page';
    public const TYPE_TUYENDUNG = 'tuyendung';
    public const TYPE_HOP_TAC = 'hop_tac';
    public const TYPE_VAN_CHUYEN = 'van_chuyen';

    protected function casts(): array
    {
        return [
            'id' => 'int',
            'legacy_id' => 'int',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function scopeOfType($query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeBySlug($query, string $slug): Builder
    {
        return $query->where('slug', $slug);
    }

    public function scopeLatest($query): Builder
    {
        return $query->orderByDesc('created_at')->orderByDesc('id');
    }
}
