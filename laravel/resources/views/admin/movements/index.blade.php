@extends('layouts.admin.app')

@section('content')
@php
	$query = array_filter(['sku' => $filterSku, 'type' => $filterType], fn ($value) => $value !== '');
	$qs = $query === [] ? '' : ('?' . http_build_query($query));
	$per = \App\Http\Controllers\Admin\StockMovementsController::PER_PAGE;
@endphp
<nav aria-label="breadcrumb" class="mb-3">
	<ol class="breadcrumb mb-0">
		<li class="breadcrumb-item"><a href="{{ url('admin/inventory') }}">Tồn kho</a></li>
		<li class="breadcrumb-item active">Lịch sử biến động</li>
	</ol>
</nav>
<h1 class="h4">Lịch sử biến động kho</h1>
<form class="row g-2 align-items-end mb-3" method="get" action="{{ url('admin/stock-movements') }}">
	<div class="col-md-3">
		<label class="form-label" for="mv-sku">SKU</label>
		<input type="search" name="sku" id="mv-sku" class="form-control" value="{{ $filterSku }}">
	</div>
	<div class="col-md-3">
		<label class="form-label" for="mv-type">Loại</label>
		<select name="type" id="mv-type" class="form-select">
			<option value="">— Tất cả —</option>
			<option value="in" @selected($filterType === 'in')>Nhập (in)</option>
			<option value="out" @selected($filterType === 'out')>Xuất (out)</option>
			<option value="adjust" @selected($filterType === 'adjust')>Kiểm kê (adjust)</option>
		</select>
	</div>
	<div class="col-md-2"><button class="btn btn-primary" type="submit">Lọc</button></div>
</form>
<table class="table admin-table">
	<thead>
		<tr><th>Thời gian</th><th>SKU</th><th>Sản phẩm</th><th>Loại</th><th>Trước</th><th>Thay đổi</th><th>Sau</th><th>Tham chiếu</th><th>Ghi chú</th></tr>
	</thead>
	<tbody>
		@forelse ($rows as $row)
			<tr>
				<td>{{ date('d/m/Y H:i', (int) $row->created) }}</td>
				<td><code>{{ $row->sku ?: '—' }}</code></td>
				<td>{{ $row->product_name ?: ('#'.$row->product_id) }}</td>
				<td>{{ $row->movement_type }}</td>
				<td>{{ number_format((int) $row->before_qty) }}</td>
				<td>{{ ((int) $row->qty_change > 0 ? '+' : '') . number_format((int) $row->qty_change) }}</td>
				<td>{{ number_format((int) $row->after_qty) }}</td>
				<td>{{ $row->reference_type }} #{{ (int) $row->reference_id }}</td>
				<td>{{ $row->note }}</td>
			</tr>
		@empty
			<tr><td colspan="9">Chưa có biến động.</td></tr>
		@endforelse
	</tbody>
</table>
@if ($total > $per)
	<nav class="mt-3">
		<ul class="pagination">
			@for ($i = 0; $i < $total; $i += $per)
				<li class="page-item {{ $i === $offset ? 'active' : '' }}">
					<a class="page-link" href="{{ url('admin/stock-movements/index/'.$i).$qs }}">{{ (int) ($i / $per) + 1 }}</a>
				</li>
			@endfor
		</ul>
	</nav>
@endif
@endsection
