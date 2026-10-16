@php
    // 1 the san pham trong luoi danh sach - port site/product/catalog.php
    $product_link = \App\Support\Storefront\Urls::product($value);
    $in_stock = \App\Support\Storefront\Product::isInStock($value);
    $add_url = url("gio-hang/them/" . (int) $value->id);
@endphp
<div class="jm-grid-item">
    <article class="jm-product-card{{ $in_stock ? "" : " jm-product-card--out-of-stock" }}">
        <div class="jm-product-thumb-wrapper">
            {!! \App\Support\Storefront\Product::discountBadgeHtml($value) !!}
            @if (! $in_stock)
                {!! \App\Support\Storefront\Product::outOfStockBadgeHtml() !!}
            @endif
            <a href="{{ $product_link }}" class="jm-img-link">
                <img src="{{ \App\Support\Storefront\Product::imageUrl($value->image_link) }}" alt="{{ \App\Support\Storefront\Product::displayName($value->name) }}" class="jm-product-img" loading="lazy">
            </a>

            @if ($in_stock)
            <a href="{{ $add_url }}" class="jm-catalog-add-cart">Thêm vào giỏ</a>
            @else
            <span class="jm-catalog-add-cart jm-catalog-add-cart--disabled">Hết hàng</span>
            @endif
        </div>

        <div class="jm-product-info">
            <h3 class="jm-product-name">
                <a href="{{ $product_link }}">{{ \App\Support\Storefront\Product::displayName($value->name) }}</a>
            </h3>

            <div class="jm-product-meta">
                <div class="jm-price-box">
                    @if ((int) $value->discount > 0)
                        @php $new_price = (int) $value->price - (int) $value->discount; @endphp
                        <span class="jm-price-new"><span class="jm-price-amount">{{ number_format($new_price) }}</span><span class="jm-price-currency">đ</span></span>
                        <del class="jm-price-old"><span class="jm-price-amount">{{ number_format($value->price) }}</span><span class="jm-price-currency">đ</span></del>
                    @else
                        <span class="jm-price-new"><span class="jm-price-amount">{{ number_format($value->price) }}</span><span class="jm-price-currency">đ</span></span>
                    @endif
                </div>
            </div>

            <div class="jm-product-rating">
                {!! \App\Support\Storefront\Product::catalogStarsHtml($value) !!}
                @if (! empty($value->rate_count))
                <span class="jm-rating-avg">({{ number_format(\App\Support\Storefront\Product::ratingAverage($value), 1, ".", "") }})</span>
                @endif
                @if (! empty($is_hot_list))
                <span class="jm-sales-count">({{ (int) $value->buyed }} đã bán)</span>
                @else
                <span class="jm-sales-count">({{ (int) $value->view }} đã xem)</span>
                @endif
            </div>
        </div>
    </article>
</div>
