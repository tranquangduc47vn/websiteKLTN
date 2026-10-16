<!DOCTYPE html>
<html lang="vi">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate">
	<title>{{ ($admin_header_title ?? 'Webshop Admin') }} · Webshop Admin</title>
	<link href="{{ url('assets/admin/css/bootstrap5.min.css') }}" rel="stylesheet">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
	<link href="{{ url('assets/admin/css/admin-theme.css') }}?v=10" rel="stylesheet">
	<link href="{{ url('assets/admin/css/admin-dark-mode.css') }}?v=1" rel="stylesheet">
	{!! \App\Support\Storefront\Csrf::bootstrapScript() !!}
</head>
<body class="admin-app">
<script>
(function () {
	try {
		if (localStorage.getItem('webshop-admin-theme') === 'dark') {
			document.body.classList.add('admin-dark');
			document.body.setAttribute('data-bs-theme', 'dark');
		} else {
			document.body.setAttribute('data-bs-theme', 'light');
		}
	} catch (e) {}
})();
</script>
	@include('layouts.admin.sidebar')
	@include('layouts.admin.header')
	<main class="admin-main" id="main-content-body">
		<div class="admin-main-inner container-fluid">
			@include('layouts.admin.message')
			@yield('content')
		</div>
	</main>
	<script src="{{ url('assets/admin/js/bootstrap5.bundle.min.js') }}"></script>
</body>
</html>
