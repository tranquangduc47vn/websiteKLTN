<!DOCTYPE html>
<html lang="vi">
<head>
	@include("layouts.storefront.head")
</head>
<body>
	@include("layouts.storefront.header")

	<div class="container">
		<div class="row">
			<div class="col-xs-12 col-sm-12 col-md-12 col-lg-12 clearpadding" style="margin-top: 15px;">
				@include("layouts.storefront.message")

				@php
					$jm_hide_sidebar = in_array($sf_temp ?? null, [
						"site/cart/index",
						"site/order/index",
						"site/order/checkout_qr",
						"site/user/index.php",
					], true);
				@endphp
				@if (! $jm_hide_sidebar)
					@include("layouts.storefront.sidebar")
				@endif

				@yield("content")

			</div>
		</div>
	</div>

	@include("layouts.storefront.footer")
	@include("layouts.storefront.scripts")
</body>
</html>
