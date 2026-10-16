@extends('layouts.admin.app')

@section('content')
@php
	$isConfirmed = $receipt->status === 'confirmed';
	$actorName = $isConfirmed && !empty($receipt->confirmer_name) ? $receipt->confirmer_name : ($receipt->creator_name ?? '');
	$actorLevel = $isConfirmed && isset($receipt->confirmer_level) ? (int) $receipt->confirmer_level : (isset($receipt->creator_level) ? (int) $receipt->creator_level : -1);
	$role = match ($actorLevel) { 0 => 'Admin', 1 => 'Mod', 2 => 'User', default => '—' };
@endphp
<nav aria-label="breadcrumb" class="mb-3">
	<ol class="breadcrumb mb-0">
		<li class="breadcrumb-item"><a href="{{ url('admin/stock-receipts') }}">Phiếu nhập</a></li>
		<li class="breadcrumb-item active">{{ $receipt->receipt_code }}</li>
	</ol>
</nav>
<h1 class="h4">{{ $receipt->receipt_code }}</h1>
<p><span class="badge text-bg-secondary">{{ $statusLabel }}</span></p>
@if ($receipt->status === 'draft' && $canReceipt)
	<p>
		<a href="{{ url('admin/stock-receipts/edit/'.$receipt->id) }}">Sửa nháp</a>
		<form method="post" action="{{ url('admin/stock-receipts/confirm') }}" class="d-inline">
			@csrf
			<input type="hidden" name="receipt_id" value="{{ (int) $receipt->id }}">
			<button type="submit">Xác nhận nhập kho</button>
		</form>
		<form method="post" action="{{ url('admin/stock-receipts/cancel') }}" class="d-inline">
			@csrf
			<input type="hidden" name="receipt_id" value="{{ (int) $receipt->id }}">
			<button type="submit">Hủy phiếu</button>
		</form>
	</p>
@endif
<p>Nhà cung cấp: {{ $receipt->supplier_name ?: '—' }}</p>
<p>Ghi chú: {{ $receipt->note !== '' ? $receipt->note : '—' }}</p>
<p>Tổng SL: {{ number_format((int) $receipt->total_qty) }}</p>
<p>Tổng tiền nhập: {{ number_format((float) $receipt->total_amount, 0, ',', '.') }} ₫</p>
<p>{{ $isConfirmed ? 'Người nhập kho' : 'Người tạo' }}: {{ $actorName !== '' ? $actorName : '—' }} ({{ $role }})</p>
<table class="table admin-table">
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
<a href="{{ url('admin/stock-receipts') }}">Quay lại</a>
@endsection
