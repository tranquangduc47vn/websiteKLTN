@php
	$current = request()->segment(2) ?: 'home';
	$segment3 = request()->segment(3);
	$allowed = fn ($slug) => \App\Support\Admin\AdminPermission::sidebarAllowed($slug, $login ?? null);
	$active = function ($slug) use ($current, $segment3) {
		$items = (array) $slug;
		if (! in_array($current, $items, true)) {
			return '';
		}
		if ($current === 'orders' && $segment3 === 'chat') {
			return '';
		}
		return 'active';
	};
	$link = function (array $item) use ($allowed, $active, $admin_pending_orders) {
		if (! $allowed($item['slug'])) {
			return '';
		}
		$badge = '';
		if (($item['badge'] ?? '') === 'orders' && ($admin_pending_orders ?? 0) > 0) {
			$n = $admin_pending_orders > 99 ? '99+' : (int) $admin_pending_orders;
			$badge = '<span class="admin-nav-badge admin-nav-badge--urgent">' . $n . '</span>';
		}
		return '<a class="nav-link ' . $active($item['slug']) . '" href="' . e($item['url']) . '">'
			. '<i class="' . e($item['icon']) . ' admin-nav-icon"></i>'
			. '<span>' . e($item['label']) . '</span>'
			. $badge
			. '</a>';
	};
	$commerce = [
		['slug' => 'home', 'url' => url('admin'), 'icon' => 'fa-solid fa-gauge-high', 'label' => 'Dashboard'],
		['slug' => 'products', 'url' => url('admin/products'), 'icon' => 'fa-solid fa-shirt', 'label' => 'Sản phẩm'],
		['slug' => 'product-reviews', 'url' => url('admin/product-reviews'), 'icon' => 'fa-solid fa-star', 'label' => 'Đánh giá sản phẩm'],
		['slug' => 'catalog', 'url' => url('admin/catalog'), 'icon' => 'fa-solid fa-folder-tree', 'label' => 'Danh mục'],
		['slug' => 'orders', 'url' => url('admin/orders'), 'icon' => 'fa-solid fa-receipt', 'label' => 'Đơn đặt hàng', 'badge' => 'orders'],
		['slug' => 'report', 'url' => url('admin/report'), 'icon' => 'fa-solid fa-chart-column', 'label' => 'Báo cáo'],
		['slug' => 'users', 'url' => url('admin/users'), 'icon' => 'fa-solid fa-users', 'label' => 'Khách hàng'],
		['slug' => 'voucher', 'url' => url('admin/voucher'), 'icon' => 'fa-solid fa-ticket', 'label' => 'Voucher'],
	];
	$warehouse = [
		['slug' => 'inventory', 'url' => url('admin/inventory'), 'icon' => 'fa-solid fa-boxes-stacked', 'label' => 'Tồn kho'],
		['slug' => 'receipts', 'url' => url('admin/receipts'), 'icon' => 'fa-solid fa-dolly', 'label' => 'Phiếu nhập'],
		['slug' => 'inventory-adjust', 'url' => url('admin/inventory/low-stock'), 'icon' => 'fa-solid fa-clipboard-check', 'label' => 'Kiểm kê kho'],
		['slug' => 'stock-movements', 'url' => url('admin/stock-movements'), 'icon' => 'fa-solid fa-clock-rotate-left', 'label' => 'Lịch sử biến động'],
		['slug' => 'suppliers', 'url' => url('admin/suppliers'), 'icon' => 'fa-solid fa-truck-field', 'label' => 'Nhà cung cấp'],
	];
	$content = [
		['slug' => 'slider', 'url' => url('admin/slider'), 'icon' => 'fa-solid fa-images', 'label' => 'Slider'],
		['slug' => 'posts', 'url' => url('admin/posts'), 'icon' => 'fa-solid fa-newspaper', 'label' => 'Tin tức'],
		['slug' => 'page', 'url' => url('admin/page'), 'icon' => 'fa-solid fa-file-lines', 'label' => 'Trang nội dung'],
		['slug' => 'banner', 'url' => url('admin/banner'), 'icon' => 'fa-solid fa-panorama', 'label' => 'Banner'],
		['slug' => 'branding', 'url' => url('admin/branding'), 'icon' => 'fa-solid fa-image', 'label' => 'Logo website'],
		['slug' => 'store', 'url' => url('admin/store'), 'icon' => 'fa-solid fa-map-location-dot', 'label' => 'Hệ thống cửa hàng'],
		['slug' => 'tuyendung', 'url' => url('admin/tuyendung'), 'icon' => 'fa-solid fa-briefcase', 'label' => 'Tuyển dụng'],
		['slug' => 'hoptac', 'url' => url('admin/hoptac'), 'icon' => 'fa-solid fa-handshake', 'label' => 'Hợp tác'],
		['slug' => 'vanchuyen', 'url' => url('admin/vanchuyen'), 'icon' => 'fa-solid fa-truck', 'label' => 'Vận chuyển'],
	];
	$settings = [
		['slug' => 'admin', 'url' => url('admin/admin'), 'icon' => 'fa-solid fa-user-shield', 'label' => 'Nhân viên'],
	];
	$can = fn (string $permission) => \App\Support\Admin\AdminPermission::can($login ?? null, $permission);
	$whSlugs = ['inventory', 'receipts', 'inventory-adjust', 'stock-movements', 'suppliers', 'stock-receipts', 'stock-inventory'];
	$contentSlugs = ['slider', 'posts', 'page', 'banner', 'branding', 'store', 'tuyendung', 'hoptac', 'vanchuyen'];
	$warehouseVisible = array_values(array_filter($warehouse, fn ($item) => $allowed($item['slug'])));
	$contentVisible = array_values(array_filter($content, fn ($item) => $allowed($item['slug'])));
	$settingsVisible = array_values(array_filter($settings, fn ($item) => $allowed($item['slug'])));
@endphp
<div class="offcanvas offcanvas-start offcanvas-lg admin-sidebar" tabindex="-1" id="adminSidebar" aria-label="Menu quản trị">
	<div class="offcanvas-body p-0 d-flex flex-column">
		<div class="admin-sidebar-brand">
			<span class="admin-brand-mark" aria-hidden="true">W</span>
			<div class="admin-sidebar-brand-title">Webshop<small>Quản trị bán hàng</small></div>
		</div>
		<nav class="admin-nav nav flex-column flex-grow-1" aria-label="Admin navigation">
			<div class="admin-nav-section">Bán hàng</div>
			@foreach ($commerce as $item)
				{!! $link($item) !!}
			@endforeach

			@if ($warehouseVisible !== [])
				@php $whOpen = in_array($current, $whSlugs, true); @endphp
				<div class="admin-nav-group {{ $whOpen ? 'is-open' : '' }}">
					<button type="button" class="nav-link admin-nav-group-toggle w-100 border-0 bg-transparent text-start" data-bs-toggle="collapse" data-bs-target="#adminNavWarehouse" aria-expanded="{{ $whOpen ? 'true' : 'false' }}">
						<i class="fa-solid fa-warehouse admin-nav-icon"></i>
						<span class="flex-grow-1">Biến thể và tồn kho</span>
						<i class="fa-solid fa-chevron-down admin-nav-group-chevron"></i>
					</button>
					<div class="collapse {{ $whOpen ? 'show' : '' }}" id="adminNavWarehouse">
						<div class="admin-nav-sub">
							@foreach ($warehouseVisible as $item)
								{!! $link($item) !!}
							@endforeach
						</div>
					</div>
				</div>
			@endif

			@if ($contentVisible !== [])
				@php $contentOpen = in_array($current, $contentSlugs, true); @endphp
				<div class="admin-nav-section">Nội dung</div>
				<div class="admin-nav-group {{ $contentOpen ? 'is-open' : '' }}">
					<button type="button" class="nav-link admin-nav-group-toggle w-100 border-0 bg-transparent text-start" data-bs-toggle="collapse" data-bs-target="#adminNavContent" aria-expanded="{{ $contentOpen ? 'true' : 'false' }}">
						<i class="fa-solid fa-layer-group admin-nav-icon"></i>
						<span class="flex-grow-1">Nội dung website</span>
						<i class="fa-solid fa-chevron-down admin-nav-group-chevron"></i>
					</button>
					<div class="collapse {{ $contentOpen ? 'show' : '' }}" id="adminNavContent">
						<div class="admin-nav-sub">
							@foreach ($contentVisible as $item)
								{!! $link($item) !!}
							@endforeach
						</div>
					</div>
				</div>
			@endif

			@if ($can('order.chat') || $settingsVisible !== [] || $can('ai.manage'))
				<div class="admin-nav-section">Cấu hình</div>
			@endif
			@foreach ($settingsVisible as $item)
				{!! $link($item) !!}
			@endforeach
			@if ($can('order.chat'))
				<a class="nav-link {{ $current === 'support-chat' ? 'active' : '' }}" href="{{ url('admin/support-chat') }}">
					<i class="fa-solid fa-headset admin-nav-icon"></i>
					<span>Hỗ trợ khách hàng</span>
					@if (($admin_support_waiting ?? 0) > 0)
						<span class="admin-nav-badge admin-nav-badge--urgent">{{ (int) $admin_support_waiting }}</span>
					@endif
				</a>
			@endif
			@if ($can('ai.manage'))
				<a class="nav-link {{ $current === 'ai-assistant' && (request()->segment(3) === null || request()->segment(3) === 'settings') ? 'active' : '' }}" href="{{ url('admin/ai-assistant/settings') }}">
					<i class="fa-solid fa-sliders admin-nav-icon"></i>
					<span>Cấu hình AI</span>
				</a>
				<a class="nav-link {{ $current === 'ai-assistant' && request()->segment(3) === 'faq' ? 'active' : '' }}" href="{{ url('admin/ai-assistant/faq') }}">
					<i class="fa-solid fa-circle-question admin-nav-icon"></i>
					<span>FAQ</span>
				</a>
			@endif
		</nav>
		<form method="post" action="{{ route('admin.logout') }}" class="admin-sidebar-logout">
			@csrf
			<button type="submit" class="nav-link w-100 border-0 bg-transparent text-start">
				<i class="fa-solid fa-right-from-bracket admin-nav-icon"></i>
				<span>Đăng xuất</span>
			</button>
		</form>
	</div>
</div>
