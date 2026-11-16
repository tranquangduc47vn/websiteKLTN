@extends('layouts.admin.app')

@section('content')
@php $editing = $post !== null; @endphp
<nav aria-label="breadcrumb" class="admin-breadcrumb">
	<ol class="breadcrumb mb-0">
		<li class="breadcrumb-item"><a href="{{ url('admin/posts') }}">Tin tức</a></li>
		<li class="breadcrumb-item active">{{ $editing ? 'Sửa' : 'Thêm tin tức mới' }}</li>
	</ol>
</nav>
<div class="admin-page-header">
	<h1 class="admin-page-title">{{ $editing ? 'Sửa bài viết' : 'Thêm tin tức mới' }}</h1>
</div>
<form method="post" enctype="multipart/form-data" action="{{ url($editing ? 'admin/posts/edit/' . $post->id : 'admin/posts/add') }}" class="adm-form">
	@csrf
	<div class="admin-card"><div class="admin-card-body">
		<div class="mb-3">
			<label class="form-label">Tiêu đề tin tức</label>
			<input class="form-control @error('title') is-invalid @enderror" name="title" maxlength="255" value="{{ old('title', $post->title ?? '') }}">
			@error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
		</div>
		<div class="mb-3">
			<label class="form-label">Ảnh đại diện</label>
			<div class="adm-preview mb-2" id="newsPreview">
				@if ($editing && $post->image)
					<img src="{{ url('upload/news/' . $post->image) }}" alt="">
				@endif
			</div>
			<input class="form-control @error('image') is-invalid @enderror" type="file" name="image" accept=".gif,.jpg,.jpeg,.png,.webp,.bmp" data-adm-preview="#newsPreview">
			@error('image')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
		</div>
		<div class="mb-3">
			<label class="form-label">Mô tả ngắn (Intro)</label>
			<textarea class="form-control" name="intro" rows="3">{{ old('intro', $post->intro ?? '') }}</textarea>
		</div>
		<div class="mb-0">
			<label class="form-label">Nội dung chi tiết</label>
			<textarea class="form-control" name="content" rows="10">{{ old('content', $post->content ?? '') }}</textarea>
		</div>
	</div></div>
	<div class="d-flex flex-wrap gap-2 mb-4">
		<button class="btn btn-primary" type="submit">{{ $editing ? 'Cập nhật' : 'Thêm bài viết' }}</button>
		<a class="btn btn-outline-secondary" href="{{ url('admin/posts') }}">Hủy bỏ</a>
	</div>
</form>
@endsection
