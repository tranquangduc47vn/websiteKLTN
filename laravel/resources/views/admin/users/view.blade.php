@extends('layouts.admin.app')

@section('content')
<nav aria-label="breadcrumb" class="admin-breadcrumb">
	<ol class="breadcrumb mb-0">
		<li class="breadcrumb-item"><a href="{{ url('admin/users') }}">Khách hàng</a></li>
		<li class="breadcrumb-item active">{{ $customer->name }}</li>
	</ol>
</nav>
<div class="admin-page-header d-flex flex-wrap justify-content-between align-items-start gap-2">
	<div>
		<h1 class="admin-page-title">{{ $customer->name }}</h1>
		<p class="admin-page-subtitle">{{ $customer->email }}</p>
	</div>
	<div class="d-flex flex-wrap gap-2">
		<a class="btn btn-outline-secondary btn-sm" href="{{ url('admin/users') }}">Quay lại danh sách</a>
		<a class="btn btn-outline-primary btn-sm" href="{{ url('admin/users/order/'.$customer->id) }}">Xem đơn hàng</a>
	</div>
</div>
<div class="admin-card">
	<div class="admin-card-header">Thông tin tài khoản</div>
	<div class="table-responsive adm-table-wrap">
		<table class="table admin-table adm-kv mb-0">
			<tbody>
				<tr><td>ID</td><td>{{ $customer->id }}</td></tr>
				<tr><td>Họ và tên</td><td>{{ $customer->name }}</td></tr>
				<tr><td>Email</td><td>{{ $customer->email }}</td></tr>
				<tr><td>Số điện thoại</td><td>{{ $customer->phone }}</td></tr>
				<tr><td>Địa chỉ chính</td><td>{{ $customer->address }}</td></tr>
				<tr><td>Ngày đăng ký</td><td>{{ $customer->created ? date('d/m/Y', (int) $customer->created) : '—' }}</td></tr>
				<tr><td>Hạng thành viên</td><td><span class="admin-status-badge admin-status-1">{{ $profile->tier_label ?? 'Thành viên' }}</span></td></tr>
				<tr><td>Điểm tích lũy</td><td>{{ number_format((int) ($profile->points ?? 0)) }} điểm</td></tr>
				<tr><td>Tổng chi tiêu</td><td>{{ number_format((int) ($profile->lifetime_spend ?? 0)) }} ₫</td></tr>
				<tr><td>Đơn hoàn thành</td><td>{{ (int) ($profile->completed_orders ?? 0) }} đơn</td></tr>
				<tr><td>Tổng đơn hàng</td><td>{{ (int) $orderCount }} đơn</td></tr>
			</tbody>
		</table>
	</div>
</div>
@if ($phones->isNotEmpty())
	<div class="admin-card">
		<div class="admin-card-header">Sổ điện thoại</div>
		<ul class="list-group list-group-flush">
			@foreach ($phones as $phone)
				<li class="list-group-item">{{ $phone->phone_number }} @if ((int) $phone->is_default === 1)<span class="admin-status-badge admin-status-3">Mặc định</span>@endif</li>
			@endforeach
		</ul>
	</div>
@endif
@if ($addresses->isNotEmpty())
	<div class="admin-card">
		<div class="admin-card-header">Sổ địa chỉ đã lưu</div>
		<div class="table-responsive adm-table-wrap">
			<table class="table admin-table mb-0">
				<thead><tr><th>Ghi chú</th><th>Địa chỉ</th><th>Mặc định</th></tr></thead>
				<tbody>
					@foreach ($addresses as $addr)
						<tr>
							<td>{{ $addr->address_note }}</td>
							<td>{{ $addr->address_line }}</td>
							<td>{{ (int) $addr->is_default === 1 ? 'Mặc định' : '—' }}</td>
						</tr>
					@endforeach
				</tbody>
			</table>
		</div>
	</div>
@endif
@endsection
