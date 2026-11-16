@extends('layouts.admin.app')

@section('content')
@php
	$status = (string) $order->status;
	$labels = [
		'0' => 'Đang chờ xử lý',
		'pending' => 'Đang chờ xử lý',
		'1' => 'Đã xác nhận',
		'confirmed' => 'Đã xác nhận',
		'2' => 'Đang giao hàng',
		'packed' => 'Đang giao hàng',
		'3' => 'Thành công',
		'completed' => 'Thành công',
		'4' => 'Đã hủy / Hoàn',
		'cancelled' => 'Đã hủy / Hoàn',
	];
	$tones = [
		'0' => 'admin-status-0', 'pending' => 'admin-status-0',
		'1' => 'admin-status-1', 'confirmed' => 'admin-status-1',
		'2' => 'admin-status-2', 'packed' => 'admin-status-2',
		'3' => 'admin-status-3', 'completed' => 'admin-status-3',
		'4' => 'admin-status-4', 'cancelled' => 'admin-status-4',
	];
@endphp
<nav aria-label="breadcrumb" class="admin-breadcrumb">
	<ol class="breadcrumb mb-0">
		<li class="breadcrumb-item"><a href="{{ url('admin/orders') }}">Đơn hàng</a></li>
		<li class="breadcrumb-item active">Chi tiết #{{ $order->id }}</li>
	</ol>
</nav>
<div class="admin-page-header d-flex flex-wrap justify-content-between align-items-center gap-2">
	<div>
		<h1 class="admin-page-title">Chi tiết đơn #{{ $order->id }}</h1>
		<p class="admin-page-subtitle mb-0"><strong>Trạng thái:</strong> <span class="admin-status-badge {{ $tones[$status] ?? 'admin-status-0' }}">{{ $labels[$status] ?? 'Đang chờ' }}</span></p>
	</div>
</div>
<div class="admin-card mb-3">
	<div class="admin-card-header">Thông tin khách hàng</div>
	<div class="table-responsive adm-table-wrap">
		<table class="table admin-table adm-kv mb-0">
			<tbody>
				<tr><td>Họ và tên</td><td>{{ $order->user_name }}</td></tr>
				<tr><td>Email</td><td>{{ $order->user_email }}</td></tr>
				<tr><td>Số điện thoại</td><td>{{ $order->user_phone }}</td></tr>
				<tr><td>Địa chỉ</td><td>{{ $order->user_address }}</td></tr>
				<tr><td>Tin nhắn</td><td>{{ $order->message }}</td></tr>
				<tr><td>Ngày đặt</td><td>{{ $order->created_at ? $order->created_at->timezone('Asia/Ho_Chi_Minh')->format('H:i:s d/m/Y') : '' }}</td></tr>
				<tr><td>Hình thức thanh toán</td><td>{{ ($order->payment ?? '') === 'Chuyển khoản' ? 'Chuyển khoản ngân hàng (VietQR)' : 'Thanh toán tiền mặt khi nhận hàng (COD)' }}</td></tr>
				@if (($order->payment ?? '') === 'Chuyển khoản')
					@php
						$payState = (string) ($order->payment_status ?? '');
						$payLabel = match ($payState) {
							'paid' => 'Đã thanh toán',
							'review' => 'Cần đối soát — đơn đã hủy, chưa xác nhận thanh toán',
							'mismatch' => 'Cần đối soát — số tiền không khớp, chưa xác nhận thanh toán',
							default => 'Đang chờ',
						};
					@endphp
					<tr><td>Trạng thái thanh toán</td><td><span class="adm-pay adm-pay--transfer">{{ $payLabel }}</span></td></tr>
					<tr><td>Payment reference</td><td>{{ $order->payment_ref ?: '—' }}</td></tr>
					<tr><td>Mã giao dịch ngân hàng</td><td>{{ $order->payment_gateway_id ?: '—' }}</td></tr>
					<tr><td>Thời gian thanh toán</td><td>{{ ! empty($order->payment_paid_at) ? $order->payment_paid_at->format('H:i:s d/m/Y') : '—' }}</td></tr>
					@if ($payState === 'paid' && ! empty($order->payment_gateway_id))
						<tr><td>Nguồn xác nhận</td><td>Xác nhận bằng webhook</td></tr>
					@endif
				@endif
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
				<tr><td>Thanh toán thực tế</td><td class="adm-price"><strong>{{ number_format((float) $order->amount, 0, ',', '.') }} ₫</strong></td></tr>
			</tbody>
		</table>
	</div>
</div>
<div class="admin-card mb-3">
	<div class="admin-card-header">Chi tiết sản phẩm đã mua</div>
	<div class="table-responsive adm-table-wrap">
		<table class="table admin-table mb-0">
			<thead>
				<tr><th>STT</th><th>Sản phẩm</th><th>Số lượng</th><th>Thành tiền</th>@if ($status === 'pending')<th></th>@endif</tr>
			</thead>
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
						@if ($status === 'pending')
							<td>
								<form method="post" action="{{ url('admin/orders/deldetail/'.$line->order_id) }}" data-adm-confirm="Xóa sản phẩm này khỏi đơn?">
									@csrf
									<button class="btn btn-sm btn-outline-danger" type="submit">Xóa sản phẩm này khỏi đơn</button>
								</form>
							</td>
						@endif
					</tr>
				@endforeach
			</tbody>
		</table>
	</div>
</div>
<div class="admin-card">
	<div class="admin-card-header">Thao tác xử lý đơn hàng</div>
	<div class="admin-card-body adm-order-actions">
		@if ($status === 'pending')
			<a class="btn btn-success" href="{{ url('admin/orders/accept/'.$order->id) }}">Xác nhận đơn hàng này</a>
			<form method="post" action="{{ url('admin/orders/cancel/'.$order->id) }}" class="d-flex flex-wrap gap-2">
				@csrf
				<input class="form-control" type="text" name="reason" placeholder="Nhập lý do hủy đơn hàng này">
				<button class="btn btn-outline-danger" type="submit">Hủy bỏ đơn</button>
			</form>
		@elseif ($status === 'confirmed')
			<a class="btn btn-primary" href="{{ url('admin/orders/ship/'.$order->id) }}">Bàn giao cho Shipper giao hàng</a>
			<form method="post" action="{{ url('admin/orders/cancel/'.$order->id) }}" class="d-flex flex-wrap gap-2">
				@csrf
				<input class="form-control" type="text" name="reason" placeholder="Nhập lý do hủy đơn hàng này">
				<button class="btn btn-outline-danger" type="submit">Hủy bỏ đơn</button>
			</form>
		@elseif ($status === 'packed')
			<a class="btn btn-success" href="{{ url('admin/orders/complete/'.$order->id) }}">Xác nhận: Khách đã nhận hàng thành công</a>
			<span class="text-muted">Đơn đã bàn giao shipper, không thể hủy</span>
		@elseif ($status === 'completed')
			<div class="alert alert-success mb-0">Đơn hàng hoàn tất: Đơn hàng đã được giao thành công và hệ thống đã ghi nhận doanh thu cùng số lượng sản phẩm bán ra.</div>
		@elseif ($status === 'cancelled')
			<div class="alert alert-warning mb-0">
				Đơn hàng này đã bị Hủy bỏ hoặc Hoàn trả.
				Lý do hệ thống ghi nhận: {{ $order->reason ?: 'Không có lý do cụ thể.' }}
			</div>
		@endif
		<a class="btn btn-outline-secondary" href="{{ url('admin/orders') }}">Quay lại danh sách</a>
	</div>
</div>
@endsection
