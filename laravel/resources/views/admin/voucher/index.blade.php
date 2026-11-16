@extends('layouts.admin.app')

@section('content')
<meta name="csrf-token" content="{{ csrf_token() }}">
<nav aria-label="breadcrumb" class="admin-breadcrumb">
	<ol class="breadcrumb mb-0">
		<li class="breadcrumb-item"><a href="{{ url('admin') }}">Trang chủ</a></li>
		<li class="breadcrumb-item active">Voucher</li>
	</ol>
</nav>
<div class="admin-page-header d-flex flex-wrap justify-content-between align-items-start gap-2">
	<div>
		<h1 class="admin-page-title">Voucher</h1>
		<p class="admin-page-subtitle">Mã giảm giá theo hạng thành viên hoặc khách cụ thể.</p>
	</div>
	<a class="btn btn-primary" href="{{ url('admin/voucher/add') }}">Thêm voucher</a>
</div>
@include('admin.partials.export-toolbar', ['module' => 'voucher', 'query' => []])
<div class="admin-card">
	<div class="table-responsive adm-table-wrap">
		<table class="table admin-table table-hover mb-0">
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
						<td data-voucher-status="{{ $row->id }}">
							<span class="admin-status-badge {{ $active ? 'admin-status-3' : 'admin-status-4' }}">{{ $active ? 'Đang bật' : 'Tắt' }}</span>
						</td>
						<td>
							<div class="adm-actions">
								<form method="post" action="{{ url('admin/voucher/toggle_active/'.$row->id) }}">
									@csrf
									<button class="btn btn-sm btn-outline-secondary" type="submit">{{ $active ? 'Tắt' : 'Bật' }}</button>
								</form>
								<a class="btn btn-sm btn-outline-primary" href="{{ url('admin/voucher/edit/'.$row->id) }}">Sửa</a>
								<form method="post" action="{{ url('admin/voucher/delete/'.$row->id) }}" data-adm-confirm="Xóa voucher này?">
									@csrf
									<button class="btn btn-sm btn-outline-danger" type="submit">Xóa</button>
								</form>
							</div>
						</td>
					</tr>
				@empty
					<tr><td colspan="7" class="admin-empty">Chưa có voucher.</td></tr>
				@endforelse
			</tbody>
		</table>
	</div>
</div>
@endsection
