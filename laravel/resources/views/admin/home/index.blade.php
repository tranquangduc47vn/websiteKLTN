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
	$icons = [
		'orders_today' => 'fa-solid fa-receipt',
		'revenue_today' => 'fa-solid fa-sack-dollar',
		'pending' => 'fa-solid fa-hourglass-half',
		'shipping' => 'fa-solid fa-truck-fast',
		'completed' => 'fa-solid fa-circle-check',
		'cancelled' => 'fa-solid fa-ban',
		'new_users' => 'fa-solid fa-user-plus',
		'low_stock' => 'fa-solid fa-triangle-exclamation',
		'stock_import' => 'fa-solid fa-dolly',
		'inventory_value' => 'fa-solid fa-warehouse',
		'cogs' => 'fa-solid fa-coins',
		'gross_profit' => 'fa-solid fa-chart-line',
	];
@endphp
<link rel="stylesheet" href="{{ url('assets/admin/css/admin-dashboard.css') }}?v=15">
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
						<article class="adm-dash-kpi adm-dash-kpi--{{ $kpi['key'] }}">
							<div class="adm-dash-kpi__top">
								<span class="adm-dash-kpi__icon" aria-hidden="true"><i class="{{ $icons[$kpi['key']] ?? 'fa-solid fa-chart-simple' }}"></i></span>
							</div>
							<div class="adm-dash-kpi__label">{{ $kpi['label'] }}</div>
							<div class="adm-dash-kpi__value">{{ $format($kpi) }}</div>
							<div class="adm-dash-kpi__hint">{{ $kpi['hint'] ?? '' }}</div>
						</article>
					</div>
				@endforeach
			</div>
		</section>
	@endforeach

	@if (! empty($perm['revenue']) && ! empty($dash['charts']['labels']))
		<div class="adm-dash-card">
			<div class="adm-dash-card__head">Doanh thu theo ngày</div>
			<div class="adm-dash-chart">
				<canvas id="admChartRevenue"></canvas>
			</div>
		</div>
		<script>
			window.admDashboardChart = @json(['labels' => $dash['charts']['labels'], 'revenue' => $dash['charts']['revenue'], 'orders' => $dash['charts']['orders']]);
		</script>
		<script src="{{ url('assets/admin/js/chart.min.js') }}"></script>
	@endif
	<script src="{{ url('assets/admin/js/admin-dashboard.js') }}?v=9"></script>

	@if (! empty($perm['orders']))
		<div class="adm-dash-card">
			<div class="adm-dash-card__head">Đơn hàng gần đây cần xử lý</div>
			<div class="adm-dash-card__body adm-dash-card__body--table">
				@if (count($dash['pending_orders']) === 0)
					<div class="admin-empty">Không có đơn mới hoặc chờ xác nhận.</div>
				@else
					<div class="adm-dash-table-wrap">
						<table class="adm-dash-table">
							<thead>
								<tr>
									<th>Mã</th>
									<th>Khách</th>
									<th>Trạng thái</th>
								</tr>
							</thead>
							<tbody>
								@foreach ($dash['pending_orders'] as $order)
									<tr>
										<td><a href="{{ url('admin/orders/detail/' . (int) $order->id) }}">#{{ (int) $order->id }}</a></td>
										<td>{{ $order->user_name }}</td>
										<td><span class="adm-dash-status">{{ \App\Support\Admin\AdminPermission::orderStatusText($order->status) }}</span></td>
									</tr>
								@endforeach
							</tbody>
						</table>
					</div>
				@endif
			</div>
		</div>
	@endif

	<div class="row g-3 adm-dash-lists">

	@if (! empty($perm['products']) && ! empty($dash['top_products']))
		<div class="col-12 col-lg-6"><div class="adm-dash-card">
			<div class="adm-dash-card__head">Top sản phẩm bán chạy</div>
			<ul>
				@foreach ($dash['top_products'] as $product)
					<li>{{ $product->name }} — {{ (int) $product->total_sold }}</li>
				@endforeach
			</ul>
		</div></div>
	@endif

	@if (! empty($perm['users']) && ! empty($dash['top_customers']))
		<div class="col-12 col-lg-6"><div class="adm-dash-card">
			<div class="adm-dash-card__head">Khách hàng mua nhiều</div>
			<ul>
				@foreach ($dash['top_customers'] as $customer)
					<li>{{ $customer->user_name }} — {{ (int) $customer->order_count }} đơn — {{ number_format((float) $customer->total_spent, 0, ',', '.') }} ₫</li>
				@endforeach
			</ul>
		</div></div>
	@endif

	@if (! empty($perm['catalog']) && ! empty($dash['top_categories']))
		<div class="col-12 col-lg-6"><div class="adm-dash-card">
			<div class="adm-dash-card__head">Danh mục bán chạy</div>
			<ul>
				@foreach ($dash['top_categories'] as $category)
					<li>{{ $category->name }} — {{ (int) $category->total_sold }}</li>
				@endforeach
			</ul>
		</div></div>
	@endif

	@if (! empty($perm['inventory']) && ! empty($dash['low_stock']))
		<div class="col-12 col-lg-6"><div class="adm-dash-card">
			<div class="adm-dash-card__head">Sắp hết hàng</div>
			<ul>
				@foreach ($dash['low_stock'] as $product)
					<li>{{ $product->name }} — tồn {{ (int) $product->quantity }}</li>
				@endforeach
			</ul>
		</div></div>
	@endif

	@if (! empty($perm['products']) && ! empty($dash['recent_reviews']))
		<div class="col-12 col-lg-6"><div class="adm-dash-card">
			<div class="adm-dash-card__head">Đánh giá gần đây</div>
			<ul>
				@foreach ($dash['recent_reviews'] as $review)
					<li>{{ $review->product_name }} — {{ (int) $review->stars }} sao — {{ $review->user_name }}</li>
				@endforeach
			</ul>
		</div></div>
	@endif

	@if (! empty($dash['notify_groups']))
		<div class="col-12"><div class="adm-dash-card">
			<div class="adm-dash-card__head">Thông báo</div>
			@foreach ($dash['notify_groups'] as $group)
				@foreach ($group['lines'] as $line)
					<p>{{ $group['title'] }}: {{ $line }}</p>
				@endforeach
			@endforeach
		</div></div>
	@endif
	</div>
</div>
@endsection
