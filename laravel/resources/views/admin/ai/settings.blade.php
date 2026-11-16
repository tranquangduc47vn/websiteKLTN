@extends('layouts.admin.app')

@section('content')
<nav aria-label="breadcrumb" class="admin-breadcrumb">
	<ol class="breadcrumb mb-0">
		<li class="breadcrumb-item"><a href="{{ url('admin') }}">Trang chủ</a></li>
		<li class="breadcrumb-item active">Cấu hình AI</li>
	</ol>
</nav>
<div class="admin-page-header">
	<div>
		<h1 class="admin-page-title">Cấu hình AI</h1>
		<p class="admin-page-subtitle">Bật trợ lý và quy tắc chuyển nhân viên.</p>
	</div>
</div>
<form method="post" action="{{ url('admin/ai-assistant/settings') }}" class="adm-form">
	@csrf
	<div class="admin-card"><div class="admin-card-body">
		<div class="mb-3">
			<label class="form-check form-switch">
				<input type="hidden" name="ai_enabled" value="0">
				<input type="checkbox" name="ai_enabled" value="1" class="form-check-input" @checked($settings['ai_enabled'])>
				<span class="form-check-label"><strong>Bật trợ lý AI</strong></span>
			</label>
		</div>
		<div class="mb-3">
			<label class="form-check form-switch">
				<input type="hidden" name="staff_support_enabled" value="0">
				<input type="checkbox" name="staff_support_enabled" value="1" class="form-check-input" @checked($settings['staff_support_enabled'])>
				<span class="form-check-label"><strong>Cho phép chuyển nhân viên</strong></span>
			</label>
		</div>
		<div class="mb-3">
			<label class="form-check form-switch">
				<input type="hidden" name="fallback_to_staff" value="0">
				<input type="checkbox" name="fallback_to_staff" value="1" class="form-check-input" @checked($settings['fallback_to_staff'])>
				<span class="form-check-label"><strong>Khi AI không trả lời được → chuyển nhân viên</strong></span>
			</label>
		</div>
		<div class="mb-3">
			<label class="form-label">Lời chào mặc định</label>
			<input type="text" name="welcome_message" class="form-control" maxlength="255" value="{{ $settings['welcome_message'] }}">
		</div>
		<div class="mb-0">
			<label class="form-label">Giờ làm việc (hiển thị khi khách hỏi)</label>
			<input type="text" name="working_hours_text" class="form-control" maxlength="255" value="{{ $settings['working_hours_text'] }}">
		</div>
	</div></div>
	<button type="submit" name="submit" value="1" class="btn btn-primary mb-4">Lưu cấu hình</button>
</form>
@endsection
