@extends('layouts.admin.app')

@section('content')
@php
	$range = $dash['range'];
	$perm = $dash['perm'];
	$pills = ['today' => 'Hôm nay', '7d' => '7 ngày', '30d' => '30 ngày', 'month' => 'Tháng này', 'year' => 'Năm nay', 'custom' => 'Tùy chọn'];
	$format = function ($kpi) {
		return ($kpi['format'] ?? '') === 'money'
			? number_format((float) $kpi['value'], 0, ',', '.') . ' ₫'
			: number_format((int) $kpi['value'], 0, ',', '.');
	};
@endphp
<link rel="stylesheet" href="{{ url('assets/admin/css/admin-dashboard.css') }}?v=13">
<div class="adm-dash">
	<div class="adm-dash-hero">
		<div>
			<h2 class="adm-dash-hero__title">Tổng quan kinh doanh</h2>
			<p class="adm-dash-hero__sub">Theo dõi đơn hàng, doanh thu và tồn kho · <strong>{{ $range['label'] }}</strong></p>
		</div>
		<form class="adm-dash-filter" method="get" action="{{ url('admin/home') }}">
			<div class="adm-dash-filter__pills">
				@foreach ($pills as $key => $label)
					<label>
						<input type="radio" name="period" value="{{ $key }}" @checked($range['key'] === $key)>
						<span>{{ $label }}</span>
					</label>
				@endforeach
			</div>
			<div id="admDashCustomRange" @if ($range['key'] !== 'custom') hidden @endif>
				<input type="date" name="from" value="{{ request('from') }}">
				<input type="date" name="to" value="{{ request('to') }}">
			</div>
		</form>
	</div>

	@include('admin.partials.export-toolbar', ['module' => 'dashboard', 'query' => request()->only(['period', 'from', 'to'])])

	@foreach ($dash['kpi_groups'] as $group)
		@php
			$visible = array_values(array_filter($group['items'], fn ($kpi) => \App\Support\Admin\AdminPermission::kpiAllowed($kpi['key'], $login ?? null)));
		@endphp
		@continue($visible === [])
		<section class="adm-dash-kpi-group" aria-label="{{ $group['title'] }}">
			<h3 class="adm-dash-kpi-group__title">{{ $group['title'] }}</h3>
			<div class="row g-3">
				@foreach ($visible as $kpi)
					<div class="col-6 col-xl-3">
						<div class="adm-dash-kpi">
							<div class="adm-dash-kpi__label">{{ $kpi['label'] }}</div>
							<div class="adm-dash-kpi__value">{{ $format($kpi) }}</div>
							<div class="adm-dash-kpi__hint">{{ $kpi['hint'] ?? '' }}</div>
						</div>
					</div>
				@endforeach
			</div>
		</section>
	@endforeach

	@if (! empty($perm['revenue']) && ! empty($dash['charts']['labels']))
		<div class="adm-dash-card">
			<div class="adm-dash-card__head">Doanh thu theo ngày</div>
			<canvas id="admChartRevenue" height="120"></canvas>
		</div>
		<script>
			window.admDashboardChart = @json(['labels' => $dash['charts']['labels'], 'revenue' => $dash['charts']['revenue'], 'orders' => $dash['charts']['orders']]);
		</script>
		<script src="{{ url('assets/admin/js/chart.min.js') }}"></script>
	@endif
	<script src="{{ url('assets/admin/js/admin-dashboard.js') }}?v=8"></script>

	@if (! empty($perm['orders']))
		<div class="adm-dash-card">
			<div class="adm-dash-card__head">Đơn cần xử lý</div>
			<div class="adm-dash-card__body">
				@forelse ($dash['pending_orders'] as $order)
					<div>#{{ (int) $order->id }} — {{ $order->user_name }} — {{ \App\Support\Admin\AdminPermission::orderStatusText($order->status) }}</div>
				@empty
					<div class="admin-empty">Không có đơn mới hoặc chờ xác nhận.</div>
				@endforelse
			</div>
		</div>
	@endif

	@if (! empty($perm['products']) && ! empty($dash['top_products']))
		<div class="adm-dash-card">
			<div class="adm-dash-card__head">Top sản phẩm bán chạy</div>
			<ul>
				@foreach ($dash['top_products'] as $product)
					<li>{{ $product->name }} — {{ (int) $product->total_sold }}</li>
				@endforeach
			</ul>
		</div>
	@endif

	@if (! empty($perm['users']) && ! empty($dash['top_customers']))
		<div class="adm-dash-card">
			<div class="adm-dash-card__head">Khách hàng mua nhiều</div>
			<ul>
				@foreach ($dash['top_customers'] as $customer)
					<li>{{ $customer->user_name }} — {{ (int) $customer->order_count }} đơn — {{ number_format((float) $customer->total_spent, 0, ',', '.') }} ₫</li>
				@endforeach
			</ul>
		</div>
	@endif

	@if (! empty($perm['catalog']) && ! empty($dash['top_categories']))
		<div class="adm-dash-card">
			<div class="adm-dash-card__head">Danh mục bán chạy</div>
			<ul>
				@foreach ($dash['top_categories'] as $category)
					<li>{{ $category->name }} — {{ (int) $category->total_sold }}</li>
				@endforeach
			</ul>
		</div>
	@endif

	@if (! empty($perm['inventory']) && ! empty($dash['low_stock']))
		<div class="adm-dash-card">
			<div class="adm-dash-card__head">Sắp hết hàng</div>
			<ul>
				@foreach ($dash['low_stock'] as $product)
					<li>{{ $product->name }} — tồn {{ (int) $product->quantity }}</li>
				@endforeach
			</ul>
		</div>
	@endif

	@if (! empty($perm['products']) && ! empty($dash['recent_reviews']))
		<div class="adm-dash-card">
			<div class="adm-dash-card__head">Đánh giá gần đây</div>
			<ul>
				@foreach ($dash['recent_reviews'] as $review)
					<li>{{ $review->product_name }} — {{ (int) $review->stars }} sao — {{ $review->user_name }}</li>
				@endforeach
			</ul>
		</div>
	@endif

	@if (! empty($dash['notify_groups']))
		<div class="adm-dash-card">
			<div class="adm-dash-card__head">Thông báo</div>
			@foreach ($dash['notify_groups'] as $group)
				@foreach ($group['lines'] as $line)
					<p>{{ $group['title'] }}: {{ $line }}</p>
				@endforeach
			@endforeach
		</div>
	@endif
</div>
@endsection
