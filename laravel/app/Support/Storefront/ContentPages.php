<?php

namespace App\Support\Storefront;

use App\Models\ContentPage;

/**
 * B4 - Truy cập nội dung tĩnh trên bảng hợp nhất content_pages.
 *
 * Port nguyên văn Content_page_model của CI3:
 *   - mọi query đều ép thêm type = <content_type>
 *   - get_info($id): thử id -> fallback legacy_id
 *   - get_list(): orderByDesc('created_at') theo CI3 (intval timestamp DESC)
 *   - get_by_slug(): theo slug
 *
 * CHỈ ĐỌC phía storefront ở B4 (tạo/sửa là admin, ngoài phạm vi B4).
 */
class ContentPages
{
    public const TYPE_NEWS = 'news';
    public const TYPE_PAGE = 'page';
    public const TYPE_TUYENDUNG = 'tuyendung';
    public const TYPE_HOP_TAC = 'hop_tac';
    public const TYPE_VAN_CHUYEN = 'van_chuyen';

    /** Danh sách có phân trang theo type (CI3 get_list + limit). */
    public static function list(string $type, int $limit = 0, int $offset = 0, string $order = 'created_at', string $dir = 'DESC')
    {
        $query = ContentPage::query()->ofType($type);

        if ($limit > 0) {
            $query->limit($limit)->offset($offset);
        }

        return $query
            ->orderBy($order === 'id' ? 'id' : 'created_at', $dir)
            ->orderBy('id', $dir)
            ->get();
    }

    /** Tổng số bản ghi theo type (CI3 get_total). */
    public static function count(string $type): int
    {
        return (int) ContentPage::query()->ofType($type)->count();
    }

    /**
     * CI3 Content_page_model::get_info(): thử id, rồi legacy_id.
     * KHÔNG tìm kiếm theo slug nên luôn là một bản ghi hoặc null.
     */
    public static function info(int $id): ?ContentPage
    {
        if ($id <= 0) {
            return null;
        }

        return ContentPage::query()
            ->where(fn ($q) => $q->where('id', $id)->orWhere('legacy_id', $id))
            ->first();
    }

    /** CI3 get_info($id, 'type') cho controller theo type cụ thể. */
    public static function infoByType(string $type, int $id): ?ContentPage
    {
        if ($id <= 0) {
            return null;
        }

        return ContentPage::query()
            ->ofType($type)
            ->where(fn ($q) => $q->where('id', $id)->orWhere('legacy_id', $id))
            ->first();
    }

    /** CI3 Page_model::get_by_slug(). */
    public static function bySlug(string $type, string $slug): ?ContentPage
    {
        $slug = trim($slug);

        if ($slug === '') {
            return null;
        }

        return ContentPage::query()->ofType($type)->where('slug', $slug)->first();
    }

    /** CI3 Tuyendung/Hoptac::view() — tra theo slug + type. */
    public static function firstBySlug(string $type, string $slug): ?ContentPage
    {
        return self::bySlug($type, $slug);
    }

    /**
     * CI3 normalize_row(): DB dùng image, các view news cần image_link,
     * created_at (datetime) -> created (string) cho view gốc.
     */
    public static function normalize(?ContentPage $page): ?ContentPage
    {
        if ($page === null) {
            return null;
        }

        $page->image_link = (string) ($page->image ?? '');
        $page->created = $page->created_at?->format('Y-m-d H:i:s') ?? '';

        return $page;
    }

    /**
     * Danh sách N tin mới nhất dùng cho sidebar (CI3: limit 6, order id DESC)
     * và trang chi tiết.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, ContentPage>
     */
    public static function latestNews(int $limit = 6)
    {
        return self::list(self::TYPE_NEWS, $limit, 0, 'id', 'DESC');
    }
}
