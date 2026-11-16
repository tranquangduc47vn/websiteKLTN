@extends('layouts.admin.app')

@section('content')
<nav aria-label="breadcrumb" class="admin-breadcrumb">
	<ol class="breadcrumb mb-0">
		<li class="breadcrumb-item"><a href="{{ url('admin/inventory') }}">Tồn kho</a></li>
		<li class="breadcrumb-item active">Kiểm kê</li>
	</ol>
</nav>
<div class="admin-page-header">
	<h1 class="admin-page-title">Kiểm kê / điều chỉnh tồn</h1>
	<p class="admin-page-subtitle"><strong>{{ $productName }}</strong></p>
</div>
<div class="admin-card">
	<div class="admin-card-body">
		<p class="mb-0">SKU: <code>{{ $variant->sku }}</code> — Màu: {{ $variant->color ?: '—' }} — Size: {{ $variant->size ?: '—' }} — Tồn hiện tại: <strong>{{ number_format((int) $variant->stock) }}</strong></p>
	</div>
</div>
@if (! $canAdjust)
	<div class="alert alert-info">Bạn chỉ được xem kiểm kê. Điều chỉnh số lượng tồn cần quyền Admin.</div>
	<a class="btn btn-outline-secondary" href="{{ url('admin/inventory') }}">Quay lại tồn kho</a>
@else
	<form method="post" action="{{ url('admin/inventory/adjust/'.$variant->id) }}" class="adm-form">
		@csrf
		<input type="hidden" name="variant_id" value="{{ (int) $variant->id }}">
		<div class="admin-card">
			<div class="admin-card-body">
				<div class="mb-3">
					<label class="form-label" for="new-qty">Tồn sau điều chỉnh</label>
					<input type="number" name="new_qty" id="new-qty" class="form-control" min="0" step="1" required value="{{ (int) $variant->stock }}">
				</div>
				<div class="mb-0">
					<label class="form-label" for="adjust-note">Lý do</label>
					<textarea name="note" id="adjust-note" class="form-control" rows="3" required></textarea>
				</div>
			</div>
		</div>
		<div class="d-flex flex-wrap gap-2 mb-4">
			<button type="submit" class="btn btn-primary">Lưu điều chỉnh</button>
			<a class="btn btn-outline-secondary" href="{{ url('admin/inventory') }}">Hủy</a>
		</div>
	</form>
@endif
@endsection
