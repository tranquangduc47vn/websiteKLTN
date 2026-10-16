@extends('layouts.admin.app')

@section('content')
@php
	$status = (string) $order->status;
	$labels = [
		'0' => 'Đang chờ xử lý',
		'1' => 'Đã xác nhận',
		'2' => 'Đang giao hàng',
		'3' => 'Thành công',
		'4' => 'Đã hủy / Hoàn',
	];
@endphp
<nav aria-label="breadcrumb" class="mb-3">
	<ol class="breadcrumb mb-0">
		<li class="breadcrumb-item"><a href="{{ url('admin/orders') }}">Đơn hàng</a></li>
		<li class="breadcrumb-item active">Chi tiết #{{ $order->id }}</li>
	</ol>
</nav>
<p><strong>Trạng thái:</strong> {{ $labels[$status] ?? 'Đang chờ' }}</p>
<div class="admin-card mb-3">
	<div class="admin-card-header">Thông tin khách hàng</div>
	<table class="table admin-table mb-0">
		<tbody>
			<tr><td>Họ và tên</td><td>{{ $order->user_name }}</td></tr>
			<tr><td>Email</td><td>{{ $order->user_email }}</td></tr>
			<tr><td>Số điện thoại</td><td>{{ $order->user_phone }}</td></tr>
			<tr><td>Địa chỉ</td><td>{{ $order->user_address }}</td></tr>
			<tr><td>Tin nhắn</td><td>{{ $order->message }}</td></tr>
			<tr><td>Ngày đặt</td><td>{{ date('H:i:s d/m/Y', (int) $order->created) }}</td></tr>
			<tr><td>Hình thức thanh toán</td><td>{{ ($order->payment ?? '') === 'Chuyển khoản' ? 'Chuyển khoản ngân hàng (VietQR)' : 'Thanh toán tiền mặt khi nhận hàng (COD)' }}</td></tr>
			@if (trim((string) $order->voucher_code) !== '' || (int) $order->discount_amount > 0)
				<tr>
					<td>Voucher</td>
					<td>
						@if (trim((string) $order->voucher_code) !== '')<code>{{ $order->voucher_code }}</code>@endif
						@if ((int) $order->discount_amount > 0)
							— Giảm {{ number_format((int) $order->discount_amount, 0, ',', '.') }} ₫
						@endif
					</td>
				</tr>
			@endif
			<tr><td>Thanh toán thực tế</td><td>{{ number_format((float) $order->amount, 0, ',', '.') }} ₫</td></tr>
		</tbody>
	</table>
</div>
<div class="admin-card mb-3">
	<div class="admin-card-header">Chi tiết sản phẩm đã mua</div>
	<table class="table admin-table">
		<thead>
			<tr><th>STT</th><th>Sản phẩm</th><th>Số lượng</th><th>Thành tiền</th>@if ($status === '0')<th></th>@endif</tr>
		</thead>
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
					@if ($status === '0')
						<td>
							<form method="post" action="{{ url('admin/orders/deldetail/'.$line->order_id) }}" class="d-inline">
								@csrf
								<button type="submit">Xóa sản phẩm này khỏi đơn</button>
							</form>
						</td>
					@endif
				</tr>
			@endforeach
		</tbody>
	</table>
</div>
<div>
	<h2 class="h6">Thao tác xử lý đơn hàng</h2>
	@if ($status === '0')
		<a class="btn btn-success" href="{{ url('admin/orders/accept/'.$order->id) }}">Xác nhận đơn hàng này</a>
		<form method="post" action="{{ url('admin/orders/cancel/'.$order->id) }}" class="d-inline">
			@csrf
			<input type="text" name="reason" placeholder="Nhập lý do hủy đơn hàng này">
			<button type="submit">Hủy bỏ đơn</button>
		</form>
	@elseif ($status === '1')
		<a class="btn btn-primary" href="{{ url('admin/orders/ship/'.$order->id) }}">Bàn giao cho Shipper giao hàng</a>
		<form method="post" action="{{ url('admin/orders/cancel/'.$order->id) }}" class="d-inline">
			@csrf
			<input type="text" name="reason" placeholder="Nhập lý do hủy đơn hàng này">
			<button type="submit">Hủy bỏ đơn</button>
		</form>
	@elseif ($status === '2')
		<a class="btn btn-success" href="{{ url('admin/orders/complete/'.$order->id) }}">Xác nhận: Khách đã nhận hàng thành công</a>
		<span>Đơn đã bàn giao shipper, không thể hủy</span>
	@elseif ($status === '3')
		<div class="alert alert-success">Đơn hàng hoàn tất: Đơn hàng đã được giao thành công và hệ thống đã ghi nhận doanh thu cùng số lượng sản phẩm bán ra.</div>
	@elseif ($status === '4')
		<div class="alert alert-warning">
			Đơn hàng này đã bị Hủy bỏ hoặc Hoàn trả.
			Lý do hệ thống ghi nhận: {{ $order->reason ?: 'Không có lý do cụ thể.' }}
		</div>
	@endif
	<a href="{{ url('admin/orders') }}">Quay lại danh sách</a>
</div>
@endsection
