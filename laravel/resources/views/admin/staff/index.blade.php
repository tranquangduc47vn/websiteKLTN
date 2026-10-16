@extends('layouts.admin.app')

@section('content')
<nav aria-label="breadcrumb" class="mb-3">
	<ol class="breadcrumb mb-0">
		<li class="breadcrumb-item"><a href="{{ url('admin') }}">Trang chủ</a></li>
		<li class="breadcrumb-item active">Quản trị viên</li>
	</ol>
</nav>
<div class="d-flex justify-content-end mb-3">
	<a href="{{ url('admin/admin/add') }}" class="btn btn-primary">Thêm mới</a>
</div>
<table class="table admin-table" id="adminListTable">
	<thead>
		<tr><th>ID</th><th>Họ tên</th><th>Email</th><th>Level</th><th></th></tr>
	</thead>
	<tbody>
		@foreach ($list as $row)
			<tr>
				<td>{{ $row->id }}</td>
				<td>{{ $row->name }}</td>
				<td>{{ $row->email }}</td>
				<td>{{ \App\Support\Admin\AdminPermission::roleLabel($row->level) }}</td>
				<td class="text-nowrap">
					<a class="btn btn-sm btn-outline-primary" href="{{ url('admin/admin/edit/' . $row->id) }}">Sửa</a>
					@if ($canDelete && (int) $row->level !== \App\Models\Admin::ROLE_ADMIN)
						<form method="post" action="{{ url('admin/admin/del/' . $row->id) }}" class="d-inline" onsubmit="return confirm('Bạn chắc chắn muốn xóa tài khoản này?');">
							@csrf
							<button class="btn btn-sm btn-outline-danger" type="submit">Xóa</button>
						</form>
					@endif
				</td>
			</tr>
		@endforeach
	</tbody>
</table>
@endsection
