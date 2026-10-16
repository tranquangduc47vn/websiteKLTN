@extends('layouts.admin.app')

@section('content')
<nav aria-label="breadcrumb" class="mb-3">
	<ol class="breadcrumb mb-0">
		<li class="breadcrumb-item"><a href="{{ url('admin') }}">Trang chủ</a></li>
		<li class="breadcrumb-item active">Nhà cung cấp</li>
	</ol>
</nav>
<div class="d-flex justify-content-between mb-3">
	<h1 class="h4 mb-0">Nhà cung cấp</h1>
	@if ($canManage)
		<a class="btn btn-primary btn-sm" href="{{ url('admin/suppliers/add') }}">Thêm NCC</a>
	@endif
</div>
<table class="table admin-table">
	<thead><tr><th>Mã</th><th>Tên</th><th>Liên hệ</th><th>Điện thoại</th><th>Email</th><th>Trạng thái</th></tr></thead>
	<tbody>
		@foreach ($list as $row)
			<tr>
				<td>{{ $row->code }}</td>
				<td>{{ $row->name }}</td>
				<td>{{ $row->contact_name }}</td>
				<td>{{ $row->phone }}</td>
				<td>{{ $row->email }}</td>
				<td>{{ (int) $row->status === 1 ? 'Hoạt động' : 'Ngưng' }}</td>
			</tr>
		@endforeach
	</tbody>
</table>
@endsection
