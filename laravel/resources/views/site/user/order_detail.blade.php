@php
    $st = \App\Support\Storefront\OrderStatus::display($transaction->status);
    $discount = (int) ($transaction->discount_amount ?? 0);
    $voucherCode = trim((string) ($transaction->voucher_code ?? ''));
    $subtotal = 0;
    foreach ($order_lines as $line) {
        $subtotal += (float) $line->amount;
    }
@endphp
<div class="jm-account-order-detail">
	<div class="jm-account-order-detail__head">
		<p class="jm-account-order-detail__id">Đơn hàng #{{ (int) $transaction->id }}</p>
		<span class="jm-account-status {{ $st['class'] }}">{{ $st['label'] }}</span>
	</div>

	<dl class="jm-account-order-detail__meta">
		<div>
			<dt>Ngày đặt</dt>
			<dd>{{ \App\Support\Storefront\OrderStatus::formatCreated($transaction->created) }}</dd>
		</div>
		<div>
			<dt>Người nhận</dt>
			<dd>{{ $transaction->user_name }}</dd>
		</div>
		<div>
			<dt>SĐT nhận</dt>
			<dd>{{ $transaction->user_phone }}</dd>
		</div>
		<div>
			<dt>Địa chỉ giao</dt>
			<dd>{{ $transaction->user_address }}</dd>
		</div>
		@if (! empty($transaction->payment))
		<div>
			<dt>Thanh toán</dt>
			<dd>{{ $transaction->payment }}</dd>
		</div>
		@endif
		@if (! empty($transaction->message))
		<div class="jm-account-order-detail__meta--full">
			<dt>Ghi chú</dt>
			<dd>{!! nl2br(e($transaction->message)) !!}</dd>
		</div>
		@endif
		@if ((string) $transaction->status === '4' && ! empty($transaction->reason))
		<div class="jm-account-order-detail__meta--full">
			<dt>Lý do hủy</dt>
			<dd>{{ $transaction->reason }}</dd>
		</div>
		@endif
	</dl>

	@if (! empty($order_lines))
		<div class="jm-account-order-detail__items">
			<h3 class="jm-account-order-detail__items-title">Sản phẩm đã đặt</h3>
			<ul class="jm-account-order-detail__list">
				@foreach ($order_lines as $line)
					@php
						$img = \App\Support\Storefront\Product::imageUrl($line->image_link);
						$productUrl = ($line->product_id > 0 && ! empty($line->product))
							? \App\Support\Storefront\Urls::product($line->product)
							: '#';
						$size = trim((string) $line->size);
						$color = trim((string) $line->color);
					@endphp
					<li class="jm-account-order-detail__item">
						<a href="{{ $productUrl }}" class="jm-account-order-detail__thumb">
							<img src="{{ $img }}" alt="">
						</a>
						<div class="jm-account-order-detail__info">
							<a href="{{ $productUrl }}" class="jm-account-order-detail__name">
								{{ \App\Support\Storefront\Product::displayName($line->name) }}
							</a>
							@if ($size !== '' || $color !== '')
								<p class="jm-account-order-detail__variant">
									@if ($size !== '')<span>Size: {{ $size }}</span>@endif
									@if ($color !== '')<span>Màu: {{ $color }}</span>@endif
								</p>
							@endif
							<p class="jm-account-order-detail__qty">Số lượng: {{ (int) $line->qty }}</p>
						</div>
						<div class="jm-account-order-detail__price">
							{{ number_format($line->amount) }} ₫
						</div>
					</li>
				@endforeach
			</ul>
		</div>
	@else
		<p class="jm-account-order-detail__empty">Không có dòng sản phẩm trong đơn này.</p>
	@endif

	<div class="jm-account-order-detail__summary">
		@if ($discount > 0 || $voucherCode !== '')
			<div class="jm-account-order-detail__summary-row">
				<span>Tạm tính</span>
				<strong>{{ number_format($subtotal) }} ₫</strong>
			</div>
			@if ($voucherCode !== '')
				<div class="jm-account-order-detail__summary-row">
					<span>Voucher</span>
					<strong>{{ $voucherCode }}</strong>
				</div>
			@endif
			@if ($discount > 0)
				<div class="jm-account-order-detail__summary-row">
					<span>Giảm giá</span>
					<strong>-{{ number_format($discount) }} ₫</strong>
				</div>
			@endif
		@endif
		<div class="jm-account-order-detail__summary-row jm-account-order-detail__summary-row--total">
			<span>Tổng thanh toán</span>
			<strong>{{ number_format($transaction->amount) }} ₫</strong>
		</div>
	</div>
</div>
