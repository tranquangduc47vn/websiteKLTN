@extends('layouts.admin.app')

@section('content')
@php
	$isEdit = $receipt !== null;
	$receiptBase = url('admin/stock-receipts');
@endphp
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css">
<link rel="stylesheet" href="{{ url('assets/admin/css/stock-receipt-form.css') }}?v=2">

<nav aria-label="breadcrumb" class="admin-breadcrumb">
	<ol class="breadcrumb mb-0">
		<li class="breadcrumb-item"><a href="{{ $receiptBase }}">Phiếu nhập kho</a></li>
		<li class="breadcrumb-item active">{{ $isEdit ? 'Sửa phiếu nháp' : 'Nhập kho' }}</li>
	</ol>
</nav>

<div class="admin-page-header">
	<h1 class="admin-page-title">{{ $isEdit ? 'Sửa phiếu nháp' : 'Nhập kho' }}</h1>
	<p class="admin-page-subtitle text-muted mb-0">Chọn nhà cung cấp, tìm sản phẩm, thêm biến thể và xác nhận nhập kho.</p>
</div>

<form method="post" id="stock-receipt-form" class="rc-stock-form" novalidate
	action="{{ $isEdit ? $receiptBase.'/edit/'.$receipt->id : $receiptBase.'/add' }}">
	@csrf
	<input type="hidden" name="submit" value="1">

	<div class="admin-card rc-section mb-3">
		<div class="admin-card-body">
			<h2 class="rc-section-title">Thông tin phiếu</h2>
			<div class="row g-3">
				<div class="col-12 col-lg-6">
					<label class="form-label" for="supplier_id">Nhà cung cấp</label>
					<select name="supplier_id" id="supplier_id" class="form-select" required>
						<option value="">— Chọn nhà cung cấp —</option>
						@foreach ($suppliers as $supplier)
							<option value="{{ $supplier->id }}" @selected($isEdit && (int) $receipt->supplier_id === (int) $supplier->id)>{{ $supplier->name }}</option>
						@endforeach
					</select>
				</div>
				<div class="col-12">
					<label class="form-label" for="rc-note">Ghi chú</label>
					<textarea name="note" id="rc-note" class="form-control" rows="2" placeholder="Ghi chú phiếu nhập kho…">{{ $isEdit ? $receipt->note : '' }}</textarea>
				</div>
			</div>
		</div>
	</div>

	<div class="admin-card rc-section receipt-picker-card mb-3">
		<div class="admin-card-body">
			<h2 class="rc-section-title">Thêm sản phẩm nhập kho</h2>
			<div class="receipt-filter-bar row g-2 align-items-end mb-3">
				<div class="col-12 col-md-4 col-lg-3">
					<label class="form-label small" for="filter-catalog">Danh mục</label>
					<select id="filter-catalog" class="form-select form-select-sm">
						<option value="">— Tất cả —</option>
						@foreach ($catalogs as $parent)
							<optgroup label="{{ $parent->name }}">
								@foreach ($parent->sub ?? [] as $sub)
									<option value="{{ $sub->id }}">{{ $sub->name }}</option>
								@endforeach
							</optgroup>
						@endforeach
					</select>
				</div>
				<div class="col-12 col-md-5 col-lg-4">
					<label class="form-label small" for="filter-product">Tìm sản phẩm</label>
					<select id="filter-product" class="form-select form-select-sm" placeholder="Nhập tên sản phẩm hoặc SKU…"></select>
				</div>
				<div class="col-6 col-md-3 col-lg-2">
					<label class="form-label small" for="filter-color">Màu</label>
					<select id="filter-color" class="form-select form-select-sm"><option value="">— Tất cả —</option></select>
				</div>
				<div class="col-6 col-md-3 col-lg-2">
					<label class="form-label small" for="filter-size">Size</label>
					<select id="filter-size" class="form-select form-select-sm"><option value="">— Tất cả —</option></select>
				</div>
				<div class="col-12 col-lg-5">
					<label class="form-label small" for="filter-q">Tìm biến thể (SKU)</label>
					<div class="input-group input-group-sm">
						<span class="input-group-text"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i></span>
						<input type="search" id="filter-q" class="form-control" placeholder="SKU hoặc tên sản phẩm…" autocomplete="off">
					</div>
				</div>
			</div>

			<div id="rc-product-spotlight" class="rc-product-spotlight" hidden></div>

			<div id="recent-variants-wrap" class="mb-3" hidden>
				<h3 class="rc-subsection-title">Vừa nhập gần đây</h3>
				<div id="recent-variants-list" class="recent-variants-list"></div>
			</div>

			<div id="variant-picker" class="table-responsive adm-table-wrap">
				<p id="variant-picker-summary" class="variant-picker-meta small text-muted mb-2">Đang tải…</p>
				<table class="table table-sm admin-table mb-0">
					<thead>
						<tr>
							<th>SKU</th>
							<th>Sản phẩm</th>
							<th>Màu</th>
							<th>Size</th>
							<th class="text-end">Tồn</th>
							<th>Giá nhập</th>
							<th>SL</th>
							<th class="text-end">Tạm tính</th>
							<th></th>
						</tr>
					</thead>
					<tbody id="variant-picker-body"></tbody>
				</table>
				<div id="variant-picker-pagination" class="d-flex align-items-center gap-2 mt-2" hidden>
					<button type="button" class="btn btn-sm btn-outline-secondary" id="picker-prev">Trước</button>
					<span class="small text-muted" id="picker-page-label">1/1</span>
					<button type="button" class="btn btn-sm btn-outline-secondary" id="picker-next">Sau</button>
				</div>
			</div>
		</div>
	</div>

	<div class="admin-card rc-section mb-3">
		<div class="admin-card-body">
			<h2 class="rc-section-title">Danh sách sản phẩm nhập</h2>
			<div class="table-responsive adm-table-wrap">
				<table class="table admin-table mb-0" id="receipt-lines">
					<thead>
						<tr>
							<th>Sản phẩm / Biến thể</th>
							<th style="width:7rem">SL</th>
							<th style="width:9rem">Giá nhập</th>
							<th class="text-end" style="width:8rem">Thành tiền</th>
							<th style="width:3rem"></th>
						</tr>
					</thead>
					<tbody id="receipt-lines-body"></tbody>
					<tfoot>
						<tr class="rc-totals-row">
							<td class="text-muted small" id="receipt-line-count">0 dòng</td>
							<td class="fw-semibold text-end" colspan="1"><span id="receipt-total-qty">0</span></td>
							<td class="text-muted small">Tổng tiền nhập</td>
							<td class="text-end fw-semibold"><span id="receipt-total">0</span> ₫</td>
							<td></td>
						</tr>
					</tfoot>
				</table>
			</div>
		</div>
	</div>

	<div class="d-flex flex-wrap gap-2 mb-4">
		<button class="btn btn-primary btn-lg px-4" type="submit">{{ $isEdit ? 'Lưu phiếu nháp' : 'Xác nhận nhập kho' }}</button>
		<a class="btn btn-outline-secondary btn-lg" href="{{ $receiptBase }}">Quay lại</a>
	</div>
</form>

<script>
window.STOCK_RECEIPT_FORM = {
	initialLines: @json($initialLines ?? []),
	urls: {
		search_variants: @json($receiptBase.'/search_variants'),
		filter_products: @json($receiptBase.'/filter_products'),
		filter_options: @json($receiptBase.'/filter_options'),
		recent_variants: @json($receiptBase.'/recent_variants'),
	}
};
</script>
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
<script src="{{ url('assets/admin/js/stock-receipt-form.js') }}?v=5"></script>
@endsection