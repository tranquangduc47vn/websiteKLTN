@extends("layouts.storefront.sub")

{{-- Port application/views/site/order/checkout_qr.php --}}
@section("content")
@php
    $checkoutAsset = \App\Support\Storefront\Shop::assetUrl("");
    $amountFmt = number_format((int) $transaction->amount, 0, ',', '.') . ' ₫';
@endphp
<link rel="stylesheet" href="{{ $checkoutAsset }}css/checkout-luxury.css?v=5">

<div class="col-xs-12 col-sm-12 col-md-12 col-lg-12 clearpadding jm-checkout-lux">
	<div class="jm-checkout-lux__inner">

		<section class="jm-checkout-panel jm-checkout-qr" aria-labelledby="jm-checkout-qr-title">
			<header class="jm-checkout-panel__head">
				<h1 id="jm-checkout-qr-title" class="jm-checkout-panel__title">Thanh toán chuyển khoản</h1>
				<p class="jm-checkout-qr__meta">
					Đơn hàng <strong>#{{ (int) $transaction->id }}</strong>
					<span class="jm-checkout-qr__amount">{{ $amountFmt }}</span>
				</p>
			</header>

			<div class="jm-checkout-qr__body">
				<p class="jm-checkout-qr__lead">Mở app ngân hàng và chọn <strong>Quét mã QR</strong> để thanh toán.</p>

				<div class="jm-checkout-qr__frame">
					<img src="{{ $qr_image_url }}" alt="Mã QR VietQR đơn hàng #{{ (int) $transaction->id }}" width="280" height="280">
				</div>

				<div class="jm-checkout-qr__notes">
					<p class="jm-checkout-qr__notes-title">Lưu ý</p>
					<ul>
						<li>Số tiền và nội dung chuyển khoản đã được điền sẵn — vui lòng không chỉnh sửa.</li>
						<li>Sau khi chuyển khoản, đơn hàng sẽ được xác nhận trong thời gian sớm nhất.</li>
					</ul>
				</div>

				<div class="jm-checkout-qr__actions">
					<a href="{{ url('user') }}" class="jm-checkout-submit jm-checkout-qr__btn">Xem lịch sử đơn hàng</a>
					<a href="{{ url('/') }}" class="jm-checkout-qr__link-home">Tiếp tục mua sắm</a>
				</div>
			</div>
		</section>
	</div>
</div>
@endsection
