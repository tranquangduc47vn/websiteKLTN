@extends('layouts.admin.app')

@section('content')
@php
	$type = $page['type'];
	$exportQuery = array_filter([
		'type' => $type,
		'date_from' => $type === '' ? $page['date_from'] : '',
		'date_to' => $type === '' ? $page['date_to'] : '',
	], fn ($value) => $value !== '' && $value !== null);
@endphp
<nav aria-label="breadcrumb" class="admin-breadcrumb">
	<ol class="breadcrumb mb-0">
		<li class="breadcrumb-item"><a href="{{ url('admin') }}">Trang chủ</a></li>
		<li class="breadcrumb-item active">Thống kê doanh thu</li>
	</ol>
</nav>
<div class="admin-page-header">
	<h1 class="admin-page-title">Thống kê doanh thu</h1>
</div>
<div class="admin-card mb-3">
	<div class="admin-card-header">Bộ lọc thống kê</div>
	<div class="admin-card-body">
		<div class="adm-report-pills mb-3">
			<a href="{{ url('admin/report?type=day') }}" class="{{ $type === 'day' ? 'is-active' : '' }}">Hôm nay</a>
			<a href="{{ url('admin/report?type=week') }}" class="{{ $type === 'week' ? 'is-active' : '' }}">Tuần này</a>
			<a href="{{ url('admin/report?type=month') }}" class="{{ $type === 'month' ? 'is-active' : '' }}">Tháng này</a>
			<a href="{{ url('admin/report?type=year') }}" class="{{ $type === 'year' ? 'is-active' : '' }}">Năm nay</a>
		</div>
		<form method="get" action="{{ url('admin/report') }}" class="row g-2 align-items-end">
			<div class="col-md-3">
				<label class="form-label" for="report-from">Từ ngày</label>
				<input type="date" name="date_from" id="report-from" class="form-control" value="{{ $page['date_from'] }}">
			</div>
			<div class="col-md-3">
				<label class="form-label" for="report-to">Đến ngày</label>
				<input type="date" name="date_to" id="report-to" class="form-control" value="{{ $page['date_to'] }}">
			</div>
			<div class="col-md-4 d-flex flex-wrap gap-2">
				<button type="submit" class="btn btn-primary btn-sm">Lọc dữ liệu</button>
				<a href="{{ url('admin/report') }}" class="btn btn-outline-secondary btn-sm">Xóa bộ lọc</a>
			</div>
		</form>
	</div>
</div>

@include('admin.partials.export-toolbar', ['module' => 'revenue', 'query' => $exportQuery])

<div class="row g-3 mb-3">
	<div class="col-md-4">
		<div class="admin-stat-card">
			<div>
				<div class="stat-value" data-orders="{{ $page['total_orders'] }}">{{ number_format($page['total_orders']) }}</div>
				<div class="stat-label">Đơn hàng thành công</div>
			</div>
			<span class="admin-stat-icon icon-primary"><i class="fa-solid fa-receipt"></i></span>
		</div>
	</div>
	<div class="col-md-4">
		<div class="admin-stat-card">
			<div>
				<div class="stat-value" data-qty="{{ $page['total_qty'] }}">{{ number_format($page['total_qty']) }}</div>
				<div class="stat-label">Số lượng bán ra</div>
			</div>
			<span class="admin-stat-icon icon-info"><i class="fa-solid fa-boxes-stacked"></i></span>
		</div>
	</div>
	<div class="col-md-4">
		<div class="admin-stat-card">
			<div>
				<div class="stat-value" data-revenue="{{ $page['total_revenue'] }}">{{ number_format($page['total_revenue']) }}</div>
				<div class="stat-label">Doanh số thực thu (VNĐ)</div>
			</div>
			<span class="admin-stat-icon icon-success"><i class="fa-solid fa-sack-dollar"></i></span>
		</div>
	</div>
</div>

<div class="admin-card mb-3">
	<div class="admin-card-header">Biểu đồ doanh thu theo tháng (Năm {{ $page['year'] }})</div>
	<div class="admin-card-body">
		<div class="adm-report-chart"><canvas id="admReportChart"></canvas></div>
		<div class="table-responsive adm-table-wrap mt-3">
		<table class="table table-sm admin-table mb-0">
			<thead><tr>@foreach (range(1, 12) as $month)<th>Tháng {{ $month }}</th>@endforeach</tr></thead>
			<tbody><tr>
				@foreach ($page['chart'] as $amount)
					<td data-month-revenue="{{ $amount }}">{{ number_format($amount) }}</td>
				@endforeach
			</tr></tbody>
		</table>
		</div>
	</div>
</div>

@include('admin.partials.export-toolbar', ['module' => 'top_products', 'query' => $exportQuery])

<div class="admin-card">
	<div class="admin-card-header">Chi tiết bán ra &amp; tồn kho</div>
	<div class="table-responsive adm-table-wrap">
		<table class="table admin-table" id="reportProductsTable">
			<thead>
				<tr>
					<th>STT</th><th>Mã SP</th><th>Tên sản phẩm</th><th>Giá bán</th><th>Đã bán</th><th>Tồn kho</th><th>Trạng thái</th>
				</tr>
			</thead>
			<tbody>
				@foreach ($page['products'] as $index => $row)
					@php
						$stock = (int) ($row->quantity ?? 0);
						$sold = (int) ($row->da_ban ?? 0);
						if ($stock <= 0) { $label = 'Hết hàng'; }
						elseif ($stock <= 5) { $label = 'Sắp hết hàng'; }
						else { $label = 'An toàn'; }
					@endphp
					<tr>
						<td>{{ $index + 1 }}</td>
						<td>#{{ $row->id }}</td>
						<td>{{ $row->name }}</td>
						<td>{{ number_format((float) $row->price) }} VNĐ</td>
						<td>{{ $sold }}</td>
						<td>{{ $stock }}</td>
						<td><span class="admin-status-badge {{ $stock <= 0 ? 'adm-stock--out' : ($stock <= 5 ? 'adm-stock--low' : 'adm-stock--ok') }}">{{ $label }}</span></td>
					</tr>
				@endforeach
			</tbody>
		</table>
	</div>
</div>
<script src="{{ url('assets/admin/js/chart.min.js') }}"></script>
<script>
(function () {
	var canvas = document.getElementById('admReportChart');
	if (!canvas || typeof Chart === 'undefined') {
		return;
	}
	new Chart(canvas.getContext('2d')).Line({
		labels: ['T1', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'T8', 'T9', 'T10', 'T11', 'T12'],
		datasets: [{
			label: 'Doanh thu',
			fillColor: 'rgba(37, 99, 235, 0.12)',
			strokeColor: '#2563eb',
			pointColor: '#2563eb',
			pointStrokeColor: '#ffffff',
			pointHighlightFill: '#ffffff',
			pointHighlightStroke: '#2563eb',
			data: @json(array_map('floatval', array_values($page['chart']))),
		}],
	}, {
		responsive: true,
		maintainAspectRatio: false,
		animation: false,
		scaleBeginAtZero: true,
		bezierCurve: true,
		datasetFill: true,
	});
})();
</script>
@endsection
