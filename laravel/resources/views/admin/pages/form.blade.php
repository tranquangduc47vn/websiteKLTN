@extends('layouts.admin.app')

@section('content')
@php $editing = $page !== null; @endphp
<nav aria-label="breadcrumb" class="admin-breadcrumb">
	<ol class="breadcrumb mb-0">
		<li class="breadcrumb-item"><a href="{{ url('admin/pages') }}">Trang tĩnh</a></li>
		<li class="breadcrumb-item active">{{ $editing ? 'Sửa' : 'Thêm trang' }}</li>
	</ol>
</nav>
<div class="admin-page-header">
	<h1 class="admin-page-title">{{ $editing ? 'Sửa trang' : 'Thêm trang' }}</h1>
</div>
<form method="post" action="{{ url($editing ? 'admin/pages/edit/' . $page->id : 'admin/pages/add') }}" class="adm-form">
	@csrf
	<div class="admin-card"><div class="admin-card-body">
		<div class="mb-3">
			<label class="form-label">Tiêu đề</label>
			<input class="form-control @error('title') is-invalid @enderror" name="title" maxlength="255" value="{{ old('title', $page->title ?? '') }}">
			@error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
		</div>
		@unless ($editing)
			<div class="mb-3">
				<label class="form-label">Slug</label>
				<input class="form-control @error('slug') is-invalid @enderror" name="slug" value="{{ old('slug', 'gioi-thieu') }}">
				@error('slug')<div class="invalid-feedback">{{ $message }}</div>@enderror
			</div>
		@endunless
		<div class="mb-0">
			<label class="form-label">Nội dung</label>
			<textarea class="form-control @error('content') is-invalid @enderror" name="content" rows="12">{{ old('content', $page->content ?? '') }}</textarea>
			@error('content')<div class="invalid-feedback">{{ $message }}</div>@enderror
		</div>
	</div></div>
	<div class="d-flex flex-wrap gap-2 mb-4">
		<button class="btn btn-primary" name="submit" value="1" type="submit">{{ $editing ? 'Lưu cập nhật' : 'Thêm mới trang' }}</button>
		<a class="btn btn-outline-secondary" href="{{ url('admin/pages') }}">Hủy</a>
	</div>
</form>
@endsection
