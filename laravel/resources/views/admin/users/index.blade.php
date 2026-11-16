@extends('layouts.admin.app')

@section('content')
<nav aria-label="breadcrumb" class="admin-breadcrumb">
	<ol class="breadcrumb mb-0">
		<li class="breadcrumb-item"><a href="{{ url('admin') }}">Trang chủ</a></li>
		<li class="breadcrumb-item active">Khách hàng</li>
	</ol>
</nav>
<div class="admin-page-header">
	<h1 class="admin-page-title">Khách hàng</h1>
	<p class="admin-page-subtitle">Tìm theo tên, email, số điện thoại hoặc địa chỉ. Danh sách theo dữ liệu backend hiện có, không phân trang.</p>
</div>
<div class="admin-card">
	<div class="admin-card-body">
		<form method="get" action="{{ url('admin/users') }}" class="d-flex flex-wrap gap-2 adm-filter">
			<input type="search" name="q" value="{{ $search }}" class="form-control" placeholder="Tìm khách hàng..." aria-label="Tìm khách hàng">
			<button class="btn btn-outline-primary" type="submit">Tìm</button>
		</form>
	</div>
</div>
@include('admin.partials.export-toolbar', ['module' => 'customers', 'query' => []])
<div class="admin-card">
	<div class="table-responsive adm-table-wrap">
		<table class="table admin-table table-hover mb-0" id="userListTable">
			<thead>
				<tr><th>ID</th><th>Họ tên</th><th>Email</th><th>Địa chỉ</th><th></th></tr>
			</thead>
			<tbody>
				@forelse ($users as $row)
					<tr>
						<td>{{ $row->id }}</td>
						<td>{{ $row->name }}</td>
						<td>{{ $row->email }}</td>
						<td>{{ $row->address }}</td>
						<td>
							<div class="adm-actions">
								<a class="btn btn-sm btn-outline-primary" href="{{ url('admin/users/view/'.$row->id) }}">Xem thông tin</a>
								<a class="btn btn-sm btn-outline-secondary" href="{{ url('admin/users/order/'.$row->id) }}">Đơn hàng</a>
								<form method="post" action="{{ url('admin/users/del/'.$row->id) }}" data-adm-confirm="Bạn chắc chắn muốn xóa">
									@csrf
									<button class="btn btn-sm btn-outline-danger" type="submit">Xóa</button>
								</form>
							</div>
						</td>
					</tr>
				@empty
					<tr><td colspan="5" class="admin-empty">Chưa có khách hàng.</td></tr>
				@endforelse
			</tbody>
		</table>
	</div>
</div>
@endsection
