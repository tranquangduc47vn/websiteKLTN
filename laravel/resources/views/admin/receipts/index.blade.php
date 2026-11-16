@extends('layouts.admin.app')

@section('content')
<nav aria-label="breadcrumb" class="admin-breadcrumb">
	<ol class="breadcrumb mb-0">
		<li class="breadcrumb-item"><a href="{{ url('admin') }}">Trang chủ</a></li>
		<li class="breadcrumb-item active">Phiếu nhập</li>
	</ol>
</nav>
<div class="admin-page-header d-flex flex-wrap justify-content-between align-items-start gap-2">
	<div>
		<h1 class="admin-page-title">Phiếu nhập kho</h1>
		<p class="admin-page-subtitle">Phiếu nhập từ nhà cung cấp.</p>
	</div>
	@if ($canReceipt)
		<a class="btn btn-primary" href="{{ url('admin/stock-receipts/add') }}">Nhập kho</a>
	@endif
</div>
<form class="admin-card mb-3" method="get" action="{{ url('admin/receipts') }}">
	<div class="admin-card-body">
		<div class="row g-2 align-items-end">
			<div class="col-md-3">
				<label class="form-label" for="rc-status">Trạng thái</label>
				<select name="status" id="rc-status" class="form-select">
					<option value="">— Tất cả —</option>
					<option value="draft" @selected($filterStatus === 'draft')>Nháp</option>
					<option value="confirmed" @selected($filterStatus === 'confirmed')>Đã nhập</option>
					<option value="cancelled" @selected($filterStatus === 'cancelled')>Đã hủy</option>
				</select>
			</div>
			<div class="col-md-4">
				<label class="form-label" for="rc-code">Mã phiếu</label>
				<input type="search" name="code" id="rc-code" class="form-control" value="{{ $filterCode }}">
			</div>
			<div class="col-md-2"><button class="btn btn-primary" type="submit">Lọc</button></div>
		</div>
	</div>
</form>
<div class="admin-card">
	<div class="table-responsive adm-table-wrap">
		<table class="table admin-table table-hover mb-0">
			<thead><tr><th>Mã</th><th>Nhà cung cấp</th><th>SL</th><th>Tiền</th><th>Trạng thái</th><th></th></tr></thead>
			<tbody>
				@forelse ($list as $row)
					@php
						$tone = match ((string) $row->status) {
							'confirmed' => 'admin-status-3',
							'cancelled' => 'admin-status-4',
							default => 'admin-status-0',
						};
						$label = match ((string) $row->status) {
							'confirmed' => 'Đã nhập',
							'cancelled' => 'Đã hủy',
							default => 'Nháp',
						};
					@endphp
					<tr>
						<td><code>{{ $row->receipt_code }}</code></td>
						<td>{{ $row->supplier_name ?: '—' }}</td>
						<td>{{ (int) $row->total_qty }}</td>
						<td class="adm-price">{{ number_format((float) ($row->lines_total ?? $row->total_amount), 0, ',', '.') }}</td>
						<td><span class="admin-status-badge {{ $tone }}">{{ $label }}</span></td>
						<td><a class="btn btn-sm btn-outline-primary" href="{{ url('admin/stock-receipts/view/'.$row->id) }}">Xem</a></td>
					</tr>
				@empty
					<tr><td colspan="6" class="admin-empty">Không có phiếu.</td></tr>
				@endforelse
			</tbody>
		</table>
	</div>
</div>
@endsection
