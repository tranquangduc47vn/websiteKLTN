@extends('layouts.admin.app')

@section('content')
@php
	$isEdit = $voucher !== null;
	$from = ($isEdit && (int) $voucher->valid_from > 0) ? date('Y-m-d', (int) $voucher->valid_from) : '';
	$to = ($isEdit && (int) $voucher->valid_to > 0) ? date('Y-m-d', (int) $voucher->valid_to) : '';
@endphp
<nav aria-label="breadcrumb" class="mb-3">
	<ol class="breadcrumb mb-0">
		<li class="breadcrumb-item"><a href="{{ url('admin/voucher') }}">Voucher</a></li>
		<li class="breadcrumb-item active">{{ $isEdit ? 'Sửa voucher' : 'Thêm voucher' }}</li>
	</ol>
</nav>
<form method="post" action="{{ $isEdit ? url('admin/voucher/edit/'.$voucher->id) : url('admin/voucher/add') }}" class="admin-card">
	@csrf
	<input type="hidden" name="submit" value="1">
	<div class="mb-3">
		<label class="form-label" for="v-code">Mã voucher</label>
		<input type="text" name="code" id="v-code" class="form-control text-uppercase" required value="{{ $isEdit ? $voucher->code : '' }}">
	</div>
	<div class="mb-3">
		<label class="form-label" for="v-name">Tên hiển thị</label>
		<input type="text" name="name" id="v-name" class="form-control" value="{{ $isEdit ? $voucher->name : '' }}">
	</div>
	<div class="mb-3">
		<label class="form-label" for="v-desc">Mô tả ngắn</label>
		<input type="text" name="description" id="v-desc" class="form-control" value="{{ $isEdit ? $voucher->description : '' }}">
	</div>
	<div class="row">
		<div class="col-md-4 mb-3">
			<label class="form-label" for="v-type">Loại giảm</label>
			<select name="discount_type" id="v-type" class="form-select">
				<option value="fixed" @selected(! $isEdit || $voucher->discount_type === 'fixed')>Số tiền cố định (₫)</option>
				<option value="percent" @selected($isEdit && $voucher->discount_type === 'percent')>Phần trăm (%)</option>
			</select>
		</div>
		<div class="col-md-4 mb-3">
			<label class="form-label" for="v-value">Giá trị giảm</label>
			<input type="number" name="discount_value" id="v-value" class="form-control" min="0" required value="{{ $isEdit ? (int) $voucher->discount_value : 0 }}">
		</div>
		<div class="col-md-4 mb-3">
			<label class="form-label" for="v-max">Giảm tối đa (₫, % only)</label>
			<input type="number" name="max_discount" id="v-max" class="form-control" min="0" value="{{ $isEdit ? (int) $voucher->max_discount : 0 }}">
		</div>
	</div>
	<div class="row">
		<div class="col-md-4 mb-3">
			<label class="form-label" for="v-min">Đơn tối thiểu (₫)</label>
			<input type="number" name="min_order_amount" id="v-min" class="form-control" min="0" value="{{ $isEdit ? (int) $voucher->min_order_amount : 0 }}">
		</div>
		<div class="col-md-4 mb-3">
			<label class="form-label" for="v-tier">Hạng tối thiểu</label>
			<select name="tier_min" id="v-tier" class="form-select">
				@foreach ($tiers as $key => $label)
					<option value="{{ $key }}" @selected($isEdit && $voucher->tier_min === $key)>{{ $label }}</option>
				@endforeach
			</select>
		</div>
		<div class="col-md-4 mb-3">
			<label class="form-label" for="v-user">ID khách riêng (0 = công khai)</label>
			<input type="number" name="user_id" id="v-user" class="form-control" min="0" value="{{ $isEdit ? (int) $voucher->user_id : 0 }}">
		</div>
	</div>
	<div class="row">
		<div class="col-md-4 mb-3">
			<label class="form-label" for="v-limit">Giới hạn lượt dùng (0 = không giới hạn)</label>
			<input type="number" name="usage_limit" id="v-limit" class="form-control" min="0" value="{{ $isEdit ? (int) $voucher->usage_limit : 0 }}">
		</div>
		<div class="col-md-4 mb-3">
			<label class="form-label" for="v-per">Lượt / khách</label>
			<input type="number" name="per_user_limit" id="v-per" class="form-control" min="1" value="{{ $isEdit ? (int) $voucher->per_user_limit : 1 }}">
		</div>
		<div class="col-md-4 mb-3 d-flex align-items-end">
			<label class="form-check">
				<input type="checkbox" name="is_active" value="1" class="form-check-input" @checked(! $isEdit || (int) $voucher->is_active === 1)>
				<span class="form-check-label">Đang kích hoạt</span>
			</label>
		</div>
	</div>
	<div class="row">
		<div class="col-md-6 mb-3">
			<label class="form-label" for="v-from">Hiệu lực từ (để trống = ngay)</label>
			<input type="date" name="valid_from" id="v-from" class="form-control" value="{{ $from }}">
		</div>
		<div class="col-md-6 mb-3">
			<label class="form-label" for="v-to">Hiệu lực đến (để trống = không hết hạn)</label>
			<input type="date" name="valid_to" id="v-to" class="form-control" value="{{ $to }}">
		</div>
	</div>
	<button class="btn btn-primary" type="submit">Lưu</button>
	<a href="{{ url('admin/voucher') }}">Hủy</a>
</form>
@endsection
