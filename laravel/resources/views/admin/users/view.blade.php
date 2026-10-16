@extends('layouts.admin.app')

@section('content')
<nav aria-label="breadcrumb" class="mb-3">
	<ol class="breadcrumb mb-0">
		<li class="breadcrumb-item"><a href="{{ url('admin/users') }}">Khách hàng</a></li>
		<li class="breadcrumb-item active">{{ $customer->name }}</li>
	</ol>
</nav>
<table class="table admin-table">
	<tbody>
		<tr><td>ID</td><td>{{ $customer->id }}</td></tr>
		<tr><td>Họ và tên</td><td>{{ $customer->name }}</td></tr>
		<tr><td>Email</td><td>{{ $customer->email }}</td></tr>
		<tr><td>Số điện thoại</td><td>{{ $customer->phone }}</td></tr>
		<tr><td>Địa chỉ chính</td><td>{{ $customer->address }}</td></tr>
		<tr><td>Ngày đăng ký</td><td>{{ $customer->created ? date('d/m/Y', (int) $customer->created) : '—' }}</td></tr>
		<tr><td>Hạng thành viên</td><td>{{ $profile->tier_label ?? 'Thành viên' }}</td></tr>
		<tr><td>Điểm tích lũy</td><td>{{ number_format((int) ($profile->points ?? 0)) }} điểm</td></tr>
		<tr><td>Tổng chi tiêu</td><td>{{ number_format((int) ($profile->lifetime_spend ?? 0)) }} ₫</td></tr>
		<tr><td>Đơn hoàn thành</td><td>{{ (int) ($profile->completed_orders ?? 0) }} đơn</td></tr>
		<tr><td>Tổng đơn hàng</td><td>{{ (int) $orderCount }} đơn</td></tr>
	</tbody>
</table>
@if ($phones->isNotEmpty())
	<h2 class="h6">Sổ điện thoại</h2>
	<ul>
		@foreach ($phones as $phone)
			<li>{{ $phone->phone_number }} @if ((int) $phone->is_default === 1)(Mặc định)@endif</li>
		@endforeach
	</ul>
@endif
@if ($addresses->isNotEmpty())
	<h2 class="h6">Sổ địa chỉ đã lưu</h2>
	<table class="table admin-table">
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
@endif
<a href="{{ url('admin/users') }}">Quay lại danh sách</a>
<a href="{{ url('admin/users/order/'.$customer->id) }}">Xem đơn hàng</a>
@endsection
