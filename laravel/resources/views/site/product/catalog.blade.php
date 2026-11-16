@extends("layouts.storefront.sub")

@section("content")
@php
    $siteAssetUrl = \App\Support\Storefront\Shop::assetUrl("");
    $filterAction = $filter_action ?? \App\Support\Storefront\Urls::category($catalog_p->id ?? 0);
    $chosenCats = array_map("intval", $chosen_cats ?? []);
    $chosenSizes = $chosen_sizes ?? [];
    $chosenColors = $chosen_colors ?? [];
    $chosenPrice = $chosen_price ?? null;
    $titleMain = $main_catalog_title ?? ($catalog_p->name ?? "DANH SÁCH SẢN PHẨM");

    $chosenSizesArr = array_values(array_filter(array_map("trim", array_map("strval", $chosenSizes))));
    $chosenColorsArr = array_map("strval", $chosenColors);

    $currentSort = $current_sort ?? "new";
    $sortOptions = $sort_options ?? ["new" => "Mới nhất", "price_asc" => "Giá tăng dần", "price_desc" => "Giá giảm dần"];
    $isHotList = ! empty($is_hot_list);
@endphp
<link rel="stylesheet" href="{{ $siteAssetUrl }}css/catalog-luxury.css?v=30">
<div class="col-xs-12 col-sm-12 col-md-12 col-lg-12 jm-catalog-wrapper jm-catalog-lux jm-catalog-lux--sidebar-pinned">

    <div class="jm-toolbar jm-catalog-header">
        <h1 class="jm-catalog-title">{{ $titleMain }}</h1>
        <div class="jm-toolbar-actions">
            <div class="jm-product-counter">
                Hiển thị <span class="jm-catalog-count">{{ $total ?? count($product_list ?? []) }}</span> sản phẩm
            </div>
            <div class="jm-filter-slot-sort" data-jm-filter-home="sort">
            <div class="jm-sort-box jm-sort-box--toolbar">
                <label class="jm-label-sort">Xem theo</label>
                <form method="GET" action="{{ $filterAction }}">
                    @foreach ($chosenCats as $cat)
                        <input type="hidden" name="category[]" value="{{ $cat }}">
                    @endforeach
                    @foreach ($chosenSizesArr as $sz)
                        <input type="hidden" name="size[]" value="{{ $sz }}">
                    @endforeach
                    @foreach ($chosenColorsArr as $cl)
                        <input type="hidden" name="color[]" value="{{ $cl }}">
                    @endforeach
                    @if (! empty($chosenPrice))
                        <input type="hidden" name="price_range" value="{{ $chosenPrice }}">
                    @endif
                    <select class="form-control jm-select-sort" id="jmCatalogSortSelect" name="sort" onchange="this.form.submit()">
                        @if ($isHotList)
                        <option value="hot" {{ $currentSort === "hot" ? "selected" : "" }}>Bán chạy nhất</option>
                        <option value="new" {{ $currentSort === "new" ? "selected" : "" }}>Mới nhất</option>
                        @else
                        <option value="new" {{ $currentSort === "new" ? "selected" : "" }}>Mới nhất</option>
                        @endif
                        <option value="price_asc" {{ $currentSort === "price_asc" ? "selected" : "" }}>Giá tăng dần</option>
                        <option value="price_desc" {{ $currentSort === "price_desc" ? "selected" : "" }}>Giá giảm dần</option>
                    </select>
                </form>
            </div>
            </div>
        </div>
    </div>

    <div class="jm-catalog-mobile-bar" id="jmCatalogMobileBar" aria-label="Bộ lọc nhanh">
        <div class="jm-catalog-mobile-bar__inner">
            <button type="button" class="jm-mfilter-chip" data-jm-mfilter="sort" aria-expanded="false">
                <span class="jm-mfilter-chip__text">Xem theo</span>
                <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
            </button>
            <button type="button" class="jm-mfilter-chip jm-mfilter-chip--filter" data-jm-mfilter="all" aria-expanded="false">
                <i class="fa-solid fa-sliders" aria-hidden="true"></i>
                <span class="jm-mfilter-chip__text">Bộ lọc</span>
            </button>
        </div>
        <div class="jm-mfilter-drop" data-jm-mfilter-drop="sort" hidden>
            <ul class="jm-sort-menu" role="listbox" aria-label="Sắp xếp sản phẩm">
                @foreach ($sortOptions as $sortValue => $sortLabel)
                <li role="none">
                    <button type="button"
                        class="jm-sort-menu__option{{ $currentSort === $sortValue ? " is-active" : "" }}"
                        role="option"
                        data-sort-value="{{ $sortValue }}"
                        aria-selected="{{ $currentSort === $sortValue ? "true" : "false" }}">
                        {{ $sortLabel }}
                    </button>
                </li>
                @endforeach
            </ul>
        </div>
    </div>
    <div class="jm-catalog-mfilter-backdrop" id="jmCatalogMfilterBackdrop" hidden aria-hidden="true"></div>

    <div class="row jm-main-layout-flex">
        <aside class="col-xs-12 col-sm-4 col-md-3 jm-sidebar-filter">
            <div class="jm-catalog-sidebar-card">
            <h2 class="jm-filter-main-title">TÌM KIẾM</h2>

            <form method="GET" action="{{ $filterAction }}" id="filter-form">
                @if (request()->query("sort"))
                    <input type="hidden" name="sort" value="{{ request()->query("sort") }}">
                @endif

                <div class="jm-filter-group">
                    <div class="jm-filter-heading jm-filter-heading--static">
                        <span>Dòng sản phẩm</span>
                    </div>
                    <div id="filter-category" class="collapse in">
                        <div class="jm-checkbox-list">
                            @foreach (($catalog_list ?? collect()) as $parent)
                                @php $parentChecked = in_array((int) $parent->id, $chosenCats, true); @endphp
                                <label class="jm-checkbox-item jm-checkbox-item--parent">
                                    <input type="checkbox" name="category[]" value="{{ $parent->id }}" {{ $parentChecked ? "checked" : "" }}>
                                    <span class="jm-checkmark"></span> {{ $parent->name }}
                                </label>

                                @if (! empty($parent->subs))
                                    <div class="jm-sub-category-list">
                                        @foreach ($parent->subs as $sub)
                                            @php $subChecked = in_array((int) $sub->id, $chosenCats, true); @endphp
                                            <label class="jm-checkbox-item jm-checkbox-item--sub">
                                                <input type="checkbox" name="category[]" value="{{ $sub->id }}" {{ $subChecked ? "checked" : "" }}>
                                                <span class="jm-checkmark"></span> {{ $sub->name }}
                                            </label>
                                        @endforeach
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="jm-filter-slot" data-jm-filter-home="size"></div>
                <div class="jm-filter-group" id="jm-filter-group-size">
                    <div class="jm-filter-heading jm-filter-heading--static">
                        <span>Kích thước</span>
                    </div>
                    <div id="filter-size" class="collapse in">
                        <div class="jm-size-options">
                            @php
                                $dynamicSizes = [];
                                foreach (($product_list ?? []) as $pItem) {
                                    if (! empty($pItem->size)) {
                                        foreach (explode(",", $pItem->size) as $szVal) {
                                            $szVal = trim($szVal);
                                            if ($szVal !== "" && ! in_array($szVal, $dynamicSizes, true)) {
                                                $dynamicSizes[] = $szVal;
                                            }
                                        }
                                    }
                                }
                                if (empty($dynamicSizes)) {
                                    $dynamicSizes = ["S", "M", "L", "XL", "XXL"];
                                }
                                if (! empty($chosenSizesArr)) {
                                    $dynamicSizes = array_values(array_unique(array_merge($dynamicSizes, $chosenSizesArr)));
                                }
                                sort($dynamicSizes);
                            @endphp
                            @foreach ($dynamicSizes as $sizeItem)
                                @php
                                    $sizeClean = trim((string) $sizeItem);
                                    $isChecked = in_array($sizeClean, $chosenSizesArr, true);
                                @endphp
                                <label class="jm-size-label {{ $isChecked ? "active" : "" }}" style="cursor:pointer; margin-right:5px; display: inline-block; position: relative;">
                                    <input type="checkbox" name="size[]" value="{{ $sizeClean }}" {{ $isChecked ? "checked" : "" }} style="position: absolute; opacity: 0; width: 100%; height: 100%; margin: 0; cursor: pointer; left: 0; top: 0;">
                                    <span class="jm-size-box">{{ $sizeClean }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="jm-filter-slot" data-jm-filter-home="color"></div>
                <div class="jm-filter-group" id="jm-filter-group-color">
                    <div class="jm-filter-heading jm-filter-heading--static">
                        <span>Màu sắc</span>
                    </div>
                    <div id="filter-color" class="collapse in">
                        <div class="jm-color-scroll-container">
                            <div class="jm-color-options">
                                @php
                                    $availableColors = \App\Support\Storefront\ColorPalette::fromMaster();
                                    foreach ($chosenColorsArr as $chosenColorName) {
                                        if ($chosenColorName !== "" && ! isset($availableColors[$chosenColorName])) {
                                            $availableColors[$chosenColorName] = \App\Support\Storefront\ColorPalette::hexFor($chosenColorName);
                                        }
                                    }
                                @endphp
                                @foreach ($availableColors as $colorName => $hexCode)
                                    @php
                                        $isChecked = in_array($colorName, $chosenColorsArr, true);
                                        $borderStyle = ($hexCode == "#ffffff" || $hexCode == "#f5f5dc") ? "border: 1px solid #ddd;" : "";
                                        $tickColor = ($hexCode == "#ffffff" || $hexCode == "#f5f5dc") ? "#333333" : "#ffffff";
                                    @endphp
                                    <label class="jm-color-label {{ $isChecked ? "active" : "" }}" title="{{ $colorName }}" style="display: inline-block; cursor: pointer; position: relative; user-select: none; margin: 0;">
                                        <input type="checkbox" name="color[]" value="{{ $colorName }}" {{ $isChecked ? "checked" : "" }} style="position: absolute; opacity: 0; width: 100%; height: 100%; margin: 0; cursor: pointer; left: 0; top: 0;">
                                        <span class="jm-color-circle" style="display: flex; align-items: center; justify-content: center; width: 20px; height: 20px; border-radius: 50%; background-color: {{ $hexCode }}; {{ $borderStyle }} transition: all 0.2s ease; box-shadow: inset 0 1px 2px rgba(0,0,0,0.15);">
                                            @if ($isChecked)
                                                <span class="jm-tick-icon" style="color: {{ $tickColor }}; font-size: 14px; font-weight: bold; line-height: 1;">✓</span>
                                            @endif
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

<div class="jm-filter-group">
    <div class="jm-filter-heading jm-filter-heading--static">
        <span>Giá</span>
    </div>
    <div id="filter-price" class="collapse in">
        <p class="jm-price-filter-hint">Chọn khoảng giá</p>
        <div class="jm-radio-list">
            @php
                $priceOptions = [
                    "" => "Tất cả mức giá",
                    "0-200000" => "Dưới 200.000đ",
                    "200000-500000" => "200.000đ - 500.000đ",
                    "500000-1000000" => "500.000đ - 1.000.000đ",
                    "1000000-999999999" => "Trên 1.000.000đ",
                ];
            @endphp
            @foreach ($priceOptions as $priceValue => $priceLabel)
            <label class="jm-radio-item">
                <input type="radio" name="price_range" value="{{ $priceValue }}" {{ empty($chosenPrice) && $priceValue === "" ? "checked" : ($chosenPrice == $priceValue ? "checked" : "") }}>
                <span>{{ $priceLabel }}</span>
            </label>
            @endforeach
        </div>
    </div>
</div>
            </form>
            </div>
        </aside>

        <main class="col-xs-12 col-sm-8 col-md-9 jm-product-content">
            <div id="jm-catalog-results" class="jm-catalog-results">
            <div class="jm-product-grid">
                @php
                    $hasProducts = (isset($total) && $total > 0) || (! empty($product_list) && count($product_list) > 0);
                @endphp

                @if ($hasProducts)
                    @foreach ($product_list as $value)
                        @include("site.partials.product-card")
                    @endforeach
                @else
                    @php
                        $emptyContext = "trong danh muc nay";
                        if (! empty($empty_catalog_message)) {
                            $emptyContext = $empty_catalog_message;
                        } elseif (! empty($is_search_page)) {
                            $searchKw = isset($keyword) ? trim((string) $keyword) : "";
                            $emptyContext = $searchKw !== ""
                                ? "voi tu khoa " . $searchKw
                                : "voi tu khoa tim kiem cua ban";
                        } elseif (! empty($empty_catalog_label)) {
                            $emptyContext = "trong muc " . $empty_catalog_label;
                        }
                    @endphp
                    <div class="jm-empty-catalog">
                        <p>Không có sản phẩm nào phù hợp {{ $emptyContext }}.</p>
                    </div>
                @endif
            </div>

            @if (! empty($pagination))
            <nav class="jm-catalog-pagination" aria-label="Phân trang sản phẩm">
                {!! $pagination !!}
            </nav>
            @endif
            </div>
        </main>

    </div>
</div>

<script src="{{ $siteAssetUrl }}js/catalog-luxury.js?v=12"></script>
@endsection
