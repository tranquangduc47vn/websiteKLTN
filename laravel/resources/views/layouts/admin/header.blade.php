@php
	$adminName = $login->name ?? 'Admin';
	$adminRole = \App\Support\Admin\AdminPermission::roleLabel($login->level ?? null);
	$avatar = mb_strtoupper(mb_substr($adminName, 0, 1));
	$segment = request()->segment(2) ?: 'home';
	$crumbs = [
		'home' => 'Dashboard',
		'products' => 'Sản phẩm',
		'product-reviews' => 'Đánh giá sản phẩm',
		'catalog' => 'Danh mục',
		'orders' => 'Đơn hàng',
		'report' => 'Báo cáo',
		'users' => 'Khách hàng',
		'voucher' => 'Voucher',
		'inventory' => 'Tồn kho',
		'receipts' => 'Phiếu nhập',
		'stock-movements' => 'Lịch sử biến động',
		'suppliers' => 'Nhà cung cấp',
		'slider' => 'Slider',
		'posts' => 'Tin tức',
		'page' => 'Trang nội dung',
		'banner' => 'Banner',
		'branding' => 'Logo website',
		'store' => 'Hệ thống cửa hàng',
		'tuyendung' => 'Tuyển dụng',
		'hoptac' => 'Hợp tác',
		'vanchuyen' => 'Vận chuyển',
		'admin' => 'Nhân viên',
		'support-chat' => 'Hỗ trợ khách hàng',
		'ai-assistant' => 'Cấu hình AI',
	];
	$pageTitle = $admin_header_title ?? ($crumbs[$segment] ?? 'Quản trị');
	$crumb = $crumbs[$segment] ?? $pageTitle;
	$canOrders = \App\Support\Admin\AdminPermission::can($login ?? null, 'order.manage');
	$canChat = \App\Support\Admin\AdminPermission::can($login ?? null, 'order.chat');
	$pending = (int) ($admin_pending_orders ?? 0);
	$waiting = (int) ($admin_support_waiting ?? 0);
	$noticeCount = ($canOrders ? $pending : 0) + ($canChat ? $waiting : 0);
@endphp
<header class="admin-header">
	<div class="admin-header-inner">
		<div class="admin-header-brand">
			<button class="btn admin-btn-icon" type="button" id="adminSidebarToggle" aria-controls="adminSidebar" aria-label="Thu gọn menu">
				<i class="fa-solid fa-bars"></i>
			</button>
			<div class="admin-header-titles min-w-0">
				<nav class="admin-top-crumb" aria-label="Breadcrumb">
					<a href="{{ url('admin') }}">Admin</a>
					<span aria-hidden="true">/</span>
					<span>{{ $crumb }}</span>
				</nav>
				<span class="admin-brand-text text-truncate d-block">{{ $pageTitle }}</span>
			</div>
		</div>
		<div class="admin-header-actions">
			<div class="dropdown">
				<button class="btn admin-btn-icon admin-notify-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Thông báo">
					<i class="fa-solid fa-bell"></i>
					@if ($noticeCount > 0)
						<span class="admin-notify-count">{{ $noticeCount > 99 ? '99+' : $noticeCount }}</span>
					@endif
				</button>
				<ul class="dropdown-menu dropdown-menu-end admin-notify-menu">
					@if ($canOrders)
						<li><a class="dropdown-item" href="{{ url('admin/orders') }}">{{ $pending }} đơn cần xử lý</a></li>
					@endif
					@if ($canChat)
						<li><a class="dropdown-item" href="{{ url('admin/support-chat') }}">{{ $waiting }} hội thoại chờ hỗ trợ</a></li>
					@endif
					@if (! $canOrders && ! $canChat)
						<li><span class="dropdown-item-text">Không có thông báo cho tài khoản này.</span></li>
					@endif
				</ul>
			</div>
			<span class="admin-user-link">
				<span class="admin-avatar">{{ $avatar }}</span>
				<span class="admin-user-meta d-none d-md-flex">
					<small>{{ $adminRole }}</small>
					<strong>{{ $adminName }}</strong>
				</span>
			</span>
			<form method="post" action="{{ route('admin.logout') }}" class="d-inline m-0">
				@csrf
				<button type="submit" class="btn btn-sm btn-primary admin-logout-btn rounded-pill px-3" id="logout">
					<i class="fa-solid fa-right-from-bracket me-1"></i>
					<span class="d-none d-sm-inline">Đăng xuất</span>
				</button>
			</form>
		</div>
	</div>
</header>
