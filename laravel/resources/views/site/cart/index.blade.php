@extends("layouts.storefront.sub")

{{-- Port application/views/site/cart/index.php. Biến cart_* đặt tên riêng vì StorefrontComposer ghi đè carts/total_items. --}}
@section("content")
@php
    $cartAsset = \App\Support\Storefront\Shop::assetUrl("");
@endphp
<link rel="stylesheet" href="{{ $cartAsset }}css/cart-luxury.css?v=15">
<link rel="stylesheet" href="{{ $cartAsset }}css/stock-notice-modal.css?v=1">

<div class="col-xs-12 col-sm-12 col-md-12 col-lg-12 clearpadding jm-cart-lux">
	<div class="jm-cart-lux__inner">

		@if (! empty($cart_message_success))
			<div class="jm-cart-flash jm-cart-flash--success" role="alert">{{ $cart_message_success }}</div>
		@endif
		@if (! empty($cart_message_fail))
			<div class="jm-cart-flash jm-cart-flash--error" role="alert">{{ $cart_message_fail }}</div>
		@endif

		@if ($cart_total_items > 0)
			<section class="jm-cart-panel" aria-labelledby="jm-cart-title">
				<header class="jm-cart-panel__head">
					<h1 id="jm-cart-title" class="jm-cart-panel__title">Giỏ hàng</h1>
					<p class="jm-cart-panel__count">{{ (int) $cart_total_items }} sản phẩm</p>
				</header>

				<div class="jm-cart-table-wrap">
					<table class="jm-cart-table">
						<thead>
							<tr>
								<th scope="col" class="jm-cart-col--idx">#</th>
								<th scope="col" class="jm-cart-col--product">Sản phẩm</th>
								<th scope="col" class="jm-cart-col--qty">Số lượng</th>
								<th scope="col" class="jm-cart-col--sub">Thành tiền</th>
								<th scope="col" class="jm-cart-col--act"><span class="sr-only">Xóa</span></th>
							</tr>
						</thead>
						<tbody>
						@foreach ($cart_items as $item)
							@php
								$currentSize = (string) ($item["options"]["size"] ?? "");
								$currentColor = (string) ($item["options"]["color"] ?? "");
								$sizeOptions = $item["variants"]["sizes"] ?? [$currentSize];
								$colorOptions = $item["variants"]["colors"] ?? [$currentColor];
								$canEditSize = count($sizeOptions) > 1;
								$canEditColor = count($colorOptions) > 1;
								$productUrl = ! empty($item["id"]) ? $item["product_url"] : "#";
							@endphp
							<tr class="jm-cart-row" data-rowid="{{ $item["rowid"] }}">
								<td class="jm-cart-col--idx" data-label="#">{{ $loop->iteration }}</td>
								<td class="jm-cart-col--product" data-label="Sản phẩm">
									<div class="jm-cart-product">
										<a href="{{ $productUrl }}" class="jm-cart-product__thumb">
											<img src="{{ $item["image_url"] }}" alt="">
										</a>
										<div class="jm-cart-product__info">
											<div class="jm-cart-product__head">
												<p class="jm-cart-product__name">
													<a href="{{ $productUrl }}">{{ \App\Support\Storefront\Product::displayName($item["name"] ?? "") }}</a>
												</p>
												<button type="button" class="jm-cart-remove jm-cart-remove--corner" data-rowid="{{ $item["rowid"] }}" title="Xóa sản phẩm" aria-label="Xóa sản phẩm">
													<span class="glyphicon glyphicon-trash" aria-hidden="true"></span>
												</button>
											</div>
											@if ($canEditSize || $canEditColor)
											<form method="post" action="{{ url("gio-hang/cap-nhat-thuoc-tinh") }}" class="jm-cart-opts-form">
												@csrf
												<input type="hidden" name="rowid" value="{{ $item["rowid"] }}">
												<div class="jm-cart-product__opts">
													<label class="jm-cart-opt">
														<span class="jm-cart-opt__label">Size</span>
														@if ($canEditSize)
														<select name="size" class="jm-cart-opt__select">
															@foreach ($sizeOptions as $sizeOption)
																<option value="{{ $sizeOption }}" @selected($sizeOption === $currentSize)>
																	{{ $sizeOption }}
																</option>
															@endforeach
														</select>
														@else
														<input type="hidden" name="size" value="{{ $currentSize }}">
														<span class="jm-cart-opt__value">{{ $currentSize }}</span>
														@endif
													</label>
													<label class="jm-cart-opt">
														<span class="jm-cart-opt__label">Màu</span>
														@if ($canEditColor)
														<select name="color" class="jm-cart-opt__select">
															@foreach ($colorOptions as $colorOption)
																<option value="{{ $colorOption }}" @selected($colorOption === $currentColor)>
																	{{ $colorOption }}
																</option>
															@endforeach
														</select>
														@else
														<input type="hidden" name="color" value="{{ $currentColor }}">
														<span class="jm-cart-opt__value">{{ $currentColor }}</span>
														@endif
													</label>
												</div>
											</form>
											@else
												<p class="jm-cart-product__opts jm-cart-product__opts--static">
													<span>Size {{ $currentSize }}</span>
													<span>Màu {{ $currentColor }}</span>
												</p>
											@endif
											<div class="jm-cart-product__qty">
												<div class="jm-cart-qty">
													<button type="button" class="jm-cart-qty__btn" data-action="sub" aria-label="Giảm số lượng">−</button>
													<span class="jm-cart-qty__val">{{ (int) $item["qty"] }}</span>
													<button type="button" class="jm-cart-qty__btn" data-action="sum" aria-label="Tăng số lượng">+</button>
												</div>
											</div>
										</div>
									</div>
								</td>
								<td class="jm-cart-col--qty" data-label="Số lượng">
									<div class="jm-cart-qty">
										<button type="button" class="jm-cart-qty__btn" data-action="sub" aria-label="Giảm số lượng">−</button>
										<span class="jm-cart-qty__val">{{ (int) $item["qty"] }}</span>
										<button type="button" class="jm-cart-qty__btn" data-action="sum" aria-label="Tăng số lượng">+</button>
									</div>
								</td>
								<td class="jm-cart-col--sub" data-label="Thành tiền">
									<span class="jm-cart-sub-label">Thành tiền</span>
									<span class="jm-cart-line-price">@money($item["subtotal"]) ₫</span>
								</td>
								<td class="jm-cart-col--act" data-label="">
									<button type="button" class="jm-cart-remove" data-rowid="{{ $item["rowid"] }}" title="Xóa sản phẩm" aria-label="Xóa sản phẩm">
										<span class="glyphicon glyphicon-trash" aria-hidden="true"></span>
									</button>
								</td>
							</tr>
						@endforeach
						</tbody>
					</table>
				</div>

				<footer class="jm-cart-summary">
					<div class="jm-cart-summary__row">
						<button type="button" class="jm-cart-clear">Xóa toàn bộ</button>
						<div class="jm-cart-summary__total">
							<span class="jm-cart-summary__label">Tổng cộng</span>
							<strong class="jm-cart-summary__amount">@money($cart_total_price) ₫</strong>
						</div>
					</div>
					<a href="{{ \App\Support\Storefront\Urls::checkout() }}" class="jm-cart-checkout">Tiến hành đặt hàng</a>
				</footer>
			</section>
		@else
			<section class="jm-cart-panel jm-cart-panel--empty">
				<header class="jm-cart-panel__head">
					<h1 class="jm-cart-panel__title">Giỏ hàng</h1>
					<p class="jm-cart-panel__count">0 sản phẩm</p>
				</header>
				<div class="jm-cart-empty">
					<img src="{{ url("upload/cart-empty.png") }}" alt="" class="jm-cart-empty__img">
					<p class="jm-cart-empty__text">Giỏ hàng của bạn đang trống</p>
					<a href="{{ url("ban-chay") }}" class="jm-cart-empty__cta">Tiếp tục mua sắm</a>
				</div>
			</section>
		@endif
	</div><!-- .jm-cart-lux__inner -->
</div>

@include("site.partials.stock-notice-modal")

<script>
window.JM_STOCK_NOTICE_AUTO = false;
window.JM_CART_AJAX = {
	optionsUrl: @json(url("gio-hang/cap-nhat-thuoc-tinh")),
	updateUrl: @json(url("gio-hang/update")),
	delUrl: @json(url("gio-hang/del")),
	loginUrl: @json(url("dang-nhap"))
};
</script>
<script src="{{ $cartAsset }}js/stock-notice-modal.js?v=4"></script>
<script src="{{ $cartAsset }}js/cart-luxury.js?v=7"></script>
@endsection
