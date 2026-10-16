@extends("layouts.storefront.sub")

{{-- Port application/views/site/order/index.php --}}
@section("content")
@php
    $checkoutAsset = \App\Support\Storefront\Shop::assetUrl("");
    $user = $checkout_user ?? ($user ?? null);
    $totalFmt = number_format((int) $total_amount, 0, ',', '.') . ' ₫';
    $cv = (isset($checkout_voucher) && is_array($checkout_voucher)) ? $checkout_voucher : null;
    $hasApplied = ($cv && (int) $cv['discount'] > 0 && (int) $cv['cart_subtotal'] === (int) $total_amount);
    $displayFinal = $hasApplied ? (int) $cv['final'] : (int) $total_amount;
    $displayFinalFmt = number_format($displayFinal, 0, ',', '.') . ' ₫';
    $voucherInitCode = $hasApplied ? $cv['code'] : old('voucher_code');
    $flashBag = $flash ?? ['success' => '', 'fail' => ''];
@endphp
<link rel="stylesheet" href="{{ $checkoutAsset }}css/checkout-luxury.css?v=7">

<div class="col-xs-12 col-sm-12 col-md-12 col-lg-12 clearpadding jm-checkout-lux">
	<div class="jm-checkout-lux__inner">

		@if (! empty($flashBag['success']))
			<div class="jm-checkout-flash jm-checkout-flash--success" role="alert">{{ $flashBag['success'] }}</div>
		@endif
		@if (! empty($flashBag['fail']))
			<div class="jm-checkout-flash jm-checkout-flash--error" role="alert">{{ $flashBag['fail'] }}</div>
		@endif

		<section class="jm-checkout-panel" aria-labelledby="jm-checkout-title">
			<header class="jm-checkout-panel__head">
				<h1 id="jm-checkout-title" class="jm-checkout-panel__title">Thông tin thanh toán</h1>
				@if (! empty($user))
					<p class="jm-checkout-panel__loyalty">Tích điểm khi đơn hoàn thành · <a href="{{ url('tich-diem') }}">Chính sách</a></p>
				@else
					<p class="jm-checkout-panel__loyalty"><a href="{{ url('dang-nhap') }}">Đăng nhập</a> để dùng voucher và tích điểm.</p>
				@endif
			</header>

			<form class="jm-checkout-form" id="jmCheckoutForm" method="post" novalidate>
				@csrf
				<div class="jm-checkout-field">
					<label for="jm-checkout-name">Họ và tên</label>
					<input id="jm-checkout-name" type="text" name="name" class="form-control" value="{{ old('name', $user->name ?? '') }}" required>
					@error('name')<span class="jm-checkout-error">{{ $message }}</span>@enderror
				</div>

				<div class="jm-checkout-field">
					<label for="jm-checkout-email">Email</label>
					<input id="jm-checkout-email" name="email" type="email" class="form-control" value="{{ old('email', $user->email ?? '') }}" required>
					@error('email')<span class="jm-checkout-error">{{ $message }}</span>@enderror
				</div>

				<div class="jm-checkout-field">
					<label for="jm-checkout-phone">Số điện thoại</label>
					<input id="jm-checkout-phone" name="phone" type="text" class="form-control" value="{{ old('phone', $user->phone ?? '') }}" required>
					@error('phone')<span class="jm-checkout-error">{{ $message }}</span>@enderror
				</div>

				<div class="jm-checkout-field">
					<label for="jm-checkout-address">Địa chỉ</label>
					<input id="jm-checkout-address" type="text" name="address" class="form-control" value="{{ old('address', $user->address ?? '') }}" required>
					@error('address')<span class="jm-checkout-error">{{ $message }}</span>@enderror
				</div>

				<div class="jm-checkout-field">
					<label for="jm-checkout-message">Lời nhắn</label>
					<textarea id="jm-checkout-message" name="message" class="form-control" rows="4">{{ old('message') }}</textarea>
					@error('message')<span class="jm-checkout-error">{{ $message }}</span>@enderror
				</div>

				<div class="jm-checkout-field">
					<label for="jm-checkout-voucher">Mã voucher</label>
					<div class="jm-checkout-voucher-row">
						<input id="jm-checkout-voucher" type="text" name="voucher_code" class="form-control text-uppercase" autocomplete="off"
							placeholder="{{ ! empty($user) ? 'Nhập mã giảm giá' : 'Cần đăng nhập' }}"
							@if (empty($user)) disabled @endif
							value="{{ $voucherInitCode }}">
						<button type="button" class="jm-checkout-voucher-btn" id="jmCheckoutVoucherBtn" @if (empty($user)) disabled @endif>Xác nhận</button>
					</div>
					<p class="jm-checkout-voucher-msg" id="jmCheckoutVoucherMsg" role="status" aria-live="polite" hidden></p>
				</div>

				<div class="jm-checkout-pay">
					<p class="jm-checkout-pay__title">Phương thức thanh toán</p>
					<label class="jm-checkout-pay__option">
						<input type="radio" name="payment" value="COD" @checked(old('payment', 'COD') === 'COD')>
						<span class="jm-checkout-pay__text">
							Thanh toán khi nhận hàng (COD)
							<span class="jm-checkout-pay__hint">Thanh toán tiền mặt khi nhận hàng</span>
						</span>
					</label>
					<label class="jm-checkout-pay__option">
						<input type="radio" name="payment" value="Chuyển khoản" @checked(old('payment') === 'Chuyển khoản')>
						<span class="jm-checkout-pay__text">
							Chuyển khoản ngân hàng (VietQR)
							<span class="jm-checkout-pay__hint">Quét mã QR sau khi xác nhận đơn</span>
						</span>
					</label>
				</div>

				<div class="jm-checkout-summary" id="jmCheckoutSummary" aria-live="polite">
					<div class="jm-checkout-summary__row jm-checkout-summary__row--discount" id="jmCheckoutDiscountRow" @if (! $hasApplied) hidden @endif>
						<span>Giảm voucher</span>
						<strong id="jmCheckoutDiscountAmt">−{{ $hasApplied ? number_format((int) $cv['discount'], 0, ',', '.') . ' ₫' : '0 ₫' }}</strong>
					</div>
					<div class="jm-checkout-summary__total">
						<span class="jm-checkout-summary__label">Tổng thanh toán</span>
						<div class="jm-checkout-summary__amounts">
							<span class="jm-checkout-summary__old" id="jmCheckoutOldTotal" @if (! $hasApplied) hidden @endif>{{ $totalFmt }}</span>
							<strong class="jm-checkout-summary__new" id="jmCheckoutNewTotal">{{ $hasApplied ? $displayFinalFmt : $totalFmt }}</strong>
						</div>
					</div>
				</div>

				<button type="submit" class="jm-checkout-submit">Tiến hành thanh toán</button>
			</form>
		</section>
	</div>
</div>

<script>
window.jmCheckoutVoucher = {!! json_encode([
    'applyUrl' => $checkout_apply_url ?? '',
    'subtotal' => (int) $total_amount,
    'subtotalFmt' => $totalFmt,
    'loggedIn' => ! empty($user),
    'applied' => $hasApplied ? [
        'discount' => (int) $cv['discount'],
        'final' => (int) $cv['final'],
        'discountFmt' => '−' . number_format((int) $cv['discount'], 0, ',', '.') . ' ₫',
        'finalFmt' => $displayFinalFmt,
    ] : null,
], JSON_UNESCAPED_UNICODE) !!};
</script>
<script src="{{ $checkoutAsset }}js/checkout-voucher.js?v=2"></script>
@endsection
