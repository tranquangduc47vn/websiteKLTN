@extends('layouts.admin.app')

@section('content')
<nav aria-label="breadcrumb" class="admin-breadcrumb">
	<ol class="breadcrumb mb-0">
		<li class="breadcrumb-item"><a href="{{ url('admin') }}">Trang chủ</a></li>
		<li class="breadcrumb-item active">FAQ</li>
	</ol>
</nav>
<div class="admin-page-header d-flex flex-wrap justify-content-between align-items-start gap-2">
	<div>
		<h1 class="admin-page-title">FAQ</h1>
		<p class="admin-page-subtitle">Câu trả lời có sẵn, khớp trước khi gọi AI.</p>
	</div>
	<a class="btn btn-primary" href="{{ url('admin/ai-assistant/faq/add') }}">Thêm FAQ</a>
</div>
<div class="admin-card">
	<div class="table-responsive adm-table-wrap">
		<table class="table admin-table table-hover mb-0">
			<thead><tr><th>Câu hỏi</th><th>Từ khóa</th><th>Trạng thái</th><th></th></tr></thead>
			<tbody>
				@forelse ($list as $faq)
					<tr>
						<td>{{ $faq->question }}</td>
						<td>{{ $faq->keywords }}</td>
						<td><span class="admin-status-badge {{ $faq->is_active ? 'admin-status-3' : 'admin-status-4' }}">{{ $faq->is_active ? 'Đang bật' : 'Tắt' }}</span></td>
						<td>
							<div class="adm-actions">
								<a class="btn btn-sm btn-outline-primary" href="{{ url('admin/ai-assistant/faq/edit/' . $faq->id) }}">Sửa</a>
								<form method="post" action="{{ url('admin/ai-assistant/faq/delete/' . $faq->id) }}" data-adm-confirm="Xóa FAQ này?">
									@csrf
									<button class="btn btn-sm btn-outline-danger" type="submit">Xóa</button>
								</form>
							</div>
						</td>
					</tr>
				@empty
					<tr><td colspan="4" class="admin-empty">Chưa có FAQ.</td></tr>
				@endforelse
			</tbody>
		</table>
	</div>
</div>
@endsection
