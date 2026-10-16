<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Catalog;
use App\Models\Product;
use App\Services\ReviewService;
use App\Support\Storefront\CatalogTree;
use App\Support\Storefront\Flash;
use App\Support\Storefront\PriceFilters;
use App\Support\Storefront\Product as ProductHelper;
use App\Support\Storefront\Shop;
use App\Support\Storefront\Slug;
use App\Support\Storefront\Urls;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * B4 - Storefront Product: catalog, chi tiet, tim kiem, danh sach noi bat, danh gia.
 *
 * Port tu CI3 application/controllers/Product.php.
 * Cac helper private cua CI3 (_apply_product_catalog_filters,
 * _apply_listing_attribute_filters, _get_catalog_menu, _load_listing_sidebar)
 * duoc thay bang Eloquent scope + CatalogTree de khong lap lai query.
 */
class ProductController extends Controller
{
    /** per_page co dinh cua CI3. */
    protected const PER_PAGE = 8;

    public function index(): View
    {
        return $this->renderSub("site/product/index", ["page_title" => "San pham"]);
    }

    /**
     * CI3 product/catalog_seo($slug, $price_slug, $offset).
     */
    public function catalogSeo(Request $request, ?string $slug = null, $offset = 0): View|RedirectResponse
    {
        // price_slug khong nam trong URI -> lay tu route defaults cua Laravel.
        $priceSlug = (string) ($request->route()->defaults["price_slug"] ?? "");
        $slug = (string) ($slug ?? "");
        $offset = (int) $offset;

        if ($priceSlug === "_") {
            $priceSlug = "";
        }

        $id = $this->resolveCatalogIdBySlug($slug);
        if ($id <= 0) {
            abort(404);
        }

        $range = null;
        if ($priceSlug !== "") {
            $range = PriceFilters::slugToRange($priceSlug);
            if ($range === "") {
                abort(404);
            }
        }

        return $this->catalog($request, $id, $offset, $priceSlug, $range);
    }

    /**
     * CI3 product/catalog - danh muc + filter + phan trang theo segment.
     */
    public function catalog(Request $request, $catalog = 0, $seo_offset = null, $seo_price_slug = null, $rangeOverride = null): View|\Illuminate\Http\RedirectResponse
    {
        $catalogId = (int) ($catalog instanceof Catalog ? $catalog->id : $catalog);
        $catalogModel = Catalog::query()->find($catalogId);

        if (! $catalogModel) {
            return $this->renderSub("site/product/index", ["page_title" => "San pham"]);
        }

        $priceRange = $rangeOverride !== null ? $rangeOverride : $request->query("price_range");

        // CI3: truy cap /product/catalog/... ma co price_range -> 301 sang URL SEO.
        if ($request->segment(1) === "product" && $request->segment(2) === "catalog" && ! empty($priceRange)) {
            return redirect()->to(Urls::category($catalogModel, $priceRange), 301);
        }

        $finalCatalogIds = $this->resolveFinalCatalogIds($request, (int) $catalogModel->id);

        $builder = $this->applyFilters(Product::query(), $finalCatalogIds, $priceRange);
        $total = (clone $builder)->count();

        $hasFilters = $request->filled("category") || $request->filled("size")
            || $request->filled("color") || ! empty($priceRange);

        // Xac dinh "danh muc goc" cap 1 (di nguoi cay len toi node co parent_id = 1).
        [$currentRootId, $currentRootTitle] = $this->catalogRoot($catalogModel);

        $catalogList = $currentRootId > 0 ? CatalogTree::childrenOf($currentRootId) : collect();

        // Phan trang theo URI segment nhu CI3 (uri_segment 2 hoac 3).
        if ($seo_offset !== null) {
            $segment = (int) $seo_offset;
        } elseif ($request->segment(1) === "product" && $request->segment(2) === "catalog") {
            $segment = (int) $request->segment(4);
        } else {
            $segment = (int) $request->segment(($seo_price_slug !== null && $seo_price_slug !== "") ? 3 : 2);
        }
        $page = (int) floor(max(0, $segment) / self::PER_PAGE) + 1;

        $productList = $this->applySort($builder, $request)
            ->with("catalog")
            ->forPage($page, self::PER_PAGE)
            ->get();

        $canonical = Urls::category($catalogModel, $priceRange ?: null);
        if ($segment > 0) {
            $canonical = rtrim($canonical, "/") . "/" . $segment;
        }

        return $this->renderSub("site/product/catalog", [
            "sf_temp" => "site/product/catalog",
            "catalog_p" => $catalogModel,
            "catalog_list" => $catalogList,
            "master_colors" => $this->masterColors(),
            "main_catalog_title" => $hasFilters ? $currentRootTitle : $catalogModel->name,
            "product_list" => $productList,
            "total" => $total,
            "pagination" => $this->segmentPaginator($total, $catalogModel, $priceRange, $request, $segment, $page),
            "filter_action" => Urls::category($catalogModel),
            "canonical_url" => $canonical,
            "page_title" => $hasFilters ? $currentRootTitle : $catalogModel->name,
            "chosen_cats" => $this->arrayParam($request->query("category")),
            "chosen_sizes" => $this->arrayParam($request->query("size")),
            "chosen_colors" => $this->arrayParam($request->query("color")),
            "chosen_price" => $priceRange,
            "current_sort" => (string) ($request->query("sort") ?: "new"),
            "sort_options" => ["new" => "Moi nhat", "price_asc" => "Gia tang dan", "price_desc" => "Gia giam dan"],
        ]);
    }

    /** CI3 product/hot - phan trang query string (?per_page=). */
    public function hot(Request $request): View
    {
        $priceRange = $request->query("price_range");
        $builder = $this->applyFilters(
            Product::query()->active()->where("buyed", ">", 0),
            $this->resolveFinalCatalogIds($request, 0),
            $priceRange
        );
        $total = (clone $builder)->count();

        $segment = max(0, (int) $request->query("per_page"));
        $page = (int) floor($segment / self::PER_PAGE) + 1;

        $productList = $this->applySort($builder, $request, "buyed", "DESC")
            ->with("catalog")
            ->forPage($page, self::PER_PAGE)
            ->get();

        return $this->renderSub("site/product/hot", [
            "sf_temp" => "site/product/hot",
            "product_list" => $productList,
            "total" => $total,
            "main_catalog_title" => "San pham ban chay",
            "filter_action" => url("ban-chay"),
            "canonical_url" => url("ban-chay"),
            "catalog_list" => CatalogTree::childrenOf(1),
            "master_colors" => $this->masterColors(),
            "is_hot" => true,
            "is_hot_list" => true,
            "pagination" => $this->queryPaginator($total, url("ban-chay"), $request, $segment),
            "page_title" => "San pham ban chay - " . Shop::name(),
            "chosen_cats" => $this->arrayParam($request->query("category")),
            "chosen_sizes" => $this->arrayParam($request->query("size")),
            "chosen_colors" => $this->arrayParam($request->query("color")),
            "chosen_price" => $priceRange,
            "current_sort" => (string) ($request->query("sort") ?: "hot"),
            "sort_options" => ["hot" => "Ban chay nhat", "new" => "Moi nhat", "price_asc" => "Gia tang dan", "price_desc" => "Gia giam dan"],
        ]);
    }

    /** CI3 product/views - phan trang theo segment. */
    public function views(Request $request, $offset = 0): View
    {
        if ($request->segment(1) === "product") {
            $offset = (int) $request->segment(4);
        }
        $offset = (int) $offset;

        $builder = Product::query()->active();
        $total = (clone $builder)->count();
        $page = (int) floor(max(0, $offset) / self::PER_PAGE) + 1;

        $productList = $builder->with("catalog")->orderByDesc("view")
            ->forPage($page, self::PER_PAGE)->get();

        return $this->renderSub("site/product/views", [
            "sf_temp" => "site/product/views",
            "product_list" => $productList,
            "total" => $total,
            "main_catalog_title" => "San pham xem nhieu",
            "filter_action" => url("product/views"),
            "canonical_url" => url("product/views"),
            "catalog_list" => collect(),
            "master_colors" => $this->masterColors(),
            "pagination" => $this->segmentPaginator($total, null, null, $request, $offset, $page, url("product/views")),
            "page_title" => "San pham xem nhieu - " . Shop::name(),
            "chosen_cats" => [],
            "chosen_sizes" => [],
            "chosen_colors" => [],
            "chosen_price" => null,
            "current_sort" => "new",
            "sort_options" => ["new" => "Moi nhat", "price_asc" => "Gia tang dan", "price_desc" => "Gia giam dan"],
        ]);
    }

    /** CI3 product/news - san pham moi, phan trang query string. */
    public function news(Request $request): View
    {
        $priceRange = $request->query("price_range");
        $builder = $this->applyFilters(
            Product::query()->active(),
            $this->resolveFinalCatalogIds($request, 0),
            $priceRange
        );
        $total = (clone $builder)->count();

        $segment = max(0, (int) $request->query("per_page"));
        $page = (int) floor($segment / self::PER_PAGE) + 1;

        $productList = $this->applySort($builder, $request, "id", "DESC")
            ->with("catalog")
            ->forPage($page, self::PER_PAGE)
            ->get();

        return $this->renderSub("site/product/new", [
            "sf_temp" => "site/product/new",
            "product_list" => $productList,
            "total" => $total,
            "main_catalog_title" => "San pham moi",
            "filter_action" => url("moi"),
            "canonical_url" => url("moi"),
            "catalog_list" => CatalogTree::childrenOf(1),
            "master_colors" => $this->masterColors(),
            "pagination" => $this->queryPaginator($total, url("moi"), $request, $segment),
            "page_title" => "San pham moi nhat - " . Shop::name(),
            "chosen_cats" => $this->arrayParam($request->query("category")),
            "chosen_sizes" => $this->arrayParam($request->query("size")),
            "chosen_colors" => $this->arrayParam($request->query("color")),
            "chosen_price" => $priceRange,
            "current_sort" => (string) ($request->query("sort") ?: "new"),
            "sort_options" => ["new" => "Moi nhat", "price_asc" => "Gia tang dan", "price_desc" => "Gia giam dan"],
        ]);
    }

    /**
     * CI3 product/discount - hai che do phan trang: /khuyen-mai/{offset} (segment 2)
     * va /product/discount (uri_segment 4 hoac query string).
     */
    public function discount(Request $request, $offset = 0): View
    {
        $priceRange = $request->query("price_range");
        $builder = $this->applyFilters(
            Product::query()->active()->where("discount", ">", 0),
            $this->resolveFinalCatalogIds($request, 0),
            $priceRange
        );
        $total = (clone $builder)->count();

        if ($request->segment(1) === "product" && $request->segment(2) === "discount") {
            $offset = (int) $request->segment(4);
        }
        $segment = max(0, (int) $offset);
        $page = (int) floor($segment / self::PER_PAGE) + 1;

        $productList = $this->applySort($builder, $request, "discount", "DESC")
            ->with("catalog")
            ->forPage($page, self::PER_PAGE)
            ->get();

        return $this->renderSub("site/product/discount", [
            "sf_temp" => "site/product/discount",
            "product_list" => $productList,
            "total" => $total,
            "main_catalog_title" => "San pham khuyen mai",
            "filter_action" => url("khuyen-mai"),
            "canonical_url" => url("khuyen-mai"),
            "catalog_list" => CatalogTree::childrenOf(1),
            "master_colors" => $this->masterColors(),
            "is_discount_list" => true,
            "pagination" => $this->segmentPaginator($total, null, null, $request, $segment, $page, url("khuyen-mai")),
            "page_title" => "San pham khuyen mai - " . Shop::name(),
            "chosen_cats" => $this->arrayParam($request->query("category")),
            "chosen_sizes" => $this->arrayParam($request->query("size")),
            "chosen_colors" => $this->arrayParam($request->query("color")),
            "chosen_price" => $priceRange,
            "current_sort" => (string) ($request->query("sort") ?: "new"),
            "sort_options" => ["new" => "Moi nhat", "price_asc" => "Gia tang dan", "price_desc" => "Gia giam dan"],
        ]);
    }

    /**
     * CI3 product/search_seo($keyword_slug, $offset) - /tim-kiem/{slug}/{offset}.
     */
    public function searchSeo(Request $request, $keyword = null, $offset = null): View|\Illuminate\Http\RedirectResponse
    {
        if (($keyword === null || $keyword === "") && $request->filled("key-search")) {
            return redirect()->to(Urls::search((string) $request->query("key-search")), 301);
        }

        $kw = $keyword === null ? "" : Urls::keywordFromSlug((string) $keyword);

        return $this->search($request, $kw, $offset);
    }

    /** CI3 product/search. */
    public function search(Request $request, $keyword = null, $seo_offset = null): View
    {
        $keyword = trim((string) ($keyword ?? $request->query("key-search", "")));
        $hasFilters = $request->filled("category") || $request->filled("size")
            || $request->filled("color") || $request->filled("price_range");
        $priceRange = $request->query("price_range");

        $sortOptions = ["new" => "Moi nhat", "price_asc" => "Gia tang dan", "price_desc" => "Gia giam dan"];

        if ($keyword === "" && ! $hasFilters) {
            return $this->renderSub("site/product/search", [
                "sf_temp" => "site/product/search",
                "total" => 0,
                "product_list" => collect(),
                "keyword" => "",
                "is_search_page" => true,
                "main_catalog_title" => "Ket qua tim kiem",
                "filter_action" => Urls::search(""),
                "canonical_url" => Urls::search(""),
                "catalog_list" => CatalogTree::childrenOf(1),
                "master_colors" => $this->masterColors(),
                "pagination" => "",
                "chosen_cats" => [],
                "chosen_sizes" => [],
                "chosen_colors" => [],
                "chosen_price" => null,
                "current_sort" => "new",
                "sort_options" => $sortOptions,
            ]);
        }

        $finalCatalogIds = $this->resolveFinalCatalogIds($request, 0);

        $builder = $this->applyFilters(Product::query()->active(), $finalCatalogIds, $priceRange)
            ->search($keyword !== "" ? $keyword : null, true);
        $total = (clone $builder)->count();

        $useSeoPagination = $request->segment(1) === "tim-kiem";
        $segment = $useSeoPagination
            ? ($seo_offset !== null ? (int) $seo_offset : (int) $request->segment(3))
            : (int) $request->query("per_page");
        $segment = max(0, $segment);
        $page = (int) floor($segment / self::PER_PAGE) + 1;

        $productList = $this->applySort($builder, $request, "id", "DESC")
            ->with("catalog")
            ->forPage($page, self::PER_PAGE)
            ->get();

        $canonical = Urls::search($keyword);
        if ($useSeoPagination && $segment > 0) {
            $canonical = rtrim($canonical, "/") . "/" . $segment;
        }

        return $this->renderSub("site/product/search", [
            "sf_temp" => "site/product/search",
            "total" => $total,
            "product_list" => $productList,
            "keyword" => $keyword,
            "is_search_page" => true,
            "main_catalog_title" => "Ket qua tim kiem",
            "filter_action" => Urls::search($keyword),
            "canonical_url" => $canonical,
            "catalog_list" => CatalogTree::childrenOf(1),
            "master_colors" => $this->masterColors(),
            "pagination" => $useSeoPagination
                ? $this->segmentPaginator($total, null, null, $request, $segment, $page, rtrim(Urls::search($keyword), "/") . "/")
                : $this->queryPaginator($total, Urls::search($keyword), $request, $segment),
            "chosen_cats" => $this->arrayParam($request->query("category")),
            "chosen_sizes" => $this->arrayParam($request->query("size")),
            "chosen_colors" => $this->arrayParam($request->query("color")),
            "chosen_price" => $priceRange,
            "current_sort" => (string) ($request->query("sort") ?: "new"),
            "sort_options" => $sortOptions,
        ]);
    }

    /**
     * CI3 product/view($id) - chi tiet san pham, 301 neu slug khong khop.
     */
    public function view(Request $request, $slug = null, $id = null): View|\Illuminate\Http\RedirectResponse
    {
        $id = (int) ($id ?? $request->query("id", 0));

        $product = Product::query()->find($id);

        if (! $product) {
            Flash::store("fail", "San pham khong ton tai");

            return redirect(url("/"));
        }

        if ((int) $product->status !== 1) {
            Flash::store("fail", "San pham nay hien khong con ban");

            return redirect(url("/"));
        }

        // Legacy /product/view/{id} hoac /product/detail/{id} -> 301 ve URL SEO
        if ($request->segment(1) === "product" && in_array($request->segment(2), ["view", "detail"], true)) {
            return redirect()->to(Urls::product($product), 301);
        }

        // Slug khong khop -> 301 ve slug chuan
        if ($slug === null && preg_match("/^(.+)-p(\\d+)$/i", (string) $request->segment(1), $m)) {
            $expected = Slug::make($product->name);
            if ((int) $m[2] === $id && $expected !== "" && $m[1] !== $expected) {
                return redirect()->to(Urls::product($product), 301);
            }
        }

        // Tang luot xem 1 lan / session (session key "session_view")
        $seen = $request->session()->get("session_view");
        $seen = is_array($seen) ? $seen : [];
        if (! isset($seen[$id])) {
            $seen[$id] = true;
            $request->session()->put("session_view", $seen);
            Product::query()->where("id", $id)->increment("view");
            $product->view = (int) $product->view + 1;
        }

        $variantStockMap = $this->variantStockMap($id);

        $productSub = Product::query()->active()
            ->where("catalog_id", $product->catalog_id)
            ->where("id", "!=", $id)
            ->limit(4)->get();

        $productView = Product::query()->active()->orderByDesc("buyed")->limit(4)->get();

        $reviewData = app(ReviewService::class)->forProduct($id, $this->ratingSessionToken($request));
        if ($reviewData["rate_count"] > 0) {
            $product->rate_count = $reviewData["rate_count"];
            $product->rate_total = $reviewData["rate_total"];
        }

        $defaultColor = "";
        $defaultSize = "";
        foreach ($variantStockMap as $row) {
            if (! empty($row["stock"])) {
                $defaultColor = (string) $row["color"];
                $defaultSize = (string) $row["size"];
                break;
            }
        }

        return $this->renderSub("site/product/view", [
            "sf_temp" => "site/product/view",
            "product" => $product,
            "catalog_product" => Catalog::query()->find($product->catalog_id),
            "image_list" => $product->imageList(),
            "product_colors" => $product->colorList(),
            "product_sizes" => $product->sizeList(),
            "variant_stock_map" => $variantStockMap,
            "productsub" => $productSub,
            "productview" => $productView,
            "product_reviews" => $reviewData["reviews"],
            "review_breakdown" => $reviewData["breakdown"],
            "user_already_rated" => $reviewData["user_review"] !== null,
            "user_review" => $reviewData["user_review"] ?: false,
            "user_review_pending" => $reviewData["pending"],
            "default_variant_color" => $defaultColor,
            "default_variant_size" => $defaultSize,
            "canonical_url" => Urls::product($product),
            "page_title" => ProductHelper::displayName($product->name),
        ]);
    }

    /** CI3 product/raty - POST JSON. */
    public function raty(Request $request, ReviewService $reviews): JsonResponse
    {
        $result = $reviews->submit(
            (int) $request->input("id"),
            (int) round((float) $request->input("score")),
            trim((string) $request->input("comment")),
            $this->ratingSessionToken($request)
        );

        return $this->ratyResponse($result);
    }

    /** CI3 product/raty_undo - POST JSON. */
    public function ratyUndo(Request $request, ReviewService $reviews): JsonResponse
    {
        return $this->ratyResponse($reviews->undo(
            (int) $request->input("id"),
            $this->ratingSessionToken($request)
        ));
    }
    // ---------------------------------------------------------------------
    // Ho tro noi bo
    // ---------------------------------------------------------------------

    /**
     * Render view con trong layout sub (co sidebar).
     *
     * VIET LAI: khong dung @yield (view()->with("content") khong map duoc vao
     * @yield). Render view con TRUOC, layout sub include qua $sfContent.
     */
    protected function renderSub(string $view, array $data = []): View
    {
        $data["sf_temp"] = $data["sf_temp"] ?? str_replace(".", "/", $view);

        return view($view, $data);
    }

    /** CI3 seo_resolve_catalog_id_by_slug(). */
    protected function resolveCatalogIdBySlug(string $slug): int
    {
        $slug = trim(rawurldecode((string) $slug));
        if ($slug === "") {
            return 0;
        }

        $exact = Catalog::query()->whereRaw("LOWER(name) = ?", [mb_strtolower($slug)])->value("id");
        if ($exact) {
            return (int) $exact;
        }

        $catalogs = Catalog::query()->select(["id", "name"])->get();
        foreach ($catalogs as $row) {
            if (Slug::make($row->name) === $slug) {
                return (int) $row->id;
            }
        }

        return 0;
    }

    /** @return array{0:int,1:string} root catalog id + title */
    protected function catalogRoot(Catalog $catalog): array
    {
        if ((int) $catalog->parent_id === 1) {
            return [(int) $catalog->id, (string) $catalog->name];
        }

        $temp = $catalog;
        $guard = 0;
        while ($temp && (int) $temp->parent_id !== 1 && (int) $temp->parent_id !== 0 && $guard < 10) {
            $temp = Catalog::query()->find($temp->parent_id);
            $guard++;
        }

        return $temp ? [(int) $temp->id, (string) $temp->name] : [(int) $catalog->id, (string) $catalog->name];
    }

    /**
     * Mở rộng id danh mục đã chọn (CI3: chosen -> +2 cấp con).
     * Không chọn category nào -> lấy chính $defaultId + con.
     *
     * @return array<int,int>
     */
    protected function resolveFinalCatalogIds(Request $request, int $defaultId): array
    {
        $chosen = $this->arrayParam($request->query("category"));
        if ($chosen !== []) {
            return CatalogTree::expandIds($chosen);
        }

        return $defaultId > 0 ? CatalogTree::selfAndDescendants($defaultId) : [];
    }

    /**
     * Áp bộ lọc danh mục/size/color/price - port _apply_product_catalog_filters()
     * va _apply_listing_attribute_filters().
     */
    protected function applyFilters(Builder $builder, array $catalogIds, $priceRange): Builder
    {
        if ($catalogIds !== []) {
            $builder->whereIn("catalog_id", $catalogIds);
        }

        $sizes = array_values(array_filter(array_map("trim", $this->arrayParam(request()->query("size"))), fn ($s) => $s !== ""));
        if ($sizes !== []) {
            $builder->where(function (Builder $q) use ($sizes) {
                foreach ($sizes as $sz) {
                    $q->orWhere("size", "LIKE", "%" . $this->escapeLike($sz) . "%");
                }
            });
        }

        $colors = array_values(array_filter(array_map("trim", $this->arrayParam(request()->query("color"))), fn ($c) => $c !== ""));
        if ($colors !== []) {
            $builder->where(function (Builder $q) use ($colors) {
                foreach ($colors as $cl) {
                    $clean = str_replace(" ", "", mb_strtolower($cl, "UTF-8"));
                    $safe = $this->escapeLike($clean);
                    $q->orWhereRaw("REPLACE(LOWER(color), ' ', '') LIKE ?", ["%" . $safe . "%"]);
                }
            });
        }

        [$min, $max] = $this->parseRange($priceRange);
        if ($min !== null) {
            $builder->where("price", ">=", $min);
            if ($max !== null) {
                $builder->where("price", "<=", $max);
            }
        }

        return $builder;
    }

    /**
     * Sắp xếp CI3: price_asc/price_desc order by (price - discount);
     * mặc định theo cột $defaultColumn.
     */
    protected function applySort(Builder $builder, Request $request, string $defaultColumn = "id", string $defaultDir = "DESC"): Builder
    {
        $sort = (string) $request->query("sort", "");

        if ($sort === "price_asc" || $sort === "price_desc") {
            return $builder->orderByRaw("(price - discount) " . ($sort === "price_asc" ? "ASC" : "DESC"));
        }

        if ($sort === "new") {
            return $builder->orderByDesc("id");
        }

        return $builder->orderBy($defaultColumn, $defaultDir);
    }

    /** Phân trang theo URI segment (CI3 uri_segment). GIỮ y hệt HTML wrapper. */
    protected function segmentPaginator(int $total, ?Catalog $catalog, $priceRange, Request $request, int $segment, int $page, ?string $baseUrl = null): string
    {
        if ($total <= self::PER_PAGE) {
            return "";
        }

        $base = $baseUrl
            ?? rtrim(Urls::category($catalog, $priceRange ?: null), "/") . "/";

        $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
            $this->sliceForPage($request, $page),
            $total,
            self::PER_PAGE,
            $page,
            ["path" => $base, "pageName" => "page"]
        );

        return $paginator->withQueryString()->links("pagination::default")->render();
    }

    /** Phân trang dạng query string (?per_page=) - dùng chung LengthAwarePaginator. */
    protected function queryPaginator(int $total, string $baseUrl, Request $request, int $segment): string
    {
        if ($total <= self::PER_PAGE) {
            return "";
        }

        $page = (int) floor($segment / self::PER_PAGE) + 1;

        $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
            $this->emptySlice(),
            $total,
            self::PER_PAGE,
            $page,
            ["path" => $baseUrl, "pageName" => "page"]
        );

        return $paginator->links("pagination::default")->render();
    }

    /**
     * Laravel Paginator cần item cho trang hiện tại; controller truyền sẵn
     * $product_list nên ta chỉ cần 1 placeholder cùng kích thước.
     */
    protected function emptySlice(): array
    {
        return [];
    }

    protected function sliceForPage(Request $request, int $page): array
    {
        $list = $request->attributes->get("_product_list_slice");

        return is_array($list) ? $list : [];
    }

    /** CI3 _load_listing_sidebar(): master_colors status=1 order sort_order. */
    protected function masterColors()
    {
        return \App\Models\MasterColor::query()
            ->where("status", 1)
            ->orderBy("sort_order")
            ->orderBy("name")
            ->get();
    }

    /**
     * CI3 product_service->get_variant_stock_map($id): map "color||size" -> variant.
     */
    protected function variantStockMap(int $productId): array
    {
        $rows = \App\Models\ProductVariant::query()
            ->where("product_id", $productId)
            ->where("status", 1)
            ->orderBy("color")
            ->orderBy("size")
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $map[(string) $row->color . "||" . (string) $row->size] = [
                "id" => (int) $row->id,
                "stock" => (int) $row->stock,
                "color" => (string) $row->color,
                "size" => (string) $row->size,
            ];
        }

        return $map;
    }

    /** CI3 _get_rating_session_token(). */
    protected function ratingSessionToken(Request $request): string
    {
        $token = $request->session()->get("rating_guest_token");
        if (empty($token)) {
            $token = bin2hex(random_bytes(16));
            $request->session()->put("rating_guest_token", $token);
        }

        return (string) $token;
    }

    /** CI3 _json_raty_response() - tra JSON kem CSRF meta. */
    protected function ratyResponse(array $result): JsonResponse
    {
        return response()->json(array_merge($result, \App\Support\Storefront\Csrf::jsonMeta()), 200, [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Query param dam bài array[] hoặc giá trị đơn -> mảng sạch.
     *
     * @return array<int,mixed>
     */
    protected function arrayParam(mixed $value): array
    {
        if ($value === null || $value === "" || $value === []) {
            return [];
        }

        return array_values((array) $value);
    }

    /** Escape ký tự LIKE trong MySQL. */
    protected function escapeLike(string $value): string
    {
        return str_replace(["\\", "%", "_"], ["\\\\", "\\%", "\\_"], $value);
    }

    /**
     * Tách range "min-max" (CI3 explode("-", $price_range)).
     *
     * @return array{0:int|null,1:int|null}
     */
    protected function parseRange(mixed $range): array
    {
        if ($range === null || $range === "") {
            return [null, null];
        }

        $parts = explode("-", (string) $range);
        if (count($parts) !== 2) {
            return [null, null];
        }

        return [(int) $parts[0], (int) $parts[1]];
    }
}
