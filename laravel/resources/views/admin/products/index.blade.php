@extends('layouts.admin.app')

@section('content')
@php
	$query = array_filter([
		'name' => $filters['name'] ?? '',
		'catalog_id' => $filters['catalog_id'] ?? '',
		'status' => $filters['status'] ?? '',
	], fn ($value) => $value !== '' && $value !== null);
	$qs = $query === [] ? '' : ('?' . http_build_query($query));
	$total = (int) $page['total'];
	$offset = (int) $page['offset'];
	$per = \App\Services\ProductAdminService::PER_PAGE;
	$exportQuery = array_filter([
		'name' => $filters['name'] ?? '',
		'catalog_id' => $filters['catalog_id'] ?? '',
		'offset' => $offset,
		'per_page' => $per,
	], fn ($value) => $value !== '' && $value !== null);
@endphp
<nav aria-label="breadcrumb" class="mb-3">
	<ol class="breadcrumb mb-0">
		<li class="breadcrumb-item"><a href="{{ url('admin') }}">Trang chủ</a></li>
		<li class="breadcrumb-item active">Sản phẩm</li>
	</ol>
</nav>
<form class="row g-2 align-items-end mb-3" method="get" action="{{ url('admin/products/index') }}">
	<div class="col-md-4">
		<label for="search_name" class="form-label">Tên sản phẩm</label>
		<input type="search" name="name" id="search_name" class="form-control" value="{{ $filters['name'] }}">
	</div>
	<div class="col-md-3">
		<label for="search_catalog" class="form-label">Danh mục</label>
		<select name="catalog_id" id="search_catalog" class="form-select">
			<option value="">-- Tất cả danh mục --</option>
			@foreach ($catalog as $group)
				@if (! empty($group->sub))
					<option value="{{ $group->id }}" disabled>{{ $group->name }}</option>
					@foreach ($group->sub as $child)
						<option value="{{ $child->id }}" @selected((string) $filters['catalog_id'] === (string) $child->id)>{{ $child->name }}</option>
					@endforeach
				@else
					<option value="{{ $group->id }}" @selected((string) $filters['catalog_id'] === (string) $group->id)>{{ $group->name }}</option>
				@endif
			@endforeach
		</select>
	</div>
	<div class="col-md-2">
		<label for="search_status" class="form-label">Trạng thái bán</label>
		<select name="status" id="search_status" class="form-select">
			<option value="" @selected($filters['status'] === '')>Tất cả</option>
			<option value="1" @selected($filters['status'] === '1')>Còn kinh doanh</option>
			<option value="0" @selected($filters['status'] === '0')>Ngừng kinh doanh</option>
		</select>
	</div>
	<div class="col-md-3">
		<button class="btn btn-primary" type="submit">Tìm kiếm</button>
		<a class="btn btn-outline-secondary" href="{{ url('admin/products') }}">Xóa bộ lọc</a>
	</div>
</form>
@include('admin.partials.export-toolbar', ['module' => 'products', 'query' => $exportQuery])
<div class="d-flex justify-content-between align-items-center mb-2">
	<strong>Quản lý sản phẩm (Tìm thấy: {{ $total }})</strong>
	<a class="btn btn-success btn-sm" href="{{ url('admin/products/add') }}">Thêm sản phẩm</a>
</div>
<form method="post" action="{{ url('admin/products/bulk_del') }}" id="bulk-delete-form">
	@csrf
	<div class="table-responsive">
		<table class="table admin-table">
			<thead>
				<tr>
					@if ($canBulkDelete)<th></th>@endif
					<th>ID</th>
					<th>Tên</th>
					<th>Danh mục</th>
					<th>Giá</th>
					<th>Tồn</th>
					<th>Trạng thái</th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				@forelse ($page['rows'] as $row)
					<tr>
						@if ($canBulkDelete)
							<td><input type="checkbox" name="checkbox[]" class="case" value="{{ $row->id }}"></td>
						@endif
						<td>{{ $row->id }}</td>
						<td>{{ $row->name }}</td>
						<td>{{ $row->namecatalog }}</td>
						<td>
							@if ((int) $row->discount > 0)
								<del>{{ number_format((float) $row->price, 0, ',', '.') }} đ</del>
								{{ number_format((float) $row->price - (int) $row->discount, 0, ',', '.') }} đ
							@else
								{{ number_format((float) $row->price, 0, ',', '.') }} đ
							@endif
						</td>
						<td>{{ (int) $row->quantity }}</td>
						<td>{{ (int) $row->status === 1 ? 'Còn kinh doanh' : 'Ngừng kinh doanh' }}</td>
						<td class="text-end">
							<a class="btn btn-sm btn-outline-primary" href="{{ url('admin/products/edit/'.$row->id) }}">Sửa</a>
							@if ($canDelete)
								<button type="button" class="btn btn-sm btn-outline-danger remove" id="{{ $row->id }}">Xóa</button>
							@endif
						</td>
					</tr>
				@empty
					<tr><td colspan="8">Không tìm thấy sản phẩm nào phù hợp với bộ lọc!</td></tr>
				@endforelse
			</tbody>
		</table>
	</div>
	@if ($canBulkDelete)
		<button class="btn btn-danger btn-sm" type="submit" id="submit-del">Xóa mục đã chọn</button>
	@endif
</form>
@if ($total > $per)
	<nav class="mt-3" aria-label="Phân trang sản phẩm">
		<ul class="pagination">
			@for ($i = 0; $i < $total; $i += $per)
				<li class="page-item {{ $i === $offset ? 'active' : '' }}">
					<a class="page-link" href="{{ url('admin/products/index/'.$i).$qs }}">{{ (int) ($i / $per) + 1 }}</a>
				</li>
			@endfor
		</ul>
	</nav>
@endif
<div id="message"></div>
<script src="{{ url('assets/admin/js/jquery-3.1.1.js') }}"></script>
<script>
document.querySelectorAll('.remove').forEach(function (button) {
	button.addEventListener('click', function () {
		if (!confirm('Bạn có chắc chắn muốn xóa sản phẩm này? Sau khi xóa dữ liệu hình ảnh sẽ mất hoàn toàn.')) {
			return;
		}
		var row = button.closest('tr');
		var body = new URLSearchParams(window.appendCsrf({ id: button.id }));
		fetch('{{ url('admin/products/del') }}', {
			method: 'POST',
			headers: {
				'X-Requested-With': 'XMLHttpRequest',
				'Accept': 'application/json',
				'Content-Type': 'application/x-www-form-urlencoded'
			},
			body: body
		}).then(function (res) { return res.json(); }).then(function (response) {
			if (typeof window.applyCsrfResponse === 'function') {
				window.applyCsrfResponse(response);
			}
			if (response && (response.ok || response.status === 'success')) {
				row.remove();
				document.getElementById('message').innerHTML = '<div class="alert alert-success">Xóa sản phẩm thành công!</div>';
			} else {
				document.getElementById('message').innerHTML = '<div class="alert alert-danger">Lỗi: Bạn không có quyền xóa hoặc sản phẩm không tồn tại!</div>';
			}
		});
	});
});
</script>
@endsection
