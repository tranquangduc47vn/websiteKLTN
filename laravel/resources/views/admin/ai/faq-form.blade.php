@extends('layouts.admin.app')

@section('content')
<nav aria-label="breadcrumb" class="admin-breadcrumb">
	<ol class="breadcrumb mb-0">
		<li class="breadcrumb-item"><a href="{{ url('admin/ai-assistant/faq') }}">FAQ</a></li>
		<li class="breadcrumb-item active">{{ $faq ? 'Sửa' : 'Thêm' }}</li>
	</ol>
</nav>
<div class="admin-page-header">
	<h1 class="admin-page-title">{{ $faq ? 'Sửa FAQ' : 'Thêm FAQ' }}</h1>
</div>
<form method="post" action="{{ url($faq ? 'admin/ai-assistant/faq/edit/' . $faq->id : 'admin/ai-assistant/faq/add') }}" class="adm-form">
	@csrf
	<div class="admin-card"><div class="admin-card-body">
		<div class="mb-3">
			<label class="form-label">Câu hỏi</label>
			<input class="form-control @error('question') is-invalid @enderror" name="question" value="{{ old('question', $faq->question ?? '') }}" required>
			@error('question')<div class="invalid-feedback">{{ $message }}</div>@enderror
		</div>
		<div class="mb-3">
			<label class="form-label">Câu trả lời</label>
			<textarea class="form-control @error('answer') is-invalid @enderror" name="answer" rows="4" required>{{ old('answer', $faq->answer ?? '') }}</textarea>
			@error('answer')<div class="invalid-feedback">{{ $message }}</div>@enderror
		</div>
		<div class="row">
			<div class="col-md-6 mb-3">
				<label class="form-label">Từ khóa</label>
				<input class="form-control" name="keywords" value="{{ old('keywords', $faq->keywords ?? '') }}">
			</div>
			<div class="col-md-3 mb-3">
				<label class="form-label">Nhóm</label>
				<input class="form-control" name="category" value="{{ old('category', $faq->category ?? '') }}">
			</div>
			<div class="col-md-3 mb-3">
				<label class="form-label">Thứ tự</label>
				<input class="form-control" type="number" name="sort_order" value="{{ old('sort_order', $faq->sort_order ?? 0) }}">
			</div>
		</div>
		<label class="form-check mb-0">
			<input type="checkbox" name="is_active" value="1" class="form-check-input" @checked(old('is_active', $faq->is_active ?? 1))>
			<span class="form-check-label">Đang bật</span>
		</label>
	</div></div>
	<button class="btn btn-primary mb-4" type="submit" name="submit" value="1">Lưu</button>
</form>
@endsection
