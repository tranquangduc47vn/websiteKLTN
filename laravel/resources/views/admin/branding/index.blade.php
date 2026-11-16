@extends('layouts.admin.app')

@section('content')
<nav aria-label="breadcrumb" class="admin-breadcrumb">
	<ol class="breadcrumb mb-0">
		<li class="breadcrumb-item"><a href="{{ url('admin') }}">Trang chủ</a></li>
		<li class="breadcrumb-item active">Logo website</li>
	</ol>
</nav>
<div class="admin-page-header">
	<div>
		<h1 class="admin-page-title">Logo website</h1>
		<p class="admin-page-subtitle">Thay logo hiển thị trên header storefront.</p>
	</div>
</div>
<div class="admin-card mb-3">
	<div class="admin-card-body">
		@if ($logo_exists)
			<div class="adm-preview adm-preview--logo mb-3" id="logoPreview">
				<img src="{{ $logo_url }}?v={{ time() }}" alt="Logo website">
			</div>
			<p class="mb-0 text-muted">File: <code>upload/{{ $logo_path }}</code></p>
		@else
			<div class="adm-preview adm-preview--logo mb-3" id="logoPreview"></div>
			<p class="mb-0">Chưa có logo hoặc file không tồn tại.</p>
		@endif
	</div>
</div>
<form method="post" enctype="multipart/form-data" action="{{ url('admin/branding') }}" class="adm-form">
	@csrf
	<div class="admin-card"><div class="admin-card-body">
		<label class="form-label" for="site-logo-file">Chọn hình logo</label>
		<input class="form-control @error('logo') is-invalid @enderror" type="file" id="site-logo-file" name="logo" accept="image/png,image/jpeg,image/webp,image/gif" required data-adm-preview="#logoPreview">
		@error('logo')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
	</div></div>
	<button class="btn btn-primary mb-4" type="submit" name="submit" value="1">Lưu logo</button>
</form>
@endsection
