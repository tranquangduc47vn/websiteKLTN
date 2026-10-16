@extends('layouts.admin.app')

@section('content')
<h1 class="h4 mb-3">Thêm nhà cung cấp</h1>
<form method="post" action="{{ url('admin/suppliers/add') }}" class="admin-card">
	@csrf
	<input type="hidden" name="submit" value="1">
	<div class="mb-3"><label class="form-label" for="sup-code">Mã</label><input name="code" id="sup-code" class="form-control" required></div>
	<div class="mb-3"><label class="form-label" for="sup-name">Tên</label><input name="name" id="sup-name" class="form-control" required></div>
	<div class="mb-3"><label class="form-label" for="sup-contact">Người liên hệ</label><input name="contact_name" id="sup-contact" class="form-control"></div>
	<div class="mb-3"><label class="form-label" for="sup-phone">Điện thoại</label><input name="phone" id="sup-phone" class="form-control"></div>
	<div class="mb-3"><label class="form-label" for="sup-email">Email</label><input name="email" id="sup-email" type="email" class="form-control"></div>
	<div class="mb-3"><label class="form-label" for="sup-address">Địa chỉ</label><input name="address" id="sup-address" class="form-control"></div>
	<div class="mb-3"><label class="form-label" for="sup-note">Ghi chú</label><textarea name="note" id="sup-note" class="form-control" rows="2"></textarea></div>
	<button class="btn btn-primary" type="submit">Lưu</button>
	<a href="{{ url('admin/suppliers') }}">Hủy</a>
</form>
@endsection
