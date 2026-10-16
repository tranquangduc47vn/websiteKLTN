@extends('layouts.admin.app')

@section('content')
@php $isEdit = $receipt !== null; @endphp
<h1 class="h4 mb-3">{{ $isEdit ? 'Sửa phiếu nháp' : 'Nhập kho' }}</h1>
<form method="post" action="{{ $isEdit ? url('admin/stock-receipts/edit/'.$receipt->id) : url('admin/stock-receipts/add') }}" class="admin-card">
	@csrf
	<input type="hidden" name="submit" value="1">
	<div class="mb-3">
		<label class="form-label" for="rc-supplier">Nhà cung cấp</label>
		<select name="supplier_id" id="rc-supplier" class="form-select" required>
			<option value="">— Chọn —</option>
			@foreach ($suppliers as $supplier)
				<option value="{{ $supplier->id }}" @selected($isEdit && (int) $receipt->supplier_id === (int) $supplier->id)>{{ $supplier->name }}</option>
			@endforeach
		</select>
	</div>
	<div class="mb-3">
		<label class="form-label" for="rc-note">Ghi chú</label>
		<textarea name="note" id="rc-note" class="form-control" rows="2">{{ $isEdit ? $receipt->note : '' }}</textarea>
	</div>
	<div class="mb-3">
		<label class="form-label" for="variant-q">Tìm biến thể</label>
		<input type="search" id="variant-q" class="form-control" placeholder="SKU hoặc tên">
		<div id="variant-results" class="small mt-2"></div>
	</div>
	<table class="table admin-table" id="receipt-lines">
		<thead><tr><th>Variant ID</th><th>Số lượng</th><th>Giá nhập</th></tr></thead>
		<tbody>
			@forelse ($items as $item)
				<tr>
					<td><input class="form-control" name="variant_id[]" value="{{ (int) $item->variant_id }}" required></td>
					<td><input class="form-control" name="qty[]" type="number" min="1" value="{{ (int) $item->qty }}" required></td>
					<td><input class="form-control" name="unit_cost[]" type="number" min="0" step="0.01" value="{{ $item->unit_cost }}" required></td>
				</tr>
			@empty
				<tr>
					<td><input class="form-control" name="variant_id[]" required></td>
					<td><input class="form-control" name="qty[]" type="number" min="1" value="1" required></td>
					<td><input class="form-control" name="unit_cost[]" type="number" min="0" step="0.01" required></td>
				</tr>
			@endforelse
		</tbody>
	</table>
	<button class="btn btn-primary" type="submit">{{ $isEdit ? 'Lưu phiếu nháp' : 'Nhập kho' }}</button>
	<a href="{{ url('admin/stock-receipts') }}">Quay lại</a>
</form>
<script>
document.getElementById('variant-q')?.addEventListener('change', function () {
	var q = this.value;
	fetch(@json(url('admin/receipts/search_variants')) + '?q=' + encodeURIComponent(q), {headers: {'Accept': 'application/json'}})
		.then(function (r) { return r.json(); })
		.then(function (data) {
			var box = document.getElementById('variant-results');
			box.innerHTML = '';
			(data.items || []).forEach(function (item) {
				var btn = document.createElement('button');
				btn.type = 'button';
				btn.className = 'btn btn-sm btn-outline-secondary me-1 mb-1';
				btn.textContent = item.sku + ' — ' + item.product_name;
				btn.addEventListener('click', function () {
					document.querySelector('[name="variant_id[]"]').value = item.id;
				});
				box.appendChild(btn);
			});
		});
});
</script>
@endsection
