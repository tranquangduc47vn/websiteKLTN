@extends('layouts.admin.app')

@section('content')
<nav aria-label="breadcrumb" class="admin-breadcrumb">
	<ol class="breadcrumb mb-0">
		<li class="breadcrumb-item"><a href="{{ url('admin/banner') }}">Banner danh mục</a></li>
		<li class="breadcrumb-item active">Sửa</li>
	</ol>
</nav>
<div class="admin-page-header">
	<h1 class="admin-page-title">Sửa banner</h1>
</div>
<form method="post" enctype="multipart/form-data" action="{{ url('admin/banner/edit/' . $banner->id) }}" class="adm-form">
	@csrf
	<div class="admin-card"><div class="admin-card-body">
		<div class="mb-3">
			<label class="form-label">Tên hiển thị</label>
			<input class="form-control @error('name') is-invalid @enderror" name="name" maxlength="255" required value="{{ old('name', $banner->name) }}">
			@error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
		</div>
		<div class="mb-3">
			<label class="form-label">Hình ảnh</label>
			<div class="adm-preview mb-2" id="bannerPreview">
				@if ($banner->image_link)
					<img src="{{ url('upload/slider/' . $banner->image_link) }}" alt="">
				@endif
			</div>
			<input class="form-control @error('image') is-invalid @enderror" type="file" name="image" accept=".gif,.jpg,.jpeg,.png,.webp" data-adm-preview="#bannerPreview">
			@error('image')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
		</div>
		<div class="mb-0">
			<label class="form-label">Liên kết</label>
			<input class="form-control @error('link') is-invalid @enderror" name="link" maxlength="255" required value="{{ old('link', $banner->link) }}">
			@error('link')<div class="invalid-feedback">{{ $message }}</div>@enderror
		</div>
	</div></div>
	<div class="d-flex flex-wrap gap-2 mb-4">
		<button class="btn btn-primary" type="submit" name="submit" value="1">Cập nhật ngay</button>
		<a class="btn btn-outline-secondary" href="{{ url('admin/banner') }}">Hủy</a>
	</div>
</form>
@endsection
