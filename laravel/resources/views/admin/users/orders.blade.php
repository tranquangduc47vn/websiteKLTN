@extends('layouts.admin.app')

@section('content')
<nav aria-label="breadcrumb" class="mb-3">
	<ol class="breadcrumb mb-0">
		<li class="breadcrumb-item"><a href="{{ url('admin/users') }}">Khách hàng</a></li>
		<li class="breadcrumb-item active">Đơn của khách</li>
	</ol>
</nav>
@if ($customer)
	<p>{{ $customer->name }} — {{ $customer->email }}</p>
@endif
<table class="table admin-table">
	<thead><tr><th>ID</th><th>Tên</th><th>Số ĐT</th><th>Tiền</th><th>Trạng thái</th><th></th></tr></thead>
	<tbody>
		@forelse ($orders as $row)
			<tr>
				<td>{{ $row->id }}</td>
				<td>{{ $row->user_name }}</td>
				<td>{{ $row->user_phone }}</td>
				<td>{{ number_format((float) $row->amount, 0, ',', '.') }}</td>
				<td>{{ (string) $row->status }}</td>
				<td><a href="{{ url('admin/users/detail/'.$row->id) }}">Chi tiết</a></td>
			</tr>
		@empty
			<tr><td colspan="6">Không có đơn.</td></tr>
		@endforelse
	</tbody>
</table>
<a href="{{ url('admin/users') }}">Quay lại danh sách khách hàng</a>
@endsection
