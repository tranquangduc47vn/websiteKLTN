@extends('layouts.admin.app')

@section('content')
@php $editing = $slider !== null; @endphp
<nav aria-label="breadcrumb" class="admin-breadcrumb">
	<ol class="breadcrumb mb-0">
		<li class="breadcrumb-item"><a href="{{ url('admin/slider') }}">Slider</a></li>
		<li class="breadcrumb-item active">{{ $editing ? 'Sửa' : 'Thêm slider' }}</li>
	</ol>
</nav>
<div class="admin-page-header">
	<h1 class="admin-page-title">{{ $editing ? 'Sửa slider' : 'Thêm slider' }}</h1>
</div>
<form method="post" enctype="multipart/form-data" action="{{ url($editing ? 'admin/slider/edit/' . $slider->id : 'admin/slider/add') }}" class="adm-form">
	@csrf
	<div class="admin-card"><div class="admin-card-body">
		<div class="mb-3">
			<label class="form-label">Tên slider</label>
			<input class="form-control @error('name') is-invalid @enderror" name="name" maxlength="255" value="{{ old('name', $slider->name ?? '') }}">
			@error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
		</div>
		<div class="mb-3">
			<label class="form-label">Hình ảnh</label>
			<div class="adm-preview mb-2" id="sliderPreview">
				@if ($editing && $slider->image_link)
					<img src="{{ url('upload/slider/' . $slider->image_link) }}" alt="">
				@endif
			</div>
			<input class="form-control @error('image') is-invalid @enderror" type="file" name="image" accept=".gif,.jpg,.jpeg,.png,.webp" data-adm-preview="#sliderPreview">
			@error('image')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
		</div>
		<div class="mb-3">
			<label class="form-label">Liên kết</label>
			<input class="form-control" name="link" maxlength="255" value="{{ old('link', $slider->link ?? '') }}">
		</div>
		<div class="mb-0">
			<label class="form-label">Thứ tự</label>
			<select class="form-select" name="sort_order">
				@for ($i = 1; $i <= 10; $i++)
					<option value="{{ $i }}" @selected((int) old('sort_order', $slider->sort_order ?? 1) === $i)>{{ $i }}</option>
				@endfor
			</select>
		</div>
	</div></div>
	<div class="d-flex flex-wrap gap-2 mb-4">
		<button class="btn btn-primary" type="submit">{{ $editing ? 'Lưu' : 'Thêm mới' }}</button>
		<a class="btn btn-outline-secondary" href="{{ url('admin/slider') }}">Hủy</a>
	</div>
</form>
@endsection
