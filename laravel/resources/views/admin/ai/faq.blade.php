@extends('layouts.admin.app')

@section('content')
<nav aria-label="breadcrumb" class="mb-3">
	<ol class="breadcrumb mb-0">
		<li class="breadcrumb-item"><a href="{{ url('admin') }}">Trang chủ</a></li>
		<li class="breadcrumb-item active">FAQ</li>
	</ol>
</nav>
<div class="d-flex justify-content-between mb-3">
	<p class="mb-0">Câu trả lời có sẵn, khớp trước khi gọi AI.</p>
	<a class="btn btn-primary" href="{{ url('admin/ai-assistant/faq/add') }}">Thêm FAQ</a>
</div>
<table class="table admin-table">
	<thead><tr><th>Câu hỏi</th><th>Từ khóa</th><th>Trạng thái</th><th></th></tr></thead>
	<tbody>
		@forelse ($list as $faq)
			<tr>
				<td>{{ $faq->question }}</td>
				<td>{{ $faq->keywords }}</td>
				<td>{{ $faq->is_active ? 'Đang bật' : 'Tắt' }}</td>
				<td class="text-nowrap">
					<a class="btn btn-sm btn-outline-primary" href="{{ url('admin/ai-assistant/faq/edit/' . $faq->id) }}">Sửa</a>
					<form method="post" action="{{ url('admin/ai-assistant/faq/delete/' . $faq->id) }}" class="d-inline">
						@csrf
						<button class="btn btn-sm btn-outline-danger" type="submit">Xóa</button>
					</form>
				</td>
			</tr>
		@empty
			<tr><td colspan="4">Chưa có FAQ.</td></tr>
		@endforelse
	</tbody>
</table>
@endsection
