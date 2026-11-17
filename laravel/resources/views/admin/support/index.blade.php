@extends('layouts.admin.app')

@php
	use App\Support\Admin\SupportChatPresenter;
	$statusFilterOptions = ['all' => 'Tất cả'] + SupportChatPresenter::statusLabels();
@endphp

@section('content')
<link rel="stylesheet" href="{{ url('assets/admin/css/support-chat-inbox.css') }}?v=1">
<nav aria-label="breadcrumb" class="admin-breadcrumb">
	<ol class="breadcrumb mb-0">
		<li class="breadcrumb-item"><a href="{{ url('admin') }}">Trang chủ</a></li>
		<li class="breadcrumb-item active">Hỗ trợ khách hàng</li>
	</ol>
</nav>
<div class="admin-page-header d-flex flex-wrap justify-content-between align-items-start gap-2">
	<div>
		<h1 class="admin-page-title">Hỗ trợ khách hàng</h1>
		<p class="admin-page-subtitle">Quản lý hội thoại AI và nhân viên trong một luồng chat thống nhất.</p>
	</div>
	@if ($isAdmin)
		<form method="post" action="{{ url('admin/support-chat/purge') }}" data-adm-confirm="Xóa TOÀN BỘ lịch sử hội thoại và tin nhắn? Thao tác không thể hoàn tác.">
			@csrf
			<button type="submit" class="btn btn-outline-danger btn-sm">Xóa toàn bộ lịch sử</button>
		</form>
	@endif
</div>
<div class="row g-3 mb-4">
	<div class="col-6 col-md-3"><div class="admin-card h-100"><div class="admin-card-body py-3"><div class="small text-muted">Chờ nhân viên hỗ trợ</div><div class="h4 mb-0">{{ (int) $stats['waiting'] }}</div></div></div></div>
	<div class="col-6 col-md-3"><div class="admin-card h-100"><div class="admin-card-body py-3"><div class="small text-muted">Nhân viên đang hỗ trợ</div><div class="h4 mb-0">{{ (int) $stats['active_staff'] }}</div></div></div></div>
	<div class="col-6 col-md-3"><div class="admin-card h-100"><div class="admin-card-body py-3"><div class="small text-muted">AI đang tư vấn</div><div class="h4 mb-0">{{ (int) $stats['ai_active'] }}</div></div></div></div>
	<div class="col-6 col-md-3"><div class="admin-card h-100"><div class="admin-card-body py-3"><div class="small text-muted">Đã đóng</div><div class="h4 mb-0">{{ (int) $stats['closed'] }}</div></div></div></div>
</div>
<form class="admin-card mb-3" method="get" action="{{ url('admin/support-chat') }}">
	<div class="admin-card-body">
		<div class="row g-2 align-items-end">
			<div class="col-md-3">
				<label class="form-label" for="sc-status">Trạng thái</label>
				<select id="sc-status" name="status" class="form-select">
					@foreach ($statusFilterOptions as $value => $label)
						<option value="{{ $value }}" @selected($filter_status === $value)>{{ $label }}</option>
					@endforeach
				</select>
			</div>
			<div class="col-md-4">
				<label class="form-label" for="sc-q">Tìm</label>
				<input id="sc-q" class="form-control" name="q" value="{{ $keyword }}" placeholder="Mã, tên khách, email hoặc nhân viên">
			</div>
			<div class="col-md-3">
				<label class="form-label d-block">&nbsp;</label>
				<div class="form-check">
					<input type="checkbox" class="form-check-input" name="unread" value="1" id="sc-unread" @checked($unread_filter ?? false)>
					<label class="form-check-label" for="sc-unread">Chỉ hội thoại chưa đọc (nhân viên)</label>
				</div>
			</div>
			<div class="col-md-2"><button class="btn btn-primary w-100" type="submit">Lọc</button></div>
		</div>
	</div>
</form>
<div class="admin-card adm-support-inbox">
	<div class="table-responsive adm-table-wrap">
		<table class="table admin-table table-hover align-middle mb-0">
			<thead>
				<tr>
					<th>Mã</th>
					<th>Khách hàng</th>
					<th>Trạng thái</th>
					<th>Nhân viên</th>
					<th>Hoạt động gần nhất</th>
					<th>Tin nhắn cuối</th>
					<th>Chưa đọc</th>
					<th class="text-end">Thao tác</th>
				</tr>
			</thead>
			<tbody>
				@forelse ($list as $row)
					@php
						$activity = SupportChatPresenter::lastActivityAt($row);
						$preview = SupportChatPresenter::messagePreview($row->lastMessage);
						$unreadLabel = SupportChatPresenter::unreadStaffLabel($row);
					@endphp
					<tr>
						<td><code>#{{ $row->id }}</code></td>
						<td class="fw-medium">{{ SupportChatPresenter::customerLabel($row) }}</td>
						<td>
							<span class="adm-sc-badge {{ SupportChatPresenter::statusBadgeClass($row->status) }}">
								{{ SupportChatPresenter::statusLabel($row->status) }}
							</span>
						</td>
						<td>{{ SupportChatPresenter::staffLabel($row) }}</td>
						<td class="text-nowrap small text-muted">
							{{ $activity ? $activity->format('d/m/Y H:i') : '—' }}
						</td>
						<td>
							<span class="adm-sc-last-msg" title="{{ e($preview) }}">{{ $preview }}</span>
						</td>
						<td @class(['adm-sc-unread' => (bool) $row->unread_staff])>{{ $unreadLabel }}</td>
						<td class="text-end">
							<div class="adm-support-actions">
								<a class="btn btn-sm btn-outline-primary" href="{{ url('admin/support-chat/chat/' . $row->id) }}">Mở chat</a>
								<form method="post" action="{{ url('admin/support-chat/delete/' . $row->id) }}" data-adm-confirm="Xóa hội thoại #{{ $row->id }}? Không thể hoàn tác.">
									@csrf
									<button class="btn btn-sm btn-outline-danger" type="submit">Xóa</button>
								</form>
							</div>
						</td>
					</tr>
				@empty
					<tr><td colspan="8" class="text-center text-muted py-4">Không có hội thoại.</td></tr>
				@endforelse
			</tbody>
		</table>
	</div>
</div>
@endsection