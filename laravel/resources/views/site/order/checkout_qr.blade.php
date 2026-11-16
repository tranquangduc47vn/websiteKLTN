@extends("layouts.storefront.sub")

{{-- Port application/views/site/order/checkout_qr.php --}}
@section("content")
@php
    $checkoutAsset = \App\Support\Storefront\Shop::assetUrl("");
    $amountFmt = number_format((int) $transaction->amount, 0, ',', '.') . ' ₫';
@endphp
<link rel="stylesheet" href="{{ $checkoutAsset }}css/checkout-luxury.css?v=6">

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
				@php
					$payState = (string) ($transaction->payment_status ?? 'pending');
					$paid = $payState === 'paid';
				@endphp
				<p id="jm-pay-status" class="jm-checkout-qr__status {{ $paid ? 'is-paid' : '' }}"
					data-poll="{{ ! empty($payment_poll) && ! $paid ? '1' : '0' }}"
					data-url="{{ url('thanh-toan/payment-status/' . (int) $transaction->id) }}">
					{{ $paid ? 'Thanh toán thành công' : 'Đang chờ thanh toán...' }}
				</p>
				<p class="jm-checkout-qr__lead">Mở app ngân hàng và chọn <strong>Quét mã QR</strong> để thanh toán.</p>

				<div class="jm-checkout-qr__frame">
					<img src="{{ $qr_image_url }}" alt="Mã QR VietQR đơn hàng #{{ (int) $transaction->id }}" width="280" height="280">
				</div>

				<dl class="jm-checkout-qr__pay">
					<div>
						<dt>Số tiền</dt>
						<dd>{{ $amountFmt }}</dd>
					</div>
					<div>
						<dt>Nội dung chuyển khoản</dt>
						<dd id="jm-pay-ref">{{ $transfer['reference'] }}</dd>
					</div>
					<div>
						<dt>Ngân hàng</dt>
						<dd>{{ $transfer['bank'] }}</dd>
					</div>
					<div>
						<dt>Số tài khoản</dt>
						<dd>{{ $transfer['account'] }}</dd>
					</div>
				</dl>

				<div class="jm-checkout-qr__notes">
					<p class="jm-checkout-qr__notes-title">Lưu ý</p>
					<ul>
						<li>Số tiền và nội dung chuyển khoản đã được điền sẵn — vui lòng không chỉnh sửa.</li>
						<li>Sau khi tiền vào đúng nội dung, trang này tự cập nhật. Không cần tải lại.</li>
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
<script>
(function () {
	var node = document.getElementById('jm-pay-status');
	if (!node || node.getAttribute('data-poll') !== '1') {
		return;
	}
	var url = node.getAttribute('data-url');
	var timer = null;
	var stopped = false;
	var started = Date.now();
	function stop() {
		stopped = true;
		if (timer) {
			clearInterval(timer);
			timer = null;
		}
	}
	function apply(data) {
		if (!data || !data.ok) {
			return;
		}
		if (data.payment_status === 'paid') {
			node.textContent = 'Thanh toán thành công';
			node.className += ' is-paid';
			stop();
			return;
		}
		if (data.order_status === 'cancelled' || data.order_status === 4) {
			node.textContent = 'Đơn hàng đã hủy';
			stop();
			return;
		}
		if (data.payment_status === 'review' || data.payment_status === 'mismatch') {
			node.textContent = 'Chưa xác nhận được thanh toán. Vui lòng giữ biên lai.';
			stop();
		}
	}
	function tick() {
		if (stopped) {
			return;
		}
		if (Date.now() - started > 10 * 60 * 1000) {
			node.textContent = 'Hết thời gian chờ. Tải lại trang để kiểm tra lại.';
			stop();
			return;
		}
		fetch(url, {
			headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
			credentials: 'same-origin'
		}).then(function (response) {
			return response.json();
		}).then(apply).catch(function () {});
	}
	tick();
	timer = setInterval(tick, 4000);
	document.addEventListener('visibilitychange', function () {
		if (document.hidden) {
			if (timer) {
				clearInterval(timer);
				timer = null;
			}
			return;
		}
		if (!stopped && !timer) {
			timer = setInterval(tick, 4000);
		}
	});
	window.addEventListener('pagehide', stop);
})();
</script>
@endsection
