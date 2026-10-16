@extends('layouts.admin.app')

@section('content')
<nav aria-label="breadcrumb" class="mb-3">
	<ol class="breadcrumb mb-0">
		<li class="breadcrumb-item"><a href="{{ url('admin/inventory') }}">Tồn kho</a></li>
		<li class="breadcrumb-item active">Kiểm kê</li>
	</ol>
</nav>
<h1 class="h4">Kiểm kê / điều chỉnh tồn</h1>
<p><strong>{{ $productName }}</strong></p>
<p>SKU: <code>{{ $variant->sku }}</code> — Màu: {{ $variant->color ?: '—' }} — Size: {{ $variant->size ?: '—' }} — Tồn hiện tại: <strong>{{ number_format((int) $variant->stock) }}</strong></p>
@if (! $canAdjust)
	<div class="alert alert-info">Bạn chỉ được xem kiểm kê. Điều chỉnh số lượng tồn cần quyền Admin.</div>
	<a href="{{ url('admin/inventory') }}">Quay lại tồn kho</a>
@else
	<form method="post" action="{{ url('admin/inventory/adjust/'.$variant->id) }}" class="admin-card">
		@csrf
		<input type="hidden" name="variant_id" value="{{ (int) $variant->id }}">
		<div class="mb-3">
			<label class="form-label" for="new-qty">Tồn sau điều chỉnh</label>
			<input type="number" name="new_qty" id="new-qty" class="form-control" min="0" step="1" required value="{{ (int) $variant->stock }}">
		</div>
		<div class="mb-3">
			<label class="form-label" for="adjust-note">Lý do</label>
			<textarea name="note" id="adjust-note" class="form-control" rows="3" required></textarea>
		</div>
		<button type="submit" class="btn btn-primary">Lưu điều chỉnh</button>
		<a href="{{ url('admin/inventory') }}">Hủy</a>
	</form>
@endif
@endsection
