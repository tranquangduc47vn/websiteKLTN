@extends('layouts.admin.app')

@section('content')
@php
	$isEdit = $catalog !== null;
	$action = $isEdit ? url('admin/catalog/edit/'.$catalog->id) : url('admin/catalog/add');
	$currentSort = (int) old('sort_order', $catalog->sort_order ?? 1);
	$sortOptions = range(1, 9);
	if (! in_array($currentSort, $sortOptions, true)) {
		$sortOptions[] = $currentSort;
	}
@endphp
<nav aria-label="breadcrumb" class="mb-3">
	<ol class="breadcrumb mb-0">
		<li class="breadcrumb-item"><a href="{{ url('admin/catalog') }}">Danh mục</a></li>
		<li class="breadcrumb-item active">{{ $isEdit ? 'Sửa danh mục' : 'Thêm danh mục' }}</li>
	</ol>
</nav>
<form method="post" action="{{ $action }}" class="admin-form">
	@csrf
	<div class="mb-3">
		<label for="name" class="form-label">Tên danh mục</label>
		<input type="text" name="name" id="name" class="form-control" value="{{ old('name', $catalog->name ?? '') }}" maxlength="100">
		@error('name')<div class="text-danger">{{ $message }}</div>@enderror
	</div>
	<div class="mb-3">
		<label for="description" class="form-label">Mô tả</label>
		<textarea name="description" id="description" class="form-control" rows="3" maxlength="255">{{ old('description', $catalog->description ?? '') }}</textarea>
		@error('description')<div class="text-danger">{{ $message }}</div>@enderror
	</div>
	<div class="mb-3">
		<label for="parent_id" class="form-label">Danh mục cha</label>
		<select name="parent_id" id="parent_id" class="form-select">
			<option value="0" @selected((string) old('parent_id', $catalog->parent_id ?? 0) === '0')>Menu gốc</option>
			<option value="1" @selected((string) old('parent_id', $catalog->parent_id ?? 0) === '1')>Thời trang</option>
			@foreach ($parents as $parent)
				@continue(in_array((int) $parent->id, [0, 1], true))
				<option value="{{ $parent->id }}" @selected((string) old('parent_id', $catalog->parent_id ?? 0) === (string) $parent->id)>
					{{ (int) $parent->parent_id > 0 ? '— ' : '' }}{{ $parent->name }}
				</option>
			@endforeach
		</select>
	</div>
	<div class="mb-3">
		<label for="sort_order" class="form-label">Thứ tự</label>
		<select name="sort_order" id="sort_order" class="form-select">
			@foreach ($sortOptions as $order)
				<option value="{{ $order }}" @selected($currentSort === (int) $order)>{{ $order }}</option>
			@endforeach
		</select>
	</div>
	<button class="btn btn-primary" type="submit">{{ $isEdit ? 'Lưu' : 'Thêm mới' }}</button>
	<a class="btn btn-outline-secondary" href="{{ url('admin/catalog') }}">Hủy bỏ</a>
</form>
@endsection
