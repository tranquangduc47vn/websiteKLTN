@extends('layouts.admin.app')

@section('content')
@php
	$labels = ['0' => 'Đang chờ xử lý', '1' => 'Đã xác nhận', '2' => 'Đang giao hàng', '3' => 'Thành công', '4' => 'Đã hủy / Hoàn'];
@endphp
<nav aria-label="breadcrumb" class="mb-3">
	<ol class="breadcrumb mb-0">
		<li class="breadcrumb-item"><a href="{{ url('admin/users/order/'.$order->user_id) }}">Đơn của user</a></li>
		<li class="breadcrumb-item active">Chi tiết đơn</li>
	</ol>
</nav>
<p><strong>Trạng thái:</strong> {{ $labels[(string) $order->status] ?? $order->status }}</p>
<table class="table admin-table">
	<tbody>
		<tr><td>Họ và tên</td><td>{{ $order->user_name }}</td></tr>
		<tr><td>Email</td><td>{{ $order->user_email }}</td></tr>
		<tr><td>Số điện thoại</td><td>{{ $order->user_phone }}</td></tr>
		<tr><td>Địa chỉ</td><td>{{ $order->user_address }}</td></tr>
		<tr><td>Thanh toán</td><td>{{ in_array($order->payment, ['Chuyển khoản', 'VietQR'], true) ? 'Chuyển khoản' : 'COD' }}</td></tr>
		<tr><td>Tổng</td><td>{{ number_format((float) $order->amount, 0, ',', '.') }} ₫</td></tr>
	</tbody>
</table>
<table class="table admin-table">
	<thead><tr><th>STT</th><th>Sản phẩm</th><th>SL</th><th>Thành tiền</th></tr></thead>
	<tbody>
		@foreach ($lines as $index => $line)
			<tr>
				<td>{{ $index + 1 }}</td>
				<td>
					{{ $line->name ?: ('Sản phẩm #'.$line->product_id) }}
					@if ($line->size || $line->color)
						<div>Size: {{ $line->size ?: 'Mặc định' }} — Màu: {{ $line->color ?: 'Mặc định' }}</div>
					@endif
				</td>
				<td>{{ (int) $line->qty }}</td>
				<td>{{ number_format((float) $line->amount, 0, ',', '.') }} VNĐ</td>
			</tr>
		@endforeach
	</tbody>
</table>
<p>Chỉ xem đối chiếu — đổi trạng thái tại Quản lý đơn đặt hàng.</p>
<a href="{{ url('admin/users/order/'.$order->user_id) }}">Quay lại</a>
@endsection
