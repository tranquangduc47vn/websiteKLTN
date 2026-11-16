@extends('layouts.admin.app')

@section('content')
@php $editing = $page !== null; @endphp
<nav aria-label="breadcrumb" class="admin-breadcrumb">
	<ol class="breadcrumb mb-0">
		<li class="breadcrumb-item"><a href="{{ url(ltrim($base, '/')) }}">{{ $heading }}</a></li>
		<li class="breadcrumb-item active">{{ $editing ? 'Sửa' : 'Thêm mới' }}</li>
	</ol>
</nav>
<div class="admin-page-header">
	<h1 class="admin-page-title">{{ $editing ? 'Sửa' : 'Thêm mới' }}</h1>
</div>
<form method="post" enctype="multipart/form-data" action="{{ url(ltrim($base, '/') . ($editing ? '/edit/' . $page->id : '/add')) }}" class="adm-form">
	@csrf
	<div class="admin-card"><div class="admin-card-body">
		<div class="mb-3">
			<label class="form-label">Tiêu đề</label>
			<input class="form-control" name="title" maxlength="255" value="{{ old('title', $page->title ?? '') }}">
		</div>
		<div class="mb-3">
			<label class="form-label">Ảnh</label>
			<div class="adm-preview mb-2" id="contentPreview">
				@if ($editing && ! empty($page->image))
					<img src="{{ url('upload/' . $page->image) }}" alt="">
				@endif
			</div>
			<input class="form-control" type="file" name="image" accept=".gif,.jpg,.jpeg,.png,.webp" data-adm-preview="#contentPreview">
		</div>
		<div class="mb-3">
			<label class="form-label">Mô tả ngắn</label>
			<textarea class="form-control" name="intro" rows="3">{{ old('intro', $page->intro ?? '') }}</textarea>
		</div>
		<div class="mb-0">
			<label class="form-label">Nội dung</label>
			<textarea class="form-control" name="content" rows="10">{{ old('content', $page->content ?? '') }}</textarea>
		</div>
	</div></div>
	<div class="d-flex flex-wrap gap-2 mb-4">
		<button class="btn btn-primary" type="submit">{{ $editing ? 'Cập nhật' : 'Thêm mới' }}</button>
		<a class="btn btn-outline-secondary" href="{{ url(ltrim($base, '/')) }}">Hủy</a>
	</div>
</form>
@endsection
