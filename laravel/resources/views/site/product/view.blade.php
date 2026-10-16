@extends("layouts.storefront.sub")

@section("content")
@php
    $siteAssetUrl = \App\Support\Storefront\Shop::assetUrl("");
    $imgProduct = \App\Support\Storefront\Product::imageUrl($product->image_link);
    $pdpPriceFmt = fn ($amount) => number_format((float) $amount, 0, ",", ".") . " ₫";
    $priceNew = (int) $product->price - (int) $product->discount;

    $variantStockMap = $variant_stock_map ?? [];
    $defaultColor = (string) ($default_variant_color ?? "");
    $defaultSize = (string) ($default_variant_size ?? "");

    $hasAnyStock = false;
    foreach ($variantStockMap as $row) {
        if (! empty($row["stock"])) { $hasAnyStock = true; break; }
    }
    if (empty($variantStockMap)) {
        $hasAnyStock = (int) ($product->quantity ?? 0) > 0;
    }

    $ratyTb = ((int) $product->rate_count > 0) ? ((int) $product->rate_total / (int) $product->rate_count) : 0;
    $myReviewStars = (! empty($user_review) && isset($user_review->stars)) ? (int) $user_review->stars : 0;
    $myReviewComment = (! empty($user_review) && isset($user_review->comment)) ? (string) $user_review->comment : "";

    $breakdown = $review_breakdown ?? [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
    $rateCount = (int) $product->rate_count;
@endphp
<div class="col-xs-12 col-sm-12 col-md-12 col-lg-12 clearpaddingr jm-pdp-lux">
    <div class="col-xs-12 col-sm-12 col-md-12 col-lg-12 clearpadding">

        <link rel="stylesheet" href="{{ $siteAssetUrl }}css/style-detail.css?v=17" type="text/css">
        <link rel="stylesheet" href="{{ $siteAssetUrl }}css/stock-notice-modal.css?v=1" type="text/css">
        <script src="{{ $siteAssetUrl }}js/product-detail.js?v=3" type="text/javascript"></script>

        <script type="text/javascript">
        $(document).ready(function() {
            var alreadyRated = {{ ! empty($user_already_rated) ? "true" : "false" }};
            var reviewPending = {{ ! empty($user_review_pending) ? "true" : "false" }};
            var myScore = {{ $myReviewStars }};
            var selectedScore = myScore > 0 ? myScore : 0;

            $('.raty_big_summary').raty({ score: function() { return $(this).attr('data-score'); }, half: true, readOnly: true });
            $('.raty_detailt').raty({ score: function() { return $(this).attr('data-score'); }, half: true, readOnly: true });

            function showReviewThankYou(score, isUpdate) {
                var title = 'Cảm ơn quý khách!';
                var text = isUpdate
                    ? 'Đánh giá của quý khách đã được cập nhật thành ' + score + ' sao. Ý kiến quý khách giúp chúng tôi cải thiện chất lượng sản phẩm và dịch vụ.'
                    : 'Cảm ơn quý khách đã dành thời gian đánh giá sản phẩm. Ý kiến của quý khách rất quý giá và giúp chúng tôi phục vụ tốt hơn mỗi ngày.';
                $('#jmReviewThankTitle').text(title);
                $('#jmReviewThankText').text(text);
                $('#jmReviewThankScore').text(score + '/5 sao');
                if ($('#jmReviewThankStars').data('raty')) { $('#jmReviewThankStars').raty('score', score); }
                else { $('#jmReviewThankStars').raty({ score: score, readOnly: true, half: false }); }
                $('#jmReviewThankModal').modal('show');
            }

            $('#jmReviewThankModal').on('hidden.bs.modal', function() { window.location.reload(); });

            function ratyPostData(extra) {
                var data = extra || {};
                if (window.CI_CSRF) { data[window.CI_CSRF.name] = window.CI_CSRF.hash; }
                return data;
            }
            function applyRatyCsrf(data) {
                if (typeof window.applyCsrfResponse === 'function') { window.applyCsrfResponse(data); }
            }

            $('.raty_input').raty({
                score: myScore > 0 ? myScore : undefined,
                half: false,
                readOnly: false,
                click: function(score) { selectedScore = score; }
            });

            $('#jmReviewSubmit').on('click', function(e) {
                e.preventDefault();
                var comment = $.trim($('#jmReviewComment').val());
                if (selectedScore < 1 || selectedScore > 5) { alert('Vui lòng chọn số sao đánh giá.'); return; }
                if (comment === '') { alert('Vui lòng nhập nội dung đánh giá.'); $('#jmReviewComment').focus(); return; }
                $.ajax({
                    url: '{{ url("product/raty") }}',
                    type: 'POST',
                    data: ratyPostData({ 'id': '{{ $product->id }}', 'score': selectedScore, 'comment': comment }),
                    dataType: 'json',
                    success: function(data) {
                        applyRatyCsrf(data);
                        if (data.complete) { showReviewThankYou(selectedScore, !!data.updated); return; }
                        alert(data.msg);
                    },
                    error: function() { alert('Không gửi được đánh giá, vui lòng thử lại.'); }
                });
            });

            $('#btn-raty-undo').on('click', function(e) {
                e.preventDefault();
                if (!confirm('Bạn muốn hoàn tác đánh giá này?')) { return; }
                $.ajax({
                    url: '{{ url("product/raty-undo") }}',
                    type: 'POST',
                    data: ratyPostData({'id': '{{ $product->id }}'}),
                    dataType: 'json',
                    success: function(data) {
                        applyRatyCsrf(data);
                        if (data.complete) { window.location.reload(); return; }
                        alert(data.msg);
                    }
                });
            });
        });
        </script>

        <div class="panel panel-info jm-pdp-panel" style="margin-bottom: 15px; border: none; box-shadow: none;">
            <div class="panel-body jm-pdp-panel-body" style="padding: 0;">

                <div class="col-xs-12 col-sm-6 col-md-6 col-lg-5 jm-pdp-gallery-col">
                    <div class="jm-gallery-container">
                        <div class="jm-thumb-vertical-box">
                            <ul id="thumblist" class="jm-thumb-vertical">
                                <li>
                                    <a class="zoomThumbActive" href="javascript:void(0);"
                                       data-small="{{ $imgProduct }}"
                                       data-large="{{ $imgProduct }}">
                                        <img src="{{ $imgProduct }}" alt="thumbnail">
                                    </a>
                                </li>
                                @if (is_array($image_list))
                                    @foreach ($image_list as $imgItem)
                                        @php $thumbSrc = \App\Support\Storefront\Product::imageUrl($imgItem); @endphp
                                        <li>
                                            <a href="javascript:void(0);"
                                               data-small="{{ $thumbSrc }}"
                                               data-large="{{ $thumbSrc }}">
                                                <img src="{{ $thumbSrc }}" alt="thumbnail-gallery">
                                            </a>
                                        </li>
                                    @endforeach
                                @endif
                            </ul>
                        </div>

                        <div class="jm-main-image-box">
                            <div class="jm-pdp-main-stage" id="jm-pdp-main-stage">
                                <img src="{{ $imgProduct }}" alt="{{ \App\Support\Storefront\Product::displayName($product->name) }}" class="jm-pdp-main-img" id="jm-pdp-main-img" width="340" height="440">
                            </div>
                        </div>
                        <div style="clear: both;"></div>
                    </div>
                </div>

                <div class="col-xs-12 col-sm-6 col-md-6 col-lg-7 jm-pdp-info-col">
                    <div class="jm-pdp-info-sticky">
                    <h1 class="style2-product-title">{{ \App\Support\Storefront\Product::displayName($product->name) }}</h1>
                    <p class="style2-sku">Mã sản phẩm: <strong>{{ $product->id }}</strong></p>

                    <div class="style2-price-block">
                        @if ((int) $product->discount > 0)
                            @if (\App\Support\Storefront\Product::showDiscountBadge($product))
                                <p class="style2-price-badge-line">{!! \App\Support\Storefront\Product::discountBadgeHtml($product, "jm-badge-discount jm-pdp-sale-badge") !!}</p>
                            @endif
                            <p class="style2-price-old-line"><del>{{ $pdpPriceFmt($product->price) }}</del></p>
                            <p class="style2-price-current">{{ $pdpPriceFmt($priceNew) }}</p>
                        @else
                            <p class="style2-price-current">{{ $pdpPriceFmt($product->price) }}</p>
                        @endif
                    </div>

                    <div class="style2-product-desc">{!! \App\Support\HtmlSanitizer::clean($product->content) !!}</div>
                    <hr class="style2-divider">
                    <div class="style2-meta-box">
                        <p class="style2-meta-item"><strong>Size hiện có:</strong> {{ ! empty($product->size) ? $product->size : "Freesize" }}</p>
                        <p class="style2-meta-item"><strong>Số lượt xem:</strong> {{ number_format($product->view) }}</p>
                        <p class="style2-meta-item style2-rating">
                            <strong>Đánh giá:</strong>
                            <span class="raty_detailt" id="{{ $product->id }}" data-score="{{ round($ratyTb, 1) }}" style="display:inline-block; vertical-align: middle; margin-right: 5px;"></span>
                            ({{ round($ratyTb, 1) }}/5) | <span class="rate_count">{{ $product->rate_count }}</span> đánh giá
                            @if (! empty($user_already_rated) && $myReviewStars > 0)
                                <span class="jm-my-review-badge">Bạn: {{ $myReviewStars }} sao</span>
                            @endif
                        </p>
                    </div>

                    <form action="{{ url("gio-hang/them/" . (int) $product->id) }}" method="post" id="form-add-to-cart">
                        @csrf

                        <div class="style2-attr-group">
                            <label class="style2-attr-title">Màu sắc:</label>
                            <ul class="style2-attr-list-rect" id="color-select">
                                @php
                                    $colorOptions = $product_colors ?? [];
                                    $firstColor = $defaultColor !== "" ? $defaultColor : "";
                                    if ($firstColor === "" && ! empty($colorOptions)) { $firstColor = trim($colorOptions[0]); }
                                @endphp
                                @forelse ($colorOptions as $colorItem)
                                    @php
                                        $colorItem = trim($colorItem);
                                        $bgHex = \App\Support\Storefront\ColorPalette::hexFor($colorItem);
                                    @endphp
                                    <li class="style2-rect-box {{ $colorItem === $firstColor ? "active" : "" }}"
                                        data-value="{{ $colorItem }}"
                                        style="background-color: {{ $bgHex }};"
                                        title="{{ $colorItem }}">
                                        {{ $colorItem }}
                                    </li>
                                @empty
                                    @php $firstColor = "Mặc định"; @endphp
                                    <li class="style2-rect-box active" data-value="Mặc định" style="background-color: #ffffff;">Mặc định</li>
                                @endforelse
                            </ul>
                            <input type="hidden" name="color" id="input-color" value="{{ $firstColor }}">
                        </div>

                        <div class="style2-attr-group">
                            <label class="style2-attr-title">Kích cỡ:</label>
                            <ul class="style2-attr-list-square" id="size-select">
                                @php
                                    $sizeOptions = $product_sizes ?? [];
                                    $firstSize = $defaultSize !== "" ? $defaultSize : "";
                                    if ($firstSize === "" && ! empty($sizeOptions)) { $firstSize = trim($sizeOptions[0]); }
                                @endphp
                                @forelse ($sizeOptions as $sizeItem)
                                    @php $sizeItem = trim($sizeItem); @endphp
                                    <li class="style2-square-box {{ $sizeItem === $firstSize ? "active" : "" }}" data-value="{{ $sizeItem }}">{{ $sizeItem }}</li>
                                @empty
                                    @php $firstSize = "Freesize"; @endphp
                                    <li class="style2-square-box active" data-value="Freesize">Freesize</li>
                                @endforelse
                            </ul>
                            <input type="hidden" name="size" id="input-size" value="{{ $firstSize }}">
                        </div>

                        @if ($hasAnyStock)
                            <div class="style2-attr-group" style="margin-bottom: 35px !important;">
                                <label class="style2-attr-title">Số lượng:</label>
                                <div class="style2-qty-wrapper">
                                    <button type="button" class="style2-qty-btn" id="btn-sub">-</button>
                                    <input type="text" name="qty" id="input-qty" class="style2-qty-input" value="1" readonly>
                                    <button type="button" class="style2-qty-btn" id="btn-sum">+</button>
                                </div>
                            </div>

                            <input type="hidden" name="id" value="{{ $product->id }}">
                            <input type="hidden" name="variant_id" id="input-variant-id" value="">

                            <div class="style2-action-buttons">
                                <button type="submit" class="style2-btn-addcart" id="btn-add-to-cart">
                                    <span class="glyphicon glyphicon-shopping-cart" style="margin-right: 8px;"></span> THÊM VÀO GIỎ HÀNG
                                </button>
                            </div>
                        @else
                            <div class="style2-action-buttons" style="margin-top: 30px;">
                                <button type="button" class="style2-btn-addcart" disabled style="background: #ccc !important; color: #666 !important; cursor: not-allowed !important; max-width: 320px !important;">
                                    ❌ TẠM HẾT HÀNG
                                </button>
                            </div>
                        @endif
                    </form>

                    <script type="text/javascript">
                        $(document).ready(function() {
                            var variantStocks = {!! json_encode($variantStockMap, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) !!};
                            var legacyProductStock = {!! (int) (empty($variantStockMap) ? ($product->quantity ?? 0) : 0) !!};
                            var productName = {!! json_encode($product->name, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) !!};
                            var userInitiated = false;

                            if (window.QD && window.QD.hideStockNoticeModal) { window.QD.hideStockNoticeModal(); }

                            function variantKey(color, size) { return String(color).trim() + '||' + String(size).trim(); }

                            function resolveVariant(color, size) {
                                var key = variantKey(color, size);
                                if (variantStocks[key]) { return variantStocks[key]; }
                                if (legacyProductStock > 0) { return { id: 0, stock: legacyProductStock, color: color, size: size }; }
                                return { id: 0, stock: 0, color: color, size: size };
                            }

                            function hasVariantMap() { return Object.keys(variantStocks).length > 0; }

                            function isSizeAvailable(color, size) {
                                if (!hasVariantMap()) { return legacyProductStock > 0; }
                                return (parseInt(resolveVariant(color, size).stock, 10) || 0) > 0;
                            }

                            function updateSizeAvailability(color) {
                                $('#size-select .style2-square-box').each(function() {
                                    var size = $(this).attr('data-value');
                                    $(this).toggleClass('is-unavailable', !isSizeAvailable(color, size));
                                });
                            }

                            function selectFirstAvailableSize() {
                                var $sizes = $('#size-select .style2-square-box');
                                var $active = $sizes.filter('.active');
                                if ($active.length && !$active.hasClass('is-unavailable')) { return; }
                                var $first = $sizes.not('.is-unavailable').first();
                                if (!$first.length) { return; }
                                $sizes.removeClass('active');
                                $first.addClass('active');
                                $('#input-size').val($first.attr('data-value'));
                            }

                            function showStockNotice(color, size, stock) {
                                if (!userInitiated) { return; }
                                if (window.QD && window.QD.showStockNotice) {
                                    window.QD.showStockNotice({ product_name: productName, color: color, size: size, stock: stock });
                                }
                            }

                            var qtyInput = $('#input-qty');
                            var addBtn = $('#btn-add-to-cart');
                            var maxStock = 0;

                            function refreshVariantStock() {
                                var color = $('#input-color').val();
                                updateSizeAvailability(color);
                                selectFirstAvailableSize();

                                var size = $('#input-size').val();
                                var variant = resolveVariant(color, size);
                                maxStock = parseInt(variant.stock, 10) || 0;
                                $('#input-variant-id').val(variant.id || '');

                                var currentVal = parseInt(qtyInput.val(), 10);
                                if (isNaN(currentVal) || currentVal < 1) { currentVal = 1; }
                                if (maxStock > 0 && currentVal > maxStock) { qtyInput.val(maxStock); }
                                else if (maxStock <= 0) { qtyInput.val(1); }

                                if (addBtn.length) { addBtn.prop('disabled', maxStock <= 0); }
                            }

                            $('#color-select .style2-rect-box').click(function() {
                                $('#color-select .style2-rect-box').removeClass('active');
                                $(this).addClass('active');
                                $('#input-color').val($(this).attr('data-value'));
                                refreshVariantStock();
                            });

                            $('#size-select .style2-square-box').click(function() {
                                if ($(this).hasClass('is-unavailable')) { return false; }
                                $('#size-select .style2-square-box').removeClass('active');
                                $(this).addClass('active');
                                $('#input-size').val($(this).attr('data-value'));
                                refreshVariantStock();
                            });

                            $('#btn-sum').click(function() {
                                var color = $('#input-color').val();
                                var size = $('#input-size').val();
                                var currentVal = parseInt(qtyInput.val(), 10);
                                if (!isNaN(currentVal) && currentVal < maxStock) { qtyInput.val(currentVal + 1); }
                                else { showStockNotice(color, size, maxStock); }
                            });
                            $('#btn-sub').click(function() {
                                var currentVal = parseInt(qtyInput.val(), 10);
                                if (!isNaN(currentVal) && currentVal > 1) { qtyInput.val(currentVal - 1); }
                            });

                            $('#form-add-to-cart').on('submit', function(e) {
                                userInitiated = true;
                                var color = $('#input-color').val();
                                var size = $('#input-size').val();
                                var qty = parseInt(qtyInput.val(), 10) || 0;
                                if (maxStock <= 0 || qty > maxStock) { e.preventDefault(); showStockNotice(color, size, maxStock); }
                            });
                            $('#form-add-to-cart').on('pointerdown mousedown touchstart', 'button, .style2-rect-box, .style2-square-box', function() { userInitiated = true; });

                            refreshVariantStock();
                        });
                    </script>
                    </div><!-- .jm-pdp-info-sticky -->
                </div>

                <div class="col-xs-12 col-sm-12 col-md-12 col-lg-12 text-center style2-services-container">
                    <div class="col-xs-4 col-sm-4 col-md-4 col-lg-4">
                        <img src="{{ url("upload/icon/services.png") }}" alt="service-icon" class="style2-service-icon">
                        <p class="style2-service-text">Phục vụ chu đáo</p>
                    </div>
                    <div class="col-xs-4 col-sm-4 col-md-4 col-lg-4">
                        <img src="{{ url("upload/icon/ship.png") }}" alt="ship-icon" class="style2-service-icon">
                        <p class="style2-service-text">Trao hàng đúng hẹn</p>
                    </div>
                    <div class="col-xs-4 col-sm-4 col-md-4 col-lg-4">
                        <img src="{{ url("upload/icon/services.png") }}" alt="return-icon" class="style2-service-icon">
                        <p class="style2-service-text">Đổi hàng trong 24h</p>
                    </div>
                </div>

            </div>
        </div>

        <div class="panel panel-info jm-review-panel">
            <div class="panel-heading"><h3 class="panel-title">Đánh giá sản phẩm</h3></div>
            <div class="panel-body">
                <p class="jm-review-count-title">{{ $product->rate_count }} đánh giá</p>
                <div class="jm-review-dashboard">
                    <div class="jm-review-score-box">
                        <span class="jm-big-score">{{ round($ratyTb, 1) }}</span><span class="jm-max-score">/ 5</span>
                        <div class="raty_big_summary" data-score="{{ round($ratyTb, 1) }}" style="margin-top: 8px;"></div>
                    </div>
                    <div class="jm-review-progress-box">
                        @for ($star = 5; $star >= 1; $star--)
                            @php
                                $cnt = (int) ($breakdown[$star] ?? 0);
                                $pct = $rateCount > 0 ? ($cnt / $rateCount) * 100 : 0;
                            @endphp
                            <div class="jm-progress-row">
                                <span class="jm-star-label">{{ $star }} sao</span>
                                <div class="jm-progress-bar-bg"><div class="jm-progress-bar-fill" style="width: {{ $pct }}%;"></div></div>
                                <span class="jm-star-count">({{ $cnt }})</span>
                            </div>
                        @endfor
                    </div>
                </div>

                <div class="jm-review-form">
                    <h4 class="jm-review-form-title">{{ ! empty($user_already_rated) ? "Cập nhật đánh giá của bạn" : "Viết đánh giá" }}</h4>
                    <div class="jm-review-form-stars">
                        <span class="jm-review-form-label">Chọn số sao:</span>
                        <span class="raty_input"></span>
                    </div>
                    <label class="jm-review-form-label" for="jmReviewComment">Nội dung đánh giá</label>
                    <textarea id="jmReviewComment" class="jm-review-form-textarea" rows="4" maxlength="2000" placeholder="Chia sẻ trải nghiệm về sản phẩm (bắt buộc)...">{{ $myReviewComment }}</textarea>
                    <div class="jm-review-form-actions">
                        <button type="button" class="btn btn-dark btn-sm" id="jmReviewSubmit">{{ ! empty($user_already_rated) ? "Cập nhật đánh giá" : "Gửi đánh giá" }}</button>
                        @if (! empty($user_already_rated))
                            <button type="button" class="jm-review-undo-btn" id="btn-raty-undo">Hoàn tác đánh giá</button>
                        @endif
                    </div>
                </div>

                @if (! empty($product_reviews) && count($product_reviews) > 0)
                <div class="jm-review-history">
                    <h4 class="jm-review-history-title">Lịch sử đánh giá</h4>
                    <ul class="jm-review-history-list">
                        @foreach ($product_reviews as $review)
                            @php $isMine = (! empty($user_review) && (int) $user_review->id === (int) $review->id); @endphp
                            <li class="jm-review-history-item{{ $isMine ? " jm-review-history-item--mine" : "" }}">
                                <div class="jm-review-history-head">
                                    <strong class="jm-review-user">{{ $review->user_name }}{{ $isMine ? " (Bạn)" : "" }}</strong>
                                    <span class="jm-review-stars-inline raty_readonly" data-score="{{ (int) $review->stars }}"></span>
                                    <span class="jm-review-stars-text">{{ (int) $review->stars }}/5 sao</span>
                                    <span class="jm-review-date">{{ date("d/m/Y H:i", (int) $review->created) }}</span>
                                </div>
                                @if (! empty($review->comment))
                                <p class="jm-review-comment">{!! nl2br(e($review->comment)) !!}</p>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
                <script type="text/javascript">
                $(document).ready(function() {
                    $('.raty_readonly').raty({
                        score: function() { return $(this).attr('data-score'); },
                        readOnly: true,
                        half: false
                    });
                });
                </script>
                @else
                <p class="jm-review-empty">Chưa có lịch sử đánh giá.</p>
                @endif
            </div>
        </div>

        <div class="panel panel-info jm-pdp-related">
            <div class="panel-heading jm-pdp-related__head">
                <h3 class="panel-title jm-pdp-related__title">Sản phẩm liên quan</h3>
            </div>
            <div class="panel-body jm-pdp-related__body">
                <div class="product-slider jm-pdp-related-slider">
                    <div class="product-track">
                        @for ($i = 1; $i <= 2; $i++)
                            @foreach ($productsub as $value)
                                @php $relName = \App\Support\Storefront\Product::displayName($value->name); @endphp
                                <div class="item-box jm-pdp-related-item">
                                    <article class="product_item jm-pdp-related-card">
                                        <div class="product-image jm-pdp-related-card__media">
                                            {!! \App\Support\Storefront\Product::discountBadgeHtml($value, "jm-badge-discount jm-pdp-related-badge") !!}
                                            <a href="{{ \App\Support\Storefront\Urls::product($value) }}">
                                                <img src="{{ \App\Support\Storefront\Product::imageUrl($value->image_link) }}" alt="{{ $relName }}">
                                            </a>
                                        </div>
                                        <div class="jm-pdp-related-card__body">
                                            <h4 class="product_name jm-pdp-related-card__name">
                                                <a href="{{ \App\Support\Storefront\Urls::product($value) }}">{{ $relName }}</a>
                                            </h4>
                                            @if ((int) $value->discount > 0)
                                                @php $relNew = (int) $value->price - (int) $value->discount; @endphp
                                                <p class="jm-pdp-related-card__price">
                                                    <span class="price">{{ number_format($relNew, 0, ",", ".") }} ₫</span>
                                                    <del class="product-discount">{{ number_format($value->price, 0, ",", ".") }} ₫</del>
                                                </p>
                                            @else
                                                <p class="jm-pdp-related-card__price">
                                                    <span class="price">{{ number_format($value->price, 0, ",", ".") }} ₫</span>
                                                </p>
                                            @endif
                                            <p class="jm-pdp-related-card__meta">
                                                <span>{{ (int) $value->view }} lượt xem</span>
                                                <span>·</span>
                                                <span>{{ (int) $value->buyed }} đã bán</span>
                                            </p>
                                            <a href="{{ url("gio-hang/them/" . (int) $value->id) }}" class="jm-pdp-related-card__cta">Thêm vào giỏ</a>
                                        </div>
                                    </article>
                                </div>
                            @endforeach
                        @endfor
                    </div>
                </div>
            </div>
        </div>

        @include("site.partials.stock-notice-modal")

        <script src="{{ $siteAssetUrl }}js/stock-notice-modal.js?v=4"></script>

        <div class="modal fade jm-review-thank-modal" id="jmReviewThankModal" tabindex="-1" role="dialog" aria-labelledby="jmReviewThankTitle" aria-hidden="true">
            <div class="modal-dialog modal-sm" role="document">
                <div class="modal-content jm-review-thank-modal__content">
                    <div class="modal-body jm-review-thank-modal__body">
                        <div class="jm-review-thank-modal__icon" aria-hidden="true">★</div>
                        <h4 class="jm-review-thank-modal__title" id="jmReviewThankTitle">Cảm ơn quý khách!</h4>
                        <div id="jmReviewThankStars" class="jm-review-thank-modal__stars"></div>
                        <p class="jm-review-thank-modal__score" id="jmReviewThankScore"></p>
                        <p class="jm-review-thank-modal__text" id="jmReviewThankText"></p>
                        <button type="button" class="btn jm-review-thank-modal__btn" data-dismiss="modal">Đóng</button>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
@endsection
