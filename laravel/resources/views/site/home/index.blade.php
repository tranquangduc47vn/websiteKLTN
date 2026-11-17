@extends("layouts.storefront.main")

@section("content")
@php
    $siteAssetUrl = \App\Support\Storefront\Shop::assetUrl("");
@endphp
<link rel="stylesheet" href="{{ $siteAssetUrl }}css/home-bestsellers.css?v=11">

<div class="row home-collection-banners" style="margin-top: 20px; margin-bottom: 40px;">
    @if (!empty($list_banner) && count($list_banner) > 0)
        @php
            $count = 0;
            $total = count($list_banner);
        @endphp
        @foreach ($list_banner as $bn)
            @if ($count % 2 == 0)
                @php
                    $row_class = ($count >= $total - 2) ? "row banner-flex-row no-bottom-margin" : "row banner-flex-row";
                @endphp
                <div class="{{ $row_class }}">
            @endif
            @php $count++; @endphp
            <div class="col-xs-12 col-sm-6 banner-item">
                <a href="{{ url($bn->link) }}">
                    <img src="{{ url("upload/slider/" . $bn->image_link) }}" alt="{{ $bn->name }}">
                </a>
            </div>
            @if ($count % 2 == 0 || $count == $total)
                </div>
            @endif
        @endforeach
    @else
        <p class="text-center" style="width:100%; padding: 20px; color: red; font-weight: bold;">Hệ thống chưa tìm thấy dữ liệu trong bảng banner danh mục. Hãy kiểm tra lại kết nối Database!</p>
    @endif
</div>

<section class="jm-bestsellers-lux" id="phan-ban-chay" aria-labelledby="jm-bestsellers-title">
    <header class="jm-bestsellers-lux__head">
        <p class="jm-bestsellers-lux__eyebrow">{{ \App\Support\Storefront\Shop::name() }}</p>
        <h2 class="jm-bestsellers-lux__title" id="jm-bestsellers-title">Sản phẩm bán chạy</h2>
        <div class="jm-bestsellers-lux__rule" aria-hidden="true"></div>
        <p class="jm-bestsellers-lux__sub">Những thiết kế được khách yêu thích nhất — cập nhật theo lượt bán thực tế.</p>
        <a href="{{ url("ban-chay") }}" class="jm-bestsellers-lux__cta">
            Xem tất cả <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
        </a>
    </header>

    <ul class="jm-bestsellers-lux__grid">
        @forelse ($hot_product as $value)
            @php
                $product_link = \App\Support\Storefront\Urls::product($value);
                $name = \App\Support\Storefront\Product::displayName($value->name);
                $img = \App\Support\Storefront\Product::imageUrl($value->image_link);
                $in_stock = \App\Support\Storefront\Product::isInStock($value);
                $price_new = (int) $value->price - (int) $value->discount;
            @endphp
            <li class="jm-bs-card{{ $in_stock ? "" : " jm-bs-card--out-of-stock" }}">
                <div class="jm-bs-card__media">
                    @if (\App\Support\Storefront\Product::showDiscountBadge($value))
                        {!! \App\Support\Storefront\Product::discountBadgeHtml($value, "jm-bs-card__badge-sale") !!}
                    @endif
                    @if (! $in_stock)
                        {!! \App\Support\Storefront\Product::outOfStockBadgeHtml("jm-bs-card__badge-oos") !!}
                    @endif
                    <a href="{{ $product_link }}" class="jm-bs-card__img-link">
                        <img src="{{ $img }}" alt="{{ $name }}" loading="lazy">
                    </a>
                    @if ($in_stock)
                    @include("site.partials.add-to-cart-form", ["productId" => (int) $value->id, "buttonClass" => "jm-bs-card__cart-btn", "label" => "Thêm giỏ hàng"])
                    @else
                    <span class="jm-bs-card__cart-btn jm-bs-card__cart-btn--disabled">Hết hàng</span>
                    @endif
                </div>
                <div class="jm-bs-card__body">
                    <h3 class="jm-bs-card__name">
                        <a href="{{ $product_link }}">{{ $name }}</a>
                    </h3>
                    <div class="jm-bs-card__prices">
                        @if ((int) $value->discount > 0)
                            <span class="jm-bs-card__price">{{ number_format($price_new) }}đ</span>
                            <span class="jm-bs-card__price-old">{{ number_format($value->price) }}đ</span>
                        @else
                            <span class="jm-bs-card__price">{{ number_format($value->price) }}đ</span>
                        @endif
                    </div>
                    <span class="jm-bs-card__sold">
                        <i class="fa-solid fa-fire-flame-curved" aria-hidden="true"></i>
                        {{ number_format((int) $value->buyed) }} đã bán
                    </span>
                </div>
            </li>
        @empty
            <li class="jm-bestsellers-lux__empty">Chưa có sản phẩm bán chạy.</li>
        @endforelse
    </ul>
</section>

<script src="{{ $siteAssetUrl }}js/custom.js"></script>
@endsection
