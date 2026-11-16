@extends('layouts.admin.app')

@section('content')
@php
	$labels = ['0' => 'Đang chờ xử lý', '1' => 'Đã xác nhận', '2' => 'Đang giao hàng', '3' => 'Thành công', '4' => 'Đã hủy / Hoàn'];
	$status = (string) $order->status;
@endphp
<nav aria-label="breadcrumb" class="admin-breadcrumb">
	<ol class="breadcrumb mb-0">
		<li class="breadcrumb-item"><a href="{{ url('admin/users/order/'.$order->user_id) }}">Đơn của user</a></li>
		<li class="breadcrumb-item active">Chi tiết đơn</li>
	</ol>
</nav>
<div class="admin-page-header">
	<h1 class="admin-page-title">Chi tiết đơn</h1>
	<p class="admin-page-subtitle"><strong>Trạng thái:</strong> {{ $labels[$status] ?? $order->status }}</p>
</div>
<div class="admin-card">
	<div class="table-responsive adm-table-wrap">
		<table class="table admin-table adm-kv mb-0">
			<tbody>
				<tr><td>Họ và tên</td><td>{{ $order->user_name }}</td></tr>
				<tr><td>Email</td><td>{{ $order->user_email }}</td></tr>
				<tr><td>Số điện thoại</td><td>{{ $order->user_phone }}</td></tr>
				<tr><td>Địa chỉ</td><td>{{ $order->user_address }}</td></tr>
				<tr><td>Thanh toán</td><td>{{ in_array($order->payment, ['Chuyển khoản', 'VietQR'], true) ? 'Chuyển khoản' : 'COD' }}</td></tr>
				<tr><td>Tổng</td><td class="adm-price">{{ number_format((float) $order->amount, 0, ',', '.') }} ₫</td></tr>
			</tbody>
		</table>
	</div>
</div>
<div class="admin-card">
	<div class="admin-card-header">Sản phẩm trong đơn</div>
	<div class="table-responsive adm-table-wrap">
		<table class="table admin-table mb-0">
			<thead><tr><th>STT</th><th>Sản phẩm</th><th>SL</th><th>Thành tiền</th></tr></thead>
			<tbody>
				@foreach ($lines as $index => $line)
					<tr>
						<td>{{ $index + 1 }}</td>
						<td>
							{{ $line->name ?: ('Sản phẩm #'.$line->product_id) }}
							@if ($line->size || $line->color)
								<div class="text-muted small">Size: {{ $line->size ?: 'Mặc định' }} — Màu: {{ $line->color ?: 'Mặc định' }}</div>
							@endif
						</td>
						<td>{{ (int) $line->qty }}</td>
						<td class="adm-price">{{ number_format((float) $line->amount, 0, ',', '.') }} VNĐ</td>
					</tr>
				@endforeach
			</tbody>
		</table>
	</div>
</div>
<p>Chỉ xem đối chiếu — đổi trạng thái tại Quản lý đơn đặt hàng.</p>
<a class="btn btn-outline-secondary" href="{{ url('admin/users/order/'.$order->user_id) }}">Quay lại</a>
@endsection
