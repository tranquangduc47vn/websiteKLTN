@extends('layouts.admin.app')

@section('content')
<nav aria-label="breadcrumb" class="mb-3">
	<ol class="breadcrumb mb-0">
		<li class="breadcrumb-item"><a href="{{ url('admin/ai-assistant/faq') }}">FAQ</a></li>
		<li class="breadcrumb-item active">{{ $faq ? 'Sửa' : 'Thêm' }}</li>
	</ol>
</nav>
<form method="post" action="{{ url($faq ? 'admin/ai-assistant/faq/edit/' . $faq->id : 'admin/ai-assistant/faq/add') }}">
	@csrf
	<div class="mb-3">
		<label class="form-label">Câu hỏi</label>
		<input class="form-control" name="question" value="{{ old('question', $faq->question ?? '') }}" required>
	</div>
	<div class="mb-3">
		<label class="form-label">Câu trả lời</label>
		<textarea class="form-control" name="answer" rows="4" required>{{ old('answer', $faq->answer ?? '') }}</textarea>
	</div>
	<div class="mb-3">
		<label class="form-label">Từ khóa</label>
		<input class="form-control" name="keywords" value="{{ old('keywords', $faq->keywords ?? '') }}">
	</div>
	<div class="mb-3">
		<label class="form-label">Nhóm</label>
		<input class="form-control" name="category" value="{{ old('category', $faq->category ?? '') }}">
	</div>
	<div class="mb-3">
		<label class="form-label">Thứ tự</label>
		<input class="form-control" type="number" name="sort_order" value="{{ old('sort_order', $faq->sort_order ?? 0) }}">
	</div>
	<div class="mb-3">
		<label class="form-check">
			<input type="checkbox" name="is_active" value="1" class="form-check-input" @checked(old('is_active', $faq->is_active ?? 1))>
			<span class="form-check-label">Đang bật</span>
		</label>
	</div>
	<button class="btn btn-primary" type="submit" name="submit" value="1">Lưu</button>
</form>
@endsection
