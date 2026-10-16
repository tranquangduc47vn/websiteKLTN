@extends('layouts.admin.app')

@section('content')
<nav aria-label="breadcrumb" class="mb-3">
	<ol class="breadcrumb mb-0">
		<li class="breadcrumb-item"><a href="{{ url('admin') }}">Trang chủ</a></li>
		<li class="breadcrumb-item active">Danh mục</li>
	</ol>
</nav>
<div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
	<form method="get" action="{{ url('admin/catalog') }}" class="d-flex gap-2">
		<input type="search" name="q" value="{{ $search }}" class="form-control" placeholder="Tìm danh mục..." aria-label="Tìm danh mục">
		<button class="btn btn-outline-primary" type="submit">Tìm</button>
	</form>
	<a href="{{ url('admin/catalog/add') }}" class="btn btn-primary">Thêm mới</a>
</div>
<div class="admin-card">
	<div class="table-responsive">
		<table class="table admin-table" id="catalogListTable">
			<thead>
				<tr>
					<th>ID</th>
					<th>Tên danh mục</th>
					<th>Parent ID</th>
					<th>Thứ tự</th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				@forelse ($list as $row)
					<tr>
						<td>{{ $row->id }}</td>
						<td>{{ $row->name }}</td>
						<td>{{ $row->parent_id }}</td>
						<td>{{ $row->sort_order }}</td>
						<td class="text-end">
							<a class="btn btn-sm btn-outline-primary" href="{{ url('admin/catalog/edit/'.$row->id) }}">Sửa</a>
							@if ($canDelete)
								<form method="post" action="{{ url('admin/catalog/del/'.$row->id) }}" class="d-inline" onsubmit="return confirm('Bạn chắc chắn muốn xóa danh mục này? Việc này có thể ảnh hưởng đến các sản phẩm bên trong!');">
									@csrf
									<button class="btn btn-sm btn-outline-danger" type="submit">Xóa</button>
								</form>
							@endif
						</td>
					</tr>
				@empty
					<tr><td colspan="5">Không có danh mục.</td></tr>
				@endforelse
			</tbody>
		</table>
	</div>
</div>
@endsection
