@extends('layouts.admin.app')

@section('content')
<nav aria-label="breadcrumb" class="admin-breadcrumb">
	<ol class="breadcrumb mb-0">
		<li class="breadcrumb-item"><a href="{{ url('admin') }}">Trang chủ</a></li>
		<li class="breadcrumb-item active">Duyệt đánh giá sản phẩm</li>
	</ol>
</nav>
<div class="admin-page-header">
	<h1 class="admin-page-title">Duyệt đánh giá sản phẩm</h1>
</div>
<form class="admin-card mb-3" method="get" action="{{ url('admin/product-reviews') }}">
	<div class="admin-card-body">
		<div class="row g-2 align-items-end">
			<div class="col-md-5">
				<label class="form-label" for="rv-q">Từ khóa</label>
				<input id="rv-q" type="search" name="q" class="form-control" placeholder="Tìm SP, khách, nội dung..." value="{{ $keyword }}">
			</div>
			<div class="col-md-4">
				<label class="form-label" for="rv-status">Trạng thái</label>
				<select id="rv-status" name="status" class="form-select">
					<option value="pending" @selected($filter_status === 'pending')>Chờ duyệt ({{ (int) $counts['pending'] }})</option>
					<option value="approved" @selected($filter_status === 'approved')>Đã duyệt ({{ (int) $counts['approved'] }})</option>
					<option value="rejected" @selected($filter_status === 'rejected')>Từ chối ({{ (int) $counts['rejected'] }})</option>
					<option value="all" @selected($filter_status === 'all')>Tất cả</option>
				</select>
			</div>
			<div class="col-md-2"><button type="submit" class="btn btn-primary">Lọc</button></div>
		</div>
	</div>
</form>
<div class="admin-card">
	<div class="table-responsive adm-table-wrap">
		<table class="table admin-table table-hover align-middle mb-0">
			<thead>
				<tr>
					<th>Thời gian</th><th>Sản phẩm</th><th>Khách</th><th>Sao</th><th>Nội dung</th><th>Trạng thái</th><th class="text-end">Thao tác</th>
				</tr>
			</thead>
			<tbody>
				@forelse ($list as $row)
					@php
						$status = (string) $row->status;
						$label = match ($status) {
							'approved', '1' => 'Đã duyệt',
							'rejected', '2' => 'Từ chối',
							default => 'Chờ duyệt',
						};
						$tone = match ($status) {
							'approved', '1' => 'admin-status-3',
							'rejected', '2' => 'admin-status-4',
							default => 'admin-status-0',
						};
						$createdAt = $row->created_at ?? $row->created ?? null;
					@endphp
					<tr>
						<td>{{ $createdAt ? date('d/m/Y H:i', strtotime((string) $createdAt)) : '' }}</td>
						<td><a href="{{ $productUrl($row) }}" target="_blank" rel="noopener">{{ $row->product_name ?: ('SP #' . (int) $row->product_id) }}</a></td>
						<td>{{ $row->user_name }}</td>
						<td>{{ (int) $row->stars }}/5</td>
						<td style="max-width: 360px;"><div class="text-break">{!! nl2br(e($row->comment)) !!}</div></td>
						<td><span class="admin-status-badge {{ $tone }}">{{ $label }}</span></td>
						<td class="text-end">
							<div class="adm-actions justify-content-end">
								@if ($status !== 'approved')
									<form method="post" action="{{ url('admin/product-reviews/approve/' . (int) $row->id) }}">
										@csrf
										<button type="submit" class="btn btn-success btn-sm">Duyệt</button>
									</form>
								@endif
								@if ($status !== 'rejected')
									<form method="post" action="{{ url('admin/product-reviews/reject/' . (int) $row->id) }}">
										@csrf
										<button type="submit" class="btn btn-outline-warning btn-sm">Từ chối</button>
									</form>
								@endif
								<form method="post" action="{{ url('admin/product-reviews/delete/' . (int) $row->id) }}" data-adm-confirm="Xóa đánh giá này?">
									@csrf
									<button type="submit" class="btn btn-outline-danger btn-sm">Xóa</button>
								</form>
							</div>
						</td>
					</tr>
				@empty
					<tr><td colspan="7" class="admin-empty">Không có đánh giá nào.</td></tr>
				@endforelse
			</tbody>
		</table>
	</div>
</div>
@endsection
