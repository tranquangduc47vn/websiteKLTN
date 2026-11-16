@extends('layouts.admin.app')

@section('content')
<nav aria-label="breadcrumb" class="admin-breadcrumb">
	<ol class="breadcrumb mb-0">
		<li class="breadcrumb-item"><a href="{{ url('admin/suppliers') }}">Nhà cung cấp</a></li>
		<li class="breadcrumb-item active">Thêm</li>
	</ol>
</nav>
<div class="admin-page-header">
	<h1 class="admin-page-title">Thêm nhà cung cấp</h1>
</div>
<form method="post" action="{{ url('admin/suppliers/add') }}" class="adm-form">
	@csrf
	<input type="hidden" name="submit" value="1">
	<div class="admin-card"><div class="admin-card-body">
		<div class="row">
			<div class="col-md-4 mb-3"><label class="form-label" for="sup-code">Mã</label><input name="code" id="sup-code" class="form-control @error('code') is-invalid @enderror" required>@error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
			<div class="col-md-8 mb-3"><label class="form-label" for="sup-name">Tên</label><input name="name" id="sup-name" class="form-control @error('name') is-invalid @enderror" required>@error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
		</div>
		<div class="row">
			<div class="col-md-4 mb-3"><label class="form-label" for="sup-contact">Người liên hệ</label><input name="contact_name" id="sup-contact" class="form-control"></div>
			<div class="col-md-4 mb-3"><label class="form-label" for="sup-phone">Điện thoại</label><input name="phone" id="sup-phone" class="form-control"></div>
			<div class="col-md-4 mb-3"><label class="form-label" for="sup-email">Email</label><input name="email" id="sup-email" type="email" class="form-control @error('email') is-invalid @enderror">@error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
		</div>
		<div class="mb-3"><label class="form-label" for="sup-address">Địa chỉ</label><input name="address" id="sup-address" class="form-control"></div>
		<div class="mb-0"><label class="form-label" for="sup-note">Ghi chú</label><textarea name="note" id="sup-note" class="form-control" rows="2"></textarea></div>
	</div></div>
	<div class="d-flex flex-wrap gap-2 mb-4">
		<button class="btn btn-primary" type="submit">Lưu</button>
		<a class="btn btn-outline-secondary" href="{{ url('admin/suppliers') }}">Hủy</a>
	</div>
</form>
@endsection
