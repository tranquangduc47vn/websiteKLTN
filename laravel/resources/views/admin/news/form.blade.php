@extends('layouts.admin.app')

@section('content')
@php $editing = $post !== null; @endphp
<nav aria-label="breadcrumb" class="mb-3">
	<ol class="breadcrumb mb-0">
		<li class="breadcrumb-item"><a href="{{ url('admin/posts') }}">Tin tức</a></li>
		<li class="breadcrumb-item active">{{ $editing ? 'Sửa' : 'Thêm tin tức mới' }}</li>
	</ol>
</nav>
<form method="post" enctype="multipart/form-data" action="{{ url($editing ? 'admin/posts/edit/' . $post->id : 'admin/posts/add') }}">
	@csrf
	<div class="mb-3">
		<label class="form-label">Tiêu đề tin tức</label>
		<input class="form-control" name="title" maxlength="255" value="{{ old('title', $post->title ?? '') }}">
	</div>
	<div class="mb-3">
		<label class="form-label">Ảnh đại diện</label>
		<input class="form-control" type="file" name="image" accept=".gif,.jpg,.jpeg,.png,.webp,.bmp">
	</div>
	<div class="mb-3">
		<label class="form-label">Mô tả ngắn (Intro)</label>
		<textarea class="form-control" name="intro" rows="3">{{ old('intro', $post->intro ?? '') }}</textarea>
	</div>
	<div class="mb-3">
		<label class="form-label">Nội dung chi tiết</label>
		<textarea class="form-control" name="content" rows="10">{{ old('content', $post->content ?? '') }}</textarea>
	</div>
	<button class="btn btn-primary" type="submit">{{ $editing ? 'Cập nhật' : 'Thêm bài viết' }}</button>
	<a class="btn btn-outline-secondary" href="{{ url('admin/posts') }}">Hủy bỏ</a>
</form>
@endsection
