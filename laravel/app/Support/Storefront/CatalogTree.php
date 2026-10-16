<?php

namespace App\Support\Storefront;

use App\Models\Catalog;

/**
 * B4 - Tiện ích cây danh mục catalog, port từ CI3 (Catalog_model + các controller).
 *
 * Quy ước dữ liệu (đã kiểm chứng trên DB thật):
 *   id=1 'Thời trang' là node gốc (parent_id=0)
 *   danh mục cấp 1  → parent_id = 1
 *   danh mục cấp 2  → parent_id = <id cấp 1>
 *
 * CI3 MY_Controller::__construct() nạp menu: parent_id=1, order sort_order ASC,
 * mỗi parent gắn ->sub (con trực tiếp). Giữ y hệt.
 */
class CatalogTree
{
    /**
     * Menu header: danh mục cấp 1 (parent_id=1) + con trực tiếp ở thuộc tính 'sub'.
     *
     * @return \Illuminate\Support\Collection<int, Catalog>
     */
    public static function menu(): \Illuminate\Support\Collection
    {
        $parents = Catalog::query()
            ->where('parent_id', 1)
            ->orderBy('sort_order')
            ->get();

        $childIds = $parents->pluck('id')->all();

        $children = $childIds === []
            ? collect()
            : Catalog::query()
                ->whereIn('parent_id', $childIds)
                ->orderBy('sort_order')
                ->get()
                ->groupBy('parent_id');

        foreach ($parents as $parent) {
            $parent->setRelation('subs', $children->get($parent->id, collect()));
        }

        return $parents;
    }

    /**
     * Sidebar filter: giống CI3 _load_listing_sidebar() — cấp 1 (parent_id=1)
     * kèm 'subs' là con trực tiếp, order by id ASC (CI3 dùng id ASC ở đây).
     *
     * @return \Illuminate\Support\Collection<int, Catalog>
     */
    public static function sidebar(): \Illuminate\Support\Collection
    {
        $parents = Catalog::query()
            ->where('parent_id', 1)
            ->orderBy('id')
            ->get();

        $children = Catalog::query()
            ->whereIn('parent_id', $parents->pluck('id')->all() ?: [0])
            ->orderBy('id')
            ->get()
            ->groupBy('parent_id');

        foreach ($parents as $parent) {
            $parent->setRelation('subs', $children->get($parent->id, collect()));
        }

        return $parents;
    }

    /**
     * Danh mục cấp 1 (parent_id = $rootId) + con trực tiếp — dùng cho bộ lọc catalog.
     *
     * @return \Illuminate\Support\Collection<int, Catalog>
     */
    public static function childrenOf(int $rootId): \Illuminate\Support\Collection
    {
        $parents = Catalog::query()
            ->where('parent_id', $rootId)
            ->orderBy('id')
            ->get();

        $children = Catalog::query()
            ->whereIn('parent_id', $parents->pluck('id')->all() ?: [0])
            ->orderBy('id')
            ->get()
            ->groupBy('parent_id');

        foreach ($parents as $parent) {
            $parent->setRelation('subs', $children->get($parent->id, collect()));
        }

        return $parents;
    }

    /**
     * Mở rộng danh sách id danh mục đã chọn xuống hết cấp con (tối đa 2 cấp),
     * đúng logic CI3 trong catalog()/hot()/news()/discount()/search().
     *
     * @param  array<int,mixed>  $chosenIds
     * @return array<int,int>
     */
    public static function expandIds(array $chosenIds): array
    {
        $ids = [];

        foreach ($chosenIds as $id) {
            $id = (int) $id;
            if ($id <= 0) {
                continue;
            }
            $ids[] = $id;
        }

        $level1 = $ids === [] ? [] : Catalog::query()->whereIn('parent_id', $ids)->pluck('id')->all();
        $level2 = $level1 === [] ? [] : Catalog::query()->whereIn('parent_id', $level1)->pluck('id')->all();

        return array_values(array_unique(array_merge($ids, $level1, $level2)));
    }

    /**
     * CI3: khi không chọn category thì lấy chính id + toàn bộ con (2 cấp).
     *
     * @return array<int,int>
     */
    public static function selfAndDescendants(int $id): array
    {
        return $id <= 0 ? [] : self::expandIds([$id]);
    }
}
