@extends('layouts.admin.app')

@section('content')
@php $editing = $store !== null; @endphp
<nav aria-label="breadcrumb" class="admin-breadcrumb">
	<ol class="breadcrumb mb-0">
		<li class="breadcrumb-item"><a href="{{ url('admin/store') }}">Cửa hàng</a></li>
		<li class="breadcrumb-item active">{{ $editing ? 'Sửa' : 'Thêm cửa hàng' }}</li>
	</ol>
</nav>
<div class="admin-page-header">
	<h1 class="admin-page-title">{{ $editing ? 'Sửa cửa hàng' : 'Thêm cửa hàng' }}</h1>
</div>
<form method="post" action="{{ url($editing ? 'admin/store/edit/' . $store->id : 'admin/store/add') }}" class="adm-form">
	@csrf
	<div class="admin-card"><div class="admin-card-body">
	<div class="mb-3">
		<label class="form-label">Tên</label>
		<input class="form-control" name="name" maxlength="255" value="{{ old('name', $store->name ?? '') }}">
	</div>
	<div class="mb-3">
		<label class="form-label">Tỉnh / Thành phố</label>
		<select class="form-select" name="city">
			@foreach ($cities as $city)
				<option value="{{ $city }}" @selected(old('city', $store->city ?? 'Hà Nội') === $city)>{{ $city }}</option>
			@endforeach
		</select>
	</div>
	<div class="mb-3">
		<label class="form-label">Hotline</label>
		<input class="form-control" name="phone" maxlength="30" value="{{ old('phone', $store->phone ?? '') }}">
	</div>
	<div class="mb-3">
		<label class="form-label">Địa chỉ</label>
		<input class="form-control" name="address" maxlength="500" value="{{ old('address', $store->address ?? '') }}">
	</div>
	<div class="mb-3">
		<label class="form-label">Link bản đồ</label>
		<textarea class="form-control" name="map_link" rows="3">{{ old('map_link', $store->map_link ?? '') }}</textarea>
	</div>
	</div></div>
	<div class="d-flex flex-wrap gap-2 mb-4">
		<button class="btn btn-primary" type="submit">{{ $editing ? 'Cập nhật' : 'Thêm mới' }}</button>
		<a class="btn btn-outline-secondary" href="{{ url('admin/store') }}">Hủy</a>
	</div>
</form>
@endsection
