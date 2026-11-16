@extends('layouts.admin.app')

@section('content')
@php
	$isConfirmed = $receipt->status === 'confirmed';
	$actorName = $isConfirmed && !empty($receipt->confirmer_name) ? $receipt->confirmer_name : ($receipt->creator_name ?? '');
	$actorLevel = $isConfirmed && isset($receipt->confirmer_level) ? (int) $receipt->confirmer_level : (isset($receipt->creator_level) ? (int) $receipt->creator_level : -1);
	$role = match ($actorLevel) { 0 => 'Admin', 1 => 'Mod', 2 => 'User', default => '—' };
@endphp
<nav aria-label="breadcrumb" class="admin-breadcrumb">
	<ol class="breadcrumb mb-0">
		<li class="breadcrumb-item"><a href="{{ url('admin/stock-receipts') }}">Phiếu nhập</a></li>
		<li class="breadcrumb-item active">{{ $receipt->receipt_code }}</li>
	</ol>
</nav>
<div class="admin-page-header d-flex flex-wrap justify-content-between align-items-start gap-2">
	<div>
		<h1 class="admin-page-title">{{ $receipt->receipt_code }}</h1>
		<span class="admin-status-badge admin-status-1">{{ $statusLabel }}</span>
	</div>
	@if ($receipt->status === 'draft' && $canReceipt)
		<div class="adm-actions">
			<a class="btn btn-sm btn-outline-primary" href="{{ url('admin/stock-receipts/edit/'.$receipt->id) }}">Sửa nháp</a>
			<form method="post" action="{{ url('admin/stock-receipts/confirm') }}">
				@csrf
				<input type="hidden" name="receipt_id" value="{{ (int) $receipt->id }}">
				<button class="btn btn-sm btn-primary" type="submit">Xác nhận nhập kho</button>
			</form>
			<form method="post" action="{{ url('admin/stock-receipts/cancel') }}" data-adm-confirm="Hủy phiếu nhập này?">
				@csrf
				<input type="hidden" name="receipt_id" value="{{ (int) $receipt->id }}">
				<button class="btn btn-sm btn-outline-danger" type="submit">Hủy phiếu</button>
			</form>
		</div>
	@endif
</div>
<div class="admin-card mb-3">
	<div class="table-responsive adm-table-wrap">
		<table class="table admin-table adm-kv mb-0">
			<tbody>
				<tr><td>Nhà cung cấp</td><td>{{ $receipt->supplier_name ?: '—' }}</td></tr>
				<tr><td>Ghi chú</td><td>{{ $receipt->note !== '' ? $receipt->note : '—' }}</td></tr>
				<tr><td>Tổng SL</td><td>{{ number_format((int) $receipt->total_qty) }}</td></tr>
				<tr><td>Tổng tiền nhập</td><td>{{ number_format((float) $receipt->total_amount, 0, ',', '.') }} ₫</td></tr>
				<tr><td>{{ $isConfirmed ? 'Người nhập kho' : 'Người tạo' }}</td><td>{{ $actorName !== '' ? $actorName : '—' }} ({{ $role }})</td></tr>
			</tbody>
		</table>
	</div>
</div>
<div class="admin-card mb-3">
	<div class="table-responsive adm-table-wrap">
<table class="table admin-table table-hover mb-0">
	<thead><tr><th>Sản phẩm</th><th>Size</th><th>Màu</th><th>SL</th><th>Tổng tiền nhập</th><th>Giá vốn</th></tr></thead>
	<tbody>
		@foreach ($items as $item)
			@php $line = (float) $item->subtotal > 0 ? (float) $item->subtotal : ((int) $item->qty * (float) $item->unit_cost); @endphp
			<tr>
				<td>{{ $item->product_name ?: ('#'.$item->product_id) }}</td>
				<td>{{ $item->size }}</td>
				<td>{{ $item->color }}</td>
				<td>{{ (int) $item->qty }}</td>
				<td>{{ number_format($line, 0, ',', '.') }}</td>
				<td>{{ number_format((float) $item->unit_cost, 0, ',', '.') }}</td>
			</tr>
		@endforeach
	</tbody>
</table>
	</div>
</div>
<a class="btn btn-outline-secondary mb-4" href="{{ url('admin/stock-receipts') }}">Quay lại</a>
@endsection
