@extends('layouts.admin.app')

@section('content')
<nav aria-label="breadcrumb" class="mb-3">
	<ol class="breadcrumb mb-0">
		<li class="breadcrumb-item"><a href="{{ url('admin') }}">Trang chủ</a></li>
		<li class="breadcrumb-item active">Hỗ trợ khách hàng</li>
	</ol>
</nav>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
	<p class="mb-0">Quản lý hội thoại AI và nhân viên trong một luồng chat thống nhất.</p>
	@if ($isAdmin)
		<form method="post" action="{{ url('admin/support-chat/purge') }}" onsubmit="return confirm('Xóa TOÀN BỘ lịch sử hội thoại và tin nhắn? Thao tác không thể hoàn tác.');">
			@csrf
			<button type="submit" class="btn btn-outline-danger btn-sm">Xóa toàn bộ lịch sử</button>
		</form>
	@endif
</div>
<div class="row g-3 mb-4">
	<div class="col-6 col-md-3"><div class="admin-card h-100"><div class="admin-card-body py-3"><div class="small text-muted">Chờ nhân viên</div><div class="h4 mb-0">{{ (int) $stats['waiting'] }}</div></div></div></div>
	<div class="col-6 col-md-3"><div class="admin-card h-100"><div class="admin-card-body py-3"><div class="small text-muted">Nhân viên đang xử lý</div><div class="h4 mb-0">{{ (int) $stats['active_staff'] }}</div></div></div></div>
	<div class="col-6 col-md-3"><div class="admin-card h-100"><div class="admin-card-body py-3"><div class="small text-muted">AI đang trả lời</div><div class="h4 mb-0">{{ (int) $stats['ai_active'] }}</div></div></div></div>
	<div class="col-6 col-md-3"><div class="admin-card h-100"><div class="admin-card-body py-3"><div class="small text-muted">Đã đóng</div><div class="h4 mb-0">{{ (int) $stats['closed'] }}</div></div></div></div>
</div>
<form class="row g-2 mb-3" method="get" action="{{ url('admin/support-chat') }}">
	<div class="col-md-3">
		<select name="status" class="form-select">
			@foreach (['all' => 'Tất cả', 'waiting_staff' => 'Chờ nhân viên', 'staff_joined' => 'Nhân viên', 'ai_active' => 'AI', 'closed' => 'Đã đóng'] as $value => $label)
				<option value="{{ $value }}" @selected($filter_status === $value)>{{ $label }}</option>
			@endforeach
		</select>
	</div>
	<div class="col-md-4"><input class="form-control" name="q" value="{{ $keyword }}" placeholder="Tìm theo mã hoặc nhân viên"></div>
	<div class="col-md-2"><button class="btn btn-primary" type="submit">Lọc</button></div>
</form>
<table class="table admin-table">
	<thead><tr><th>#</th><th>Khách</th><th>Trạng thái</th><th>Nhân viên</th><th></th></tr></thead>
	<tbody>
		@forelse ($list as $row)
			<tr>
				<td>{{ $row->id }}</td>
				<td>{{ $row->user_id > 0 ? 'User #' . $row->user_id : 'Khách' }}</td>
				<td>{{ $row->status }}</td>
				<td>{{ $row->staff_name }}</td>
				<td class="text-nowrap">
					<a class="btn btn-sm btn-outline-primary" href="{{ url('admin/support-chat/chat/' . $row->id) }}">Mở chat</a>
					<form method="post" action="{{ url('admin/support-chat/delete/' . $row->id) }}" class="d-inline">
						@csrf
						<button class="btn btn-sm btn-outline-danger" type="submit">Xóa</button>
					</form>
				</td>
			</tr>
		@empty
			<tr><td colspan="5">Không có hội thoại.</td></tr>
		@endforelse
	</tbody>
</table>
@endsection
