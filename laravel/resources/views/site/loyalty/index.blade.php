@extends('layouts.storefront.sub')

@section('content')
<link rel="stylesheet" href="{{ \App\Support\Storefront\Shop::assetUrl('css/account-luxury.css') }}?v=3">
<div class="col-xs-12 jm-account-lux">
	<div class="jm-account-lux__inner">
		<section class="jm-account-panel">
			<div class="jm-account-panel__head">
				<h1 class="jm-account-panel__title">Chính sách tích điểm</h1>
				<p class="jm-account-panel__sub">{{ config('shop.name', 'qD Design') }} · Khách mua nhiều được ưu đãi hơn</p>
			</div>
			<div class="jm-account-panel__body jm-loyalty-policy">
				<h2>Cách tích điểm</h2>
				<ul>
					<li>Mỗi <strong>10.000 ₫</strong> thanh toán thực tế (sau voucher) khi đơn <strong>hoàn thành</strong> được <strong>1 điểm</strong> cơ bản.</li>
					<li>Hạng cao hơn nhận thêm điểm: Bạc ×1,1 · Vàng ×1,25 · VIP ×1,5.</li>
					<li>Điểm gắn với tài khoản đăng nhập.</li>
				</ul>
				<h2>Hạng thành viên</h2>
				<table class="jm-loyalty-tier-table">
					<thead><tr><th>Hạng</th><th>Điều kiện</th></tr></thead>
					<tbody>
						@foreach ($tiers as $tier)
							<tr><td>{{ $tier[0] }}</td><td>{{ $tier[1] }}</td></tr>
						@endforeach
					</tbody>
				</table>
				<p>Nhập mã tại bước thanh toán. Xem mã trong trang <a href="{{ url('user') }}">Tài khoản</a> sau khi đăng nhập.</p>
			</div>
		</section>
	</div>
</div>
@endsection
