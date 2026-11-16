@extends('layouts.admin.app')

@section('content')
@php $editing = $staff !== null; @endphp
<nav aria-label="breadcrumb" class="admin-breadcrumb">
	<ol class="breadcrumb mb-0">
		<li class="breadcrumb-item"><a href="{{ url('admin/admin') }}">Nhân viên</a></li>
		<li class="breadcrumb-item active">{{ $editing ? 'Sửa thông tin thành viên' : 'Thêm quản trị viên' }}</li>
	</ol>
</nav>
<div class="admin-page-header">
	<h1 class="admin-page-title">{{ $editing ? 'Sửa thông tin thành viên' : 'Thêm quản trị viên' }}</h1>
</div>
<form method="post" action="{{ url($editing ? 'admin/admin/edit/' . $staff->id : 'admin/admin/add') }}" class="adm-form">
	@csrf
	<div class="admin-card"><div class="admin-card-body">
	<div class="mb-3">
		<label class="form-label" for="name">Họ tên</label>
		<input class="form-control" id="name" name="name" maxlength="100" value="{{ old('name', $staff->name ?? '') }}">
	</div>
	<div class="mb-3">
		<label class="form-label" for="email">Email</label>
		<input class="form-control" id="email" type="email" name="email" maxlength="100" value="{{ old('email', $staff->email ?? '') }}">
	</div>
	@if ($editing)
		<p class="text-warning">* Nếu thay đổi mật khẩu thì mới nhập vào ô dưới đây</p>
	@endif
	<div class="mb-3">
		<label class="form-label" for="password">Mật khẩu</label>
		<input class="form-control" id="password" type="password" name="password" autocomplete="new-password" placeholder="{{ $editing ? 'Bỏ trống nếu không đổi' : 'Nhập mật khẩu' }}">
	</div>
	<div class="mb-3">
		<label class="form-label" for="re_password">Nhập lại mật khẩu</label>
		<input class="form-control" id="re_password" type="password" name="re_password" autocomplete="new-password">
	</div>
	<div class="mb-3">
		<label class="form-label" for="level">Phân quyền</label>
		<select class="form-select" id="level" name="level">
			<option value="">--- CHỌN ---</option>
			@if ((int) auth('admin')->user()->level === \App\Models\Admin::ROLE_ADMIN)
				<option value="0" @selected((string) old('level', $staff->level ?? '') === '0')>Admin</option>
			@endif
			<option value="1" @selected((string) old('level', $staff->level ?? '') === '1')>Mod</option>
			<option value="2" @selected((string) old('level', $staff->level ?? '') === '2')>User</option>
		</select>
	</div>
	</div></div>
	<div class="d-flex flex-wrap gap-2 mb-4">
		<button class="btn btn-primary" type="submit">{{ $editing ? 'Lưu thay đổi' : 'Thêm mới' }}</button>
		<a class="btn btn-outline-secondary" href="{{ url('admin/admin') }}">Hủy bỏ</a>
	</div>
</form>
@endsection
