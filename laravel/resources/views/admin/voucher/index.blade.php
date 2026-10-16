@extends('layouts.admin.app')

@section('content')
<meta name="csrf-token" content="{{ csrf_token() }}">
<nav aria-label="breadcrumb" class="mb-3">
	<ol class="breadcrumb mb-0">
		<li class="breadcrumb-item"><a href="{{ url('admin') }}">Trang chủ</a></li>
		<li class="breadcrumb-item active">Voucher</li>
	</ol>
</nav>
<div class="d-flex justify-content-between mb-3">
	<p class="mb-0">Mã giảm giá theo hạng thành viên hoặc khách cụ thể.</p>
	<a class="btn btn-primary" href="{{ url('admin/voucher/add') }}">Thêm voucher</a>
</div>
@include('admin.partials.export-toolbar', ['module' => 'voucher', 'query' => []])
<table class="table admin-table">
	<thead>
		<tr><th>Mã</th><th>Tên</th><th>Giảm</th><th>Hạng tối thiểu</th><th>Đã dùng</th><th>Trạng thái</th><th></th></tr>
	</thead>
	<tbody>
		@forelse ($list as $row)
			@php
				$disc = $row->discount_type === 'percent'
					? ((int) $row->discount_value . '%')
					: (number_format((int) $row->discount_value, 0, ',', '.') . ' ₫');
				$used = (int) $row->used_count;
				$limit = (int) $row->usage_limit;
				$active = (int) $row->is_active === 1;
			@endphp
			<tr>
				<td><code>{{ $row->code }}</code></td>
				<td>{{ $row->name }}</td>
				<td>{{ $disc }}</td>
				<td>{{ \App\Support\Storefront\Loyalty::tierLabel($row->tier_min) }}</td>
				<td>{{ $limit > 0 ? ($used . ' / ' . $limit) : $used }}</td>
				<td data-voucher-status="{{ $row->id }}">{{ $active ? 'Đang bật' : 'Tắt' }}</td>
				<td>
					<form method="post" action="{{ url('admin/voucher/toggle_active/'.$row->id) }}" class="d-inline">
						@csrf
						<button type="submit">{{ $active ? 'Tắt' : 'Bật' }}</button>
					</form>
					<a href="{{ url('admin/voucher/edit/'.$row->id) }}">Sửa</a>
					<form method="post" action="{{ url('admin/voucher/delete/'.$row->id) }}" class="d-inline" onsubmit="return confirm('Xóa voucher này?');">
						@csrf
						<button type="submit">Xóa</button>
					</form>
				</td>
			</tr>
		@empty
			<tr><td colspan="7">Chưa có voucher.</td></tr>
		@endforelse
	</tbody>
</table>
@endsection
