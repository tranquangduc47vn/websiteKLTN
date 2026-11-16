<!DOCTYPE html>
<html lang="vi">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate">
	<meta http-equiv="Pragma" content="no-cache">
	<meta http-equiv="Expires" content="0">
	<title>Đăng nhập quản trị</title>
	<meta name="robots" content="noindex, nofollow">
	<link href="{{ url('assets/admin/css/bootstrap5.min.css') }}" rel="stylesheet">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
	<link href="{{ url('assets/admin/css/admin-theme.css') }}?v=10" rel="stylesheet">
	<link href="{{ url('assets/admin/css/admin-dark-mode.css') }}?v=1" rel="stylesheet">
	<link href="{{ url('assets/admin/css/admin-export.css') }}?v=1" rel="stylesheet">
	{!! \App\Support\Storefront\Csrf::bootstrapScript() !!}
</head>
<body class="admin-app admin-login-page">
	<div class="admin-login-shell">
		<section class="admin-login-hero" aria-hidden="true">
			<div class="admin-login-hero-inner">
				<span class="admin-brand-mark admin-login-hero-logo">W</span>
				<h2 class="admin-login-hero-title">Webshop Admin</h2>
				<p class="admin-login-hero-text">
					Quản lý sản phẩm, đơn hàng và nội dung cửa hàng thời trang — mọi thứ trong một bảng điều khiển gọn gàng.
				</p>
				<ul class="admin-login-hero-features">
					<li><i class="fa-solid fa-chart-line"></i> Tổng quan doanh thu</li>
					<li><i class="fa-solid fa-shield-halved"></i> Truy cập bảo mật</li>
					<li><i class="fa-solid fa-mobile-screen"></i> Giao diện responsive</li>
				</ul>
			</div>
			<div class="admin-login-hero-pattern"></div>
		</section>

		<main class="admin-login-main">
			<div class="admin-login-card">
				<header class="admin-login-card-head">
					<span class="admin-brand-mark d-lg-none mb-3">W</span>
					<h1 class="admin-login-title">Chào mừng trở lại</h1>
					<p class="admin-login-subtitle">Đăng nhập tài khoản quản trị của bạn</p>
				</header>

				@if (session('message_success'))
					<div class="alert alert-success">{{ session('message_success') }}</div>
				@endif
				@if (session('message_fail'))
					<div class="alert alert-danger d-flex align-items-start gap-2 py-2 mb-3" role="alert">
						<i class="fa-solid fa-circle-exclamation mt-1 flex-shrink-0"></i>
						<span>{{ session('message_fail') }}</span>
					</div>
				@endif

				<form class="admin-form admin-login-form" method="POST" action="{{ route('admin.login.post') }}" novalidate>
					@csrf
					<div class="mb-3">
						<label class="form-label" for="email">E-mail</label>
						<div class="admin-login-input-wrap">
							<i class="fa-solid fa-envelope admin-login-input-icon" aria-hidden="true"></i>
							<input class="form-control" id="email" placeholder="email@example.com" name="email" type="email" autofocus value="{{ old('email') }}" autocomplete="username" required>
						</div>
						@error('email')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
					</div>

					<div class="mb-3">
						<label class="form-label" for="password">Mật khẩu</label>
						<div class="admin-login-input-wrap">
							<i class="fa-solid fa-lock admin-login-input-icon" aria-hidden="true"></i>
							<input class="form-control" id="password" placeholder="Nhập mật khẩu" name="password" type="password" autocomplete="current-password" required>
						</div>
						@error('password')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
					</div>

					<button type="submit" class="btn btn-primary btn-lg w-100 admin-login-submit">
						<i class="fa-solid fa-right-to-bracket me-2"></i>Đăng nhập
					</button>
				</form>

				<p class="admin-login-footnote">
					<i class="fa-solid fa-lock me-1"></i> Chỉ dành cho nhân viên được cấp quyền.
				</p>
			</div>
		</main>
	</div>
	<script src="{{ url('assets/admin/js/bootstrap5.bundle.min.js') }}"></script>
	{!! \App\Support\Storefront\Csrf::injectScript() !!}
	<script src="{{ url('assets/admin/js/admin-layout.js') }}?v=10"></script>
</body>
</html>
