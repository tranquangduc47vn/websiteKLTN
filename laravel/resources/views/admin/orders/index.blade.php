@extends('layouts.admin.app')

@section('content')
@php
	$filters = $filters ?? [];
	$query = array_filter([
		'q' => $filters['q'] ?? '',
		'status' => $filters['status'] ?? '',
		'payment' => $filters['payment'] ?? '',
		'date_from' => $filters['date_from'] ?? '',
		'date_to' => $filters['date_to'] ?? '',
	], fn ($value) => $value !== '' && $value !== null);
	$qs = $query === [] ? '' : ('?' . http_build_query($query));
	$total = (int) $page['total'];
	$offset = (int) $page['offset'];
	$per = \App\Services\OrderAdminService::PER_PAGE;
	$exportQuery = $query + ['offset' => $offset, 'per_page' => $per];
	$labels = [
		'0' => 'Đang chờ xử lý',
		'pending' => 'Đang chờ xử lý',
		'1' => 'Đã xác nhận',
		'confirmed' => 'Đã xác nhận',
		'2' => 'Đang giao',
		'packed' => 'Đang giao',
		'3' => 'Thành công',
		'completed' => 'Thành công',
		'4' => 'Đã hủy / Hoàn',
		'cancelled' => 'Đã hủy / Hoàn',
	];
	$tones = [
		'0' => 'admin-status-0', 'pending' => 'admin-status-0',
		'1' => 'admin-status-1', 'confirmed' => 'admin-status-1',
		'2' => 'admin-status-2', 'packed' => 'admin-status-2',
		'3' => 'admin-status-3', 'completed' => 'admin-status-3',
		'4' => 'admin-status-4', 'cancelled' => 'admin-status-4',
	];
@endphp
<nav aria-label="breadcrumb" class="admin-breadcrumb">
	<ol class="breadcrumb mb-0">
		<li class="breadcrumb-item"><a href="{{ url('admin') }}">Trang chủ</a></li>
		<li class="breadcrumb-item active">Đơn đặt hàng</li>
	</ol>
</nav>
<div class="admin-page-header d-flex flex-wrap justify-content-between align-items-end gap-2">
	<div>
		<h1 class="admin-page-title">Danh sách đơn</h1>
		<p class="admin-page-subtitle">{{ number_format($total) }} đơn</p>
	</div>
</div>
<div class="admin-card">
	<div class="admin-card-body">
		<form class="row g-2 align-items-end adm-filter" method="get" action="{{ url('admin/orders') }}">
			<div class="col-12 col-md-3">
				<label class="form-label" for="ord-q">Tên / SĐT</label>
				<input type="search" name="q" id="ord-q" class="form-control" value="{{ $filters['q'] }}" placeholder="Tìm khách hàng…">
			</div>
			<div class="col-6 col-md-2">
				<label class="form-label" for="ord-status">Trạng thái</label>
				<select name="status" id="ord-status" class="form-select">
					<option value="">— Tất cả —</option>
					<option value="0" @selected(($filters['status'] ?? '') === '0')>Chờ xử lý</option>
					<option value="1" @selected(($filters['status'] ?? '') === '1')>Đã xác nhận</option>
					<option value="2" @selected(($filters['status'] ?? '') === '2')>Đang giao</option>
					<option value="3" @selected(($filters['status'] ?? '') === '3')>Thành công</option>
					<option value="4" @selected(($filters['status'] ?? '') === '4')>Đã hủy / Hoàn</option>
				</select>
			</div>
			<div class="col-6 col-md-2">
				<label class="form-label" for="ord-payment">Thanh toán</label>
				<select name="payment" id="ord-payment" class="form-select">
					<option value="">— Tất cả —</option>
					<option value="cod" @selected(($filters['payment'] ?? '') === 'cod')>COD</option>
					<option value="transfer" @selected(($filters['payment'] ?? '') === 'transfer')>Chuyển khoản</option>
				</select>
			</div>
			<div class="col-6 col-md-2">
				<label class="form-label" for="ord-date-from">Từ ngày</label>
				<input type="date" name="date_from" id="ord-date-from" class="form-control" value="{{ $filters['date_from'] }}">
			</div>
			<div class="col-6 col-md-2">
				<label class="form-label" for="ord-date-to">Đến ngày</label>
				<input type="date" name="date_to" id="ord-date-to" class="form-control" value="{{ $filters['date_to'] }}">
			</div>
			<div class="col-12 col-md-1">
				<button class="btn btn-primary w-100" type="submit">Lọc</button>
			</div>
		</form>
	</div>
</div>
@include('admin.partials.export-toolbar', ['module' => 'orders', 'query' => $exportQuery])
<div class="admin-card">
	<div class="table-responsive adm-table-wrap">
		<table class="table admin-table table-hover mb-0">
			<thead>
				<tr>
					<th>STT</th>
					<th>Tên khách hàng</th>
					<th>Số ĐT</th>
					<th>Giá tiền</th>
					<th>Hình thức</th>
					<th>Trạng thái</th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				@forelse ($page['rows'] as $index => $row)
					@php
						$key = (string) $row->status;
						$pay = ($row->payment ?? '') === 'Chuyển khoản' ? 'Chuyển khoản' : 'COD';
					@endphp
					<tr>
						<td>{{ $offset + $index + 1 }}</td>
						<td>{{ $row->user_name }}</td>
						<td>{{ $row->user_phone }}</td>
						<td class="adm-price">{{ number_format((float) $row->amount, 0, ',', '.') }} VNĐ</td>
						<td><span class="adm-pay {{ $pay === 'Chuyển khoản' ? 'adm-pay--transfer' : '' }}">{{ $pay }}</span></td>
						<td><span class="admin-status-badge {{ $tones[$key] ?? 'admin-status-0' }}">{{ $labels[$key] ?? 'Đang chờ' }}</span></td>
						<td><a class="btn btn-sm btn-outline-primary" href="{{ url('admin/orders/detail/'.$row->id) }}">Chi tiết</a></td>
					</tr>
				@empty
					<tr><td colspan="7" class="admin-empty">Không có đơn hàng.</td></tr>
				@endforelse
			</tbody>
		</table>
	</div>
</div>
@if ($total > $per)
	<nav class="admin-table-footer" aria-label="Phân trang đơn hàng">
		<ul class="pagination mb-0">
			@for ($i = 0; $i < $total; $i += $per)
				<li class="page-item {{ $i === $offset ? 'active' : '' }}">
					<a class="page-link" href="{{ url('admin/orders/'.$i).$qs }}">{{ (int) ($i / $per) + 1 }}</a>
				</li>
			@endfor
		</ul>
	</nav>
@endif
@endsection
