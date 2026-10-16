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
<nav aria-label="breadcrumb" class="mb-3">
	<ol class="breadcrumb mb-0">
		<li class="breadcrumb-item"><a href="{{ url('admin') }}">Trang chủ</a></li>
		<li class="breadcrumb-item active">Thống kê doanh thu</li>
	</ol>
</nav>
<div class="card mb-3">
	<div class="card-header">Bộ lọc thống kê</div>
	<div class="card-body">
		<div class="btn-group flex-wrap mb-3">
			<a href="{{ url('admin/report?type=day') }}" class="btn btn-sm {{ $type === 'day' ? 'btn-primary' : 'btn-outline-secondary' }}">Hôm nay</a>
			<a href="{{ url('admin/report?type=week') }}" class="btn btn-sm {{ $type === 'week' ? 'btn-primary' : 'btn-outline-secondary' }}">Tuần này</a>
			<a href="{{ url('admin/report?type=month') }}" class="btn btn-sm {{ $type === 'month' ? 'btn-primary' : 'btn-outline-secondary' }}">Tháng này</a>
			<a href="{{ url('admin/report?type=year') }}" class="btn btn-sm {{ $type === 'year' ? 'btn-primary' : 'btn-outline-secondary' }}">Năm nay</a>
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
			<div class="col-md-4">
				<button type="submit" class="btn btn-primary btn-sm">Lọc dữ liệu</button>
				<a href="{{ url('admin/report') }}" class="btn btn-outline-secondary btn-sm">Xóa bộ lọc</a>
			</div>
		</form>
	</div>
</div>

@include('admin.partials.export-toolbar', ['module' => 'revenue', 'query' => $exportQuery])

<div class="row g-3 mb-3">
	<div class="col-md-4">
		<div class="card"><div class="card-body">
			<div class="fs-4" data-orders="{{ $page['total_orders'] }}">{{ number_format($page['total_orders']) }}</div>
			<div>Đơn hàng thành công</div>
		</div></div>
	</div>
	<div class="col-md-4">
		<div class="card"><div class="card-body">
			<div class="fs-4" data-qty="{{ $page['total_qty'] }}">{{ number_format($page['total_qty']) }}</div>
			<div>Số lượng bán ra</div>
		</div></div>
	</div>
	<div class="col-md-4">
		<div class="card"><div class="card-body">
			<div class="fs-4" data-revenue="{{ $page['total_revenue'] }}">{{ number_format($page['total_revenue']) }}</div>
			<div>Doanh số thực thu (VNĐ)</div>
		</div></div>
	</div>
</div>

<div class="card mb-3">
	<div class="card-header">Biểu đồ doanh thu theo tháng (Năm {{ $page['year'] }})</div>
	<div class="card-body">
		<table class="table table-sm mb-0">
			<thead><tr>@foreach (range(1, 12) as $month)<th>Tháng {{ $month }}</th>@endforeach</tr></thead>
			<tbody><tr>
				@foreach ($page['chart'] as $amount)
					<td data-month-revenue="{{ $amount }}">{{ number_format($amount) }}</td>
				@endforeach
			</tr></tbody>
		</table>
	</div>
</div>

@include('admin.partials.export-toolbar', ['module' => 'top_products', 'query' => $exportQuery])

<div class="card">
	<div class="card-header">Chi tiết bán ra &amp; tồn kho</div>
	<div class="card-body table-responsive">
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
						<td>{{ $label }}</td>
					</tr>
				@endforeach
			</tbody>
		</table>
	</div>
</div>
@endsection
