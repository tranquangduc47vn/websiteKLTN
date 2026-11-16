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
	$colspan = $canBulkDelete ? 8 : 7;
@endphp
<nav aria-label="breadcrumb" class="admin-breadcrumb">
	<ol class="breadcrumb mb-0">
		<li class="breadcrumb-item"><a href="{{ url('admin') }}">Trang chủ</a></li>
		<li class="breadcrumb-item active">Sản phẩm</li>
	</ol>
</nav>
<div class="admin-page-header d-flex flex-wrap justify-content-between align-items-start gap-2">
	<div>
		<h1 class="admin-page-title">Quản lý sản phẩm</h1>
		<p class="admin-page-subtitle">Tìm thấy: {{ $total }}</p>
	</div>
	<a class="btn btn-primary" href="{{ url('admin/products/add') }}"><i class="fa-solid fa-plus me-1"></i>Thêm sản phẩm</a>
</div>
<div class="admin-card">
	<div class="admin-card-body">
		<form class="row g-2 align-items-end adm-filter" method="get" action="{{ url('admin/products/index') }}">
			<div class="col-12 col-md-4">
				<label for="search_name" class="form-label">Tên sản phẩm</label>
				<input type="search" name="name" id="search_name" class="form-control" value="{{ $filters['name'] }}">
			</div>
			<div class="col-12 col-md-3">
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
			<div class="col-12 col-md-2">
				<label for="search_status" class="form-label">Trạng thái bán</label>
				<select name="status" id="search_status" class="form-select">
					<option value="" @selected($filters['status'] === '')>Tất cả</option>
					<option value="1" @selected($filters['status'] === '1')>Còn kinh doanh</option>
					<option value="0" @selected($filters['status'] === '0')>Ngừng kinh doanh</option>
				</select>
			</div>
			<div class="col-12 col-md-3 d-flex flex-wrap gap-2">
				<button class="btn btn-primary" type="submit">Tìm kiếm</button>
				<a class="btn btn-outline-secondary" href="{{ url('admin/products') }}">Xóa bộ lọc</a>
			</div>
		</form>
	</div>
</div>
@include('admin.partials.export-toolbar', ['module' => 'products', 'query' => $exportQuery])
<form method="post" action="{{ url('admin/products/bulk_del') }}" id="bulk-delete-form" data-adm-confirm="Xóa các sản phẩm đã chọn? Hình ảnh của các sản phẩm đó sẽ bị xóa.">
	@csrf
	<div class="admin-card">
		<div class="table-responsive adm-table-wrap">
			<table class="table admin-table table-hover mb-0">
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
						@php $selling = (string) $row->status === 'active'; @endphp
						<tr class="{{ $selling ? '' : 'admin-product-row--inactive' }}">
							@if ($canBulkDelete)
								<td><input type="checkbox" name="checkbox[]" class="case form-check-input" value="{{ $row->id }}" aria-label="Chọn {{ $row->name }}"></td>
							@endif
							<td>{{ $row->id }}</td>
							<td>
								<div class="admin-product-mini">
									@if (! empty($row->image_link))
										<img src="{{ \App\Support\Storefront\Product::imageUrl($row->image_link) }}" alt="">
									@else
										<span class="adm-thumb-fallback" aria-hidden="true"><i class="fa-solid fa-image"></i></span>
									@endif
									<span>{{ $row->name }}</span>
								</div>
							</td>
							<td>{{ $row->namecatalog }}</td>
							<td class="adm-price">
								@if ((int) $row->discount > 0)
									<del>{{ number_format((float) $row->price, 0, ',', '.') }} đ</del>
									{{ number_format((float) $row->price - (int) $row->discount, 0, ',', '.') }} đ
								@else
									{{ number_format((float) $row->price, 0, ',', '.') }} đ
								@endif
							</td>
							<td>{{ (int) $row->quantity }}</td>
							<td>
								<span class="admin-status-badge {{ $selling ? 'admin-status-3' : 'admin-status-4' }}">{{ $selling ? 'Còn kinh doanh' : 'Ngừng kinh doanh' }}</span>
							</td>
							<td>
								<div class="adm-actions">
									<a class="btn btn-sm btn-outline-primary" href="{{ url('admin/products/edit/'.$row->id) }}">Sửa</a>
									@if ($canDelete)
										<button type="button" class="btn btn-sm btn-outline-danger remove" id="{{ $row->id }}">Xóa</button>
									@endif
								</div>
							</td>
						</tr>
					@empty
						<tr><td colspan="{{ $colspan }}" class="admin-empty">Không tìm thấy sản phẩm nào phù hợp với bộ lọc!</td></tr>
					@endforelse
				</tbody>
			</table>
		</div>
		@if ($canBulkDelete)
			<div class="admin-card-body">
				<button class="btn btn-danger btn-sm" type="submit" id="submit-del">Xóa mục đã chọn</button>
			</div>
		@endif
	</div>
</form>
@if ($total > $per)
	<nav class="admin-table-footer" aria-label="Phân trang sản phẩm">
		<ul class="pagination mb-0">
			@for ($i = 0; $i < $total; $i += $per)
				<li class="page-item {{ $i === $offset ? 'active' : '' }}">
					<a class="page-link" href="{{ url('admin/products/index/'.$i).$qs }}">{{ (int) ($i / $per) + 1 }}</a>
				</li>
			@endfor
		</ul>
	</nav>
@endif
<div id="message"></div>
<script>
document.querySelectorAll('.remove').forEach(function (button) {
	button.addEventListener('click', function () {
		var ask = window.admConfirm || function (message, done) {
			if (window.confirm(message)) done();
		};
		ask('Bạn có chắc chắn muốn xóa sản phẩm này? Sau khi xóa dữ liệu hình ảnh sẽ mất hoàn toàn.', function () {
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
});
</script>
@endsection
