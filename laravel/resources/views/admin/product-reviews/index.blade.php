@extends('layouts.admin.app')

@section('content')
<nav aria-label="breadcrumb" class="mb-3">
	<ol class="breadcrumb mb-0">
		<li class="breadcrumb-item"><a href="{{ url('admin') }}">Trang chủ</a></li>
		<li class="breadcrumb-item active">Duyệt đánh giá sản phẩm</li>
	</ol>
</nav>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
	<h1 class="h4 mb-0">Duyệt đánh giá sản phẩm</h1>
	<form class="d-flex flex-wrap gap-2" method="get" action="{{ url('admin/product-reviews') }}">
		<input type="search" name="q" class="form-control form-control-sm" placeholder="Tìm SP, khách, nội dung..." value="{{ $keyword }}">
		<select name="status" class="form-select form-select-sm">
			<option value="pending" @selected($filter_status === 'pending')>Chờ duyệt ({{ (int) $counts['pending'] }})</option>
			<option value="approved" @selected($filter_status === 'approved')>Đã duyệt ({{ (int) $counts['approved'] }})</option>
			<option value="rejected" @selected($filter_status === 'rejected')>Từ chối ({{ (int) $counts['rejected'] }})</option>
			<option value="all" @selected($filter_status === 'all')>Tất cả</option>
		</select>
		<button type="submit" class="btn btn-outline-secondary btn-sm">Lọc</button>
	</form>
</div>
<div class="card">
	<div class="table-responsive">
		<table class="table table-sm align-middle mb-0">
			<thead>
				<tr>
					<th>Thời gian</th><th>Sản phẩm</th><th>Khách</th><th>Sao</th><th>Nội dung</th><th>Trạng thái</th><th class="text-end">Thao tác</th>
				</tr>
			</thead>
			<tbody>
				@forelse ($list as $row)
					@php
						$status = (int) $row->status;
						$label = match ($status) { 1 => 'Đã duyệt', 2 => 'Từ chối', default => 'Chờ duyệt' };
					@endphp
					<tr>
						<td>{{ date('d/m/Y H:i', (int) $row->created) }}</td>
						<td><a href="{{ $productUrl($row) }}" target="_blank" rel="noopener">{{ $row->product_name ?: ('SP #' . (int) $row->product_id) }}</a></td>
						<td>{{ $row->user_name }}</td>
						<td>{{ (int) $row->stars }}/5</td>
						<td style="max-width: 360px;"><div class="text-break">{!! nl2br(e($row->comment)) !!}</div></td>
						<td>{{ $label }}</td>
						<td class="text-end">
							<div class="d-inline-flex flex-wrap gap-1 justify-content-end">
								@if ($status !== 1)
									<form method="post" action="{{ url('admin/product-reviews/approve/' . (int) $row->id) }}">
										@csrf
										<button type="submit" class="btn btn-success btn-sm">Duyệt</button>
									</form>
								@endif
								@if ($status !== 2)
									<form method="post" action="{{ url('admin/product-reviews/reject/' . (int) $row->id) }}">
										@csrf
										<button type="submit" class="btn btn-outline-warning btn-sm">Từ chối</button>
									</form>
								@endif
								<form method="post" action="{{ url('admin/product-reviews/delete/' . (int) $row->id) }}" onsubmit="return confirm('Xóa đánh giá này?');">
									@csrf
									<button type="submit" class="btn btn-outline-danger btn-sm">Xóa</button>
								</form>
							</div>
						</td>
					</tr>
				@empty
					<tr><td colspan="7" class="text-center text-muted py-4">Không có đánh giá nào.</td></tr>
				@endforelse
			</tbody>
		</table>
	</div>
</div>
@endsection
