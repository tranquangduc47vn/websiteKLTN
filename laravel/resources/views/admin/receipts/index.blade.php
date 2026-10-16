@extends('layouts.admin.app')

@section('content')
<nav aria-label="breadcrumb" class="mb-3">
	<ol class="breadcrumb mb-0">
		<li class="breadcrumb-item"><a href="{{ url('admin') }}">Trang chủ</a></li>
		<li class="breadcrumb-item active">Phiếu nhập</li>
	</ol>
</nav>
<div class="d-flex justify-content-between mb-3">
	<h1 class="h4 mb-0">Phiếu nhập kho</h1>
	@if ($canReceipt)
		<a class="btn btn-primary btn-sm" href="{{ url('admin/stock-receipts/add') }}">Nhập kho</a>
	@endif
</div>
<form class="row g-2 align-items-end mb-3" method="get" action="{{ url('admin/receipts') }}">
	<div class="col-md-3">
		<label class="form-label" for="rc-status">Trạng thái</label>
		<select name="status" id="rc-status" class="form-select">
			<option value="">— Tất cả —</option>
			<option value="draft" @selected($filterStatus === 'draft')>Nháp</option>
			<option value="confirmed" @selected($filterStatus === 'confirmed')>Đã nhập</option>
			<option value="cancelled" @selected($filterStatus === 'cancelled')>Đã hủy</option>
		</select>
	</div>
	<div class="col-md-3">
		<label class="form-label" for="rc-code">Mã phiếu</label>
		<input type="search" name="code" id="rc-code" class="form-control" value="{{ $filterCode }}">
	</div>
	<div class="col-md-2"><button class="btn btn-primary" type="submit">Lọc</button></div>
</form>
<table class="table admin-table">
	<thead><tr><th>Mã</th><th>Nhà cung cấp</th><th>SL</th><th>Tiền</th><th>Trạng thái</th><th></th></tr></thead>
	<tbody>
		@forelse ($list as $row)
			<tr>
				<td>{{ $row->receipt_code }}</td>
				<td>{{ $row->supplier_name ?: '—' }}</td>
				<td>{{ (int) $row->total_qty }}</td>
				<td>{{ number_format((float) ($row->lines_total ?? $row->total_amount), 0, ',', '.') }}</td>
				<td>{{ $row->status }}</td>
				<td><a href="{{ url('admin/stock-receipts/view/'.$row->id) }}">Xem</a></td>
			</tr>
		@empty
			<tr><td colspan="6">Không có phiếu.</td></tr>
		@endforelse
	</tbody>
</table>
@endsection
