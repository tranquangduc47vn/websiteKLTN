@extends('layouts.admin.app')

@section('content')
<nav aria-label="breadcrumb" class="admin-breadcrumb">
	<ol class="breadcrumb mb-0">
		<li class="breadcrumb-item"><a href="{{ url('admin/users') }}">Khách hàng</a></li>
		<li class="breadcrumb-item active">Đơn của khách</li>
	</ol>
</nav>
<div class="admin-page-header">
	<h1 class="admin-page-title">Đơn của khách</h1>
	@if ($customer)
		<p class="admin-page-subtitle">{{ $customer->name }} — {{ $customer->email }}</p>
	@endif
</div>
<div class="admin-card">
	<div class="table-responsive adm-table-wrap">
		<table class="table admin-table table-hover mb-0">
			<thead><tr><th>ID</th><th>Tên</th><th>Số ĐT</th><th>Tiền</th><th>Trạng thái</th><th></th></tr></thead>
			<tbody>
				@forelse ($orders as $row)
					<tr>
						<td>{{ $row->id }}</td>
						<td>{{ $row->user_name }}</td>
						<td>{{ $row->user_phone }}</td>
						<td class="adm-price">{{ number_format((float) $row->amount, 0, ',', '.') }}</td>
						<td>{{ (string) $row->status }}</td>
						<td><a class="btn btn-sm btn-outline-primary" href="{{ url('admin/users/detail/'.$row->id) }}">Chi tiết</a></td>
					</tr>
				@empty
					<tr><td colspan="6" class="admin-empty">Không có đơn.</td></tr>
				@endforelse
			</tbody>
		</table>
	</div>
</div>
<a class="btn btn-outline-secondary" href="{{ url('admin/users') }}">Quay lại danh sách khách hàng</a>
@endsection
