@extends('layouts.admin.app')

@section('content')
<nav aria-label="breadcrumb" class="mb-3">
	<ol class="breadcrumb mb-0">
		<li class="breadcrumb-item"><a href="{{ url('admin') }}">Trang chủ</a></li>
		<li class="breadcrumb-item active">Khách hàng</li>
	</ol>
</nav>
<form method="get" action="{{ url('admin/users') }}" class="d-flex gap-2 mb-3">
	<input type="search" name="q" value="{{ $search }}" class="form-control" placeholder="Tìm khách hàng..." aria-label="Tìm khách hàng">
	<button class="btn btn-outline-primary" type="submit">Tìm</button>
</form>
@include('admin.partials.export-toolbar', ['module' => 'customers', 'query' => []])
<div class="table-responsive">
	<table class="table admin-table" id="userListTable">
		<thead>
			<tr><th>ID</th><th>Họ tên</th><th>Email</th><th>Địa chỉ</th><th></th></tr>
		</thead>
		<tbody>
			@foreach ($users as $row)
				<tr>
					<td>{{ $row->id }}</td>
					<td>{{ $row->name }}</td>
					<td>{{ $row->email }}</td>
					<td>{{ $row->address }}</td>
					<td class="text-end">
						<a href="{{ url('admin/users/view/'.$row->id) }}">Xem thông tin</a>
						<a href="{{ url('admin/users/order/'.$row->id) }}">Đơn hàng</a>
						<form method="post" action="{{ url('admin/users/del/'.$row->id) }}" class="d-inline" onsubmit="return confirm('Bạn chắc chắn muốn xóa');">
							@csrf
							<button type="submit">Xóa</button>
						</form>
					</td>
				</tr>
			@endforeach
		</tbody>
	</table>
</div>
@endsection
