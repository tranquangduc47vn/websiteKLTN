@extends('layouts.admin.app')

@section('content')
<nav aria-label="breadcrumb" class="admin-breadcrumb">
	<ol class="breadcrumb mb-0">
		<li class="breadcrumb-item"><a href="{{ url('admin') }}">Trang chủ</a></li>
		<li class="breadcrumb-item active">Nhà cung cấp</li>
	</ol>
</nav>
<div class="admin-page-header d-flex flex-wrap justify-content-between align-items-start gap-2">
	<div>
		<h1 class="admin-page-title">Nhà cung cấp</h1>
		<p class="admin-page-subtitle">Đối tác nhập hàng.</p>
	</div>
	@if ($canManage)
		<a class="btn btn-primary" href="{{ url('admin/suppliers/add') }}">Thêm NCC</a>
	@endif
</div>
<div class="admin-card">
	<div class="table-responsive adm-table-wrap">
		<table class="table admin-table table-hover mb-0">
			<thead><tr><th>Mã</th><th>Tên</th><th>Liên hệ</th><th>Điện thoại</th><th>Email</th><th>Trạng thái</th></tr></thead>
			<tbody>
				@forelse ($list as $row)
					@php $active = (string) $row->status === 'active'; @endphp
					<tr>
						<td><code>{{ $row->code }}</code></td>
						<td>{{ $row->name }}</td>
						<td>{{ $row->contact_name }}</td>
						<td>{{ $row->phone }}</td>
						<td>{{ $row->email }}</td>
						<td><span class="admin-status-badge {{ $active ? 'admin-status-3' : 'admin-status-4' }}">{{ $active ? 'Hoạt động' : 'Ngưng' }}</span></td>
					</tr>
				@empty
					<tr><td colspan="6" class="admin-empty">Chưa có nhà cung cấp.</td></tr>
				@endforelse
			</tbody>
		</table>
	</div>
</div>
@endsection
