@extends('layouts.admin.app')

@section('content')
<nav aria-label="breadcrumb" class="admin-breadcrumb">
	<ol class="breadcrumb mb-0">
		<li class="breadcrumb-item"><a href="{{ url('admin') }}">Trang chủ</a></li>
		<li class="breadcrumb-item active">Quản trị viên</li>
	</ol>
</nav>
<div class="admin-page-header d-flex flex-wrap justify-content-between align-items-start gap-2">
	<div>
		<h1 class="admin-page-title">Nhân viên</h1>
		<p class="admin-page-subtitle">Tài khoản quản trị và phân quyền.</p>
	</div>
	<a href="{{ url('admin/admin/add') }}" class="btn btn-primary">Thêm mới</a>
</div>
<div class="admin-card">
	<div class="table-responsive adm-table-wrap">
		<table class="table admin-table table-hover mb-0" id="adminListTable">
			<thead>
				<tr><th>ID</th><th>Họ tên</th><th>Email</th><th>Level</th><th></th></tr>
			</thead>
			<tbody>
				@forelse ($list as $row)
					<tr>
						<td>{{ $row->id }}</td>
						<td>{{ $row->name }}</td>
						<td>{{ $row->email }}</td>
						<td><span class="admin-status-badge admin-status-1">{{ \App\Support\Admin\AdminPermission::roleLabel($row->level) }}</span></td>
						<td>
							<div class="adm-actions">
								<a class="btn btn-sm btn-outline-primary" href="{{ url('admin/admin/edit/' . $row->id) }}">Sửa</a>
								@if ($canDelete && (int) $row->level !== \App\Models\Admin::ROLE_ADMIN)
									<form method="post" action="{{ url('admin/admin/del/' . $row->id) }}" data-adm-confirm="Bạn chắc chắn muốn xóa tài khoản này?">
										@csrf
										<button class="btn btn-sm btn-outline-danger" type="submit">Xóa</button>
									</form>
								@endif
							</div>
						</td>
					</tr>
				@empty
					<tr><td colspan="5" class="admin-empty">Chưa có tài khoản.</td></tr>
				@endforelse
			</tbody>
		</table>
	</div>
</div>
@endsection
