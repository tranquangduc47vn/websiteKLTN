@extends('layouts.admin.app')

@section('content')
@php
	$isEdit = $voucher !== null;
	$posted = old('submit') !== null;
	$code = old('code', $isEdit ? $voucher->code : '');
	$name = old('name', $isEdit ? $voucher->name : '');
	$description = old('description', $isEdit ? $voucher->description : '');
	$type = old('discount_type', $isEdit ? $voucher->discount_type : 'fixed');
	$tier = old('tier_min', $isEdit ? $voucher->tier_min : 'member');
	$active = $posted
		? (string) old('is_active') === '1'
		: (! $isEdit || (int) $voucher->is_active === 1);
	$from = old('valid_from', ($isEdit && $voucher->valid_from) ? $voucher->valid_from->format('Y-m-d') : '');
	$to = old('valid_to', ($isEdit && $voucher->valid_to) ? $voucher->valid_to->format('Y-m-d') : '');
@endphp
<nav aria-label="breadcrumb" class="admin-breadcrumb">
	<ol class="breadcrumb mb-0">
		<li class="breadcrumb-item"><a href="{{ url('admin/voucher') }}">Voucher</a></li>
		<li class="breadcrumb-item active">{{ $isEdit ? 'Sửa voucher' : 'Thêm voucher' }}</li>
	</ol>
</nav>
<div class="admin-page-header">
	<h1 class="admin-page-title">{{ $isEdit ? 'Sửa voucher' : 'Thêm voucher' }}</h1>
</div>
<form method="post" action="{{ $isEdit ? url('admin/voucher/edit/'.$voucher->id) : url('admin/voucher/add') }}" class="adm-form">
	@csrf
	<input type="hidden" name="submit" value="1">
	<div class="admin-card"><div class="admin-card-body">
	<div class="mb-3">
		<label class="form-label" for="v-code">Mã voucher</label>
		<input type="text" name="code" id="v-code" class="form-control text-uppercase @error('code') is-invalid @enderror" required value="{{ $code }}" @if ($errors->has('code')) autofocus @endif>
		@error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
	</div>
	<div class="mb-3">
		<label class="form-label" for="v-name">Tên hiển thị</label>
		<input type="text" name="name" id="v-name" class="form-control @error('name') is-invalid @enderror" value="{{ $name }}">
		@error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
	</div>
	<div class="mb-3">
		<label class="form-label" for="v-desc">Mô tả ngắn</label>
		<input type="text" name="description" id="v-desc" class="form-control @error('description') is-invalid @enderror" value="{{ $description }}">
		@error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
	</div>
	<div class="row">
		<div class="col-md-4 mb-3">
			<label class="form-label" for="v-type">Loại giảm</label>
			<select name="discount_type" id="v-type" class="form-select @error('discount_type') is-invalid @enderror">
				<option value="fixed" @selected($type === 'fixed')>Số tiền cố định (₫)</option>
				<option value="percent" @selected($type === 'percent')>Phần trăm (%)</option>
			</select>
			@error('discount_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
		</div>
		<div class="col-md-4 mb-3">
			<label class="form-label" for="v-value">Giá trị giảm</label>
			<input type="number" name="discount_value" id="v-value" class="form-control @error('discount_value') is-invalid @enderror" min="0" required value="{{ old('discount_value', $isEdit ? (int) $voucher->discount_value : 0) }}">
			@error('discount_value')<div class="invalid-feedback">{{ $message }}</div>@enderror
		</div>
		<div class="col-md-4 mb-3">
			<label class="form-label" for="v-max">Giảm tối đa (₫, % only)</label>
			<input type="number" name="max_discount" id="v-max" class="form-control @error('max_discount') is-invalid @enderror" min="0" value="{{ old('max_discount', $isEdit ? (int) $voucher->max_discount : 0) }}">
			@error('max_discount')<div class="invalid-feedback">{{ $message }}</div>@enderror
		</div>
	</div>
	<div class="row">
		<div class="col-md-4 mb-3">
			<label class="form-label" for="v-min">Đơn tối thiểu (₫)</label>
			<input type="number" name="min_order_amount" id="v-min" class="form-control @error('min_order_amount') is-invalid @enderror" min="0" value="{{ old('min_order_amount', $isEdit ? (int) $voucher->min_order_amount : 0) }}">
			@error('min_order_amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
		</div>
		<div class="col-md-4 mb-3">
			<label class="form-label" for="v-tier">Hạng tối thiểu</label>
			<select name="tier_min" id="v-tier" class="form-select">
				@foreach ($tiers as $key => $label)
					<option value="{{ $key }}" @selected($tier === $key)>{{ $label }}</option>
				@endforeach
			</select>
		</div>
		<div class="col-md-4 mb-3">
			<label class="form-label" for="v-user">ID khách riêng (0 = công khai)</label>
			<input type="number" name="user_id" id="v-user" class="form-control @error('user_id') is-invalid @enderror" min="0" value="{{ old('user_id', $isEdit ? (int) $voucher->user_id : 0) }}">
			@error('user_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
		</div>
	</div>
	<div class="row">
		<div class="col-md-4 mb-3">
			<label class="form-label" for="v-limit">Giới hạn lượt dùng (0 = không giới hạn)</label>
			<input type="number" name="usage_limit" id="v-limit" class="form-control @error('usage_limit') is-invalid @enderror" min="0" value="{{ old('usage_limit', $isEdit ? (int) $voucher->usage_limit : 0) }}">
			@error('usage_limit')<div class="invalid-feedback">{{ $message }}</div>@enderror
		</div>
		<div class="col-md-4 mb-3">
			<label class="form-label" for="v-per">Lượt / khách</label>
			<input type="number" name="per_user_limit" id="v-per" class="form-control @error('per_user_limit') is-invalid @enderror" min="1" value="{{ old('per_user_limit', $isEdit ? (int) $voucher->per_user_limit : 1) }}">
			@error('per_user_limit')<div class="invalid-feedback">{{ $message }}</div>@enderror
		</div>
		<div class="col-md-4 mb-3 d-flex align-items-end">
			<label class="form-check">
				<input type="checkbox" name="is_active" value="1" class="form-check-input" @checked($active)>
				<span class="form-check-label">Đang kích hoạt</span>
			</label>
		</div>
	</div>
	<div class="row">
		<div class="col-md-6 mb-3">
			<label class="form-label" for="v-from">Hiệu lực từ (để trống = ngay)</label>
			<input type="date" name="valid_from" id="v-from" class="form-control @error('valid_from') is-invalid @enderror" value="{{ $from }}">
			@error('valid_from')<div class="invalid-feedback">{{ $message }}</div>@enderror
		</div>
		<div class="col-md-6 mb-3">
			<label class="form-label" for="v-to">Hiệu lực đến (để trống = không hết hạn)</label>
			<input type="date" name="valid_to" id="v-to" class="form-control @error('valid_to') is-invalid @enderror" value="{{ $to }}">
			@error('valid_to')<div class="invalid-feedback">{{ $message }}</div>@enderror
		</div>
	</div>
	</div></div>
	<div class="d-flex flex-wrap gap-2 mb-4">
		<button class="btn btn-primary" type="submit">Lưu</button>
		<a class="btn btn-outline-secondary" href="{{ url('admin/voucher') }}">Hủy</a>
	</div>
</form>
@endsection
