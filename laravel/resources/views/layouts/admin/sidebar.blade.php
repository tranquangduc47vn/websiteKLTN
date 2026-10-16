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
	$main = [
		['slug' => 'home', 'url' => url('admin'), 'icon' => 'fa-solid fa-gauge-high', 'label' => 'Trang chủ'],
		['slug' => 'products', 'url' => url('admin/products'), 'icon' => 'fa-solid fa-shirt', 'label' => 'Sản phẩm'],
		['slug' => 'product-reviews', 'url' => url('admin/product-reviews'), 'icon' => 'fa-solid fa-star', 'label' => 'Đánh giá SP'],
		['slug' => 'slider', 'url' => url('admin/slider'), 'icon' => 'fa-solid fa-images', 'label' => 'Slider'],
		['slug' => ['orders', 'report'], 'url' => url('admin/orders'), 'icon' => 'fa-solid fa-receipt', 'label' => 'Đơn đặt hàng'],
		['slug' => 'users', 'url' => url('admin/users'), 'icon' => 'fa-solid fa-users', 'label' => 'Khách hàng'],
		['slug' => 'voucher', 'url' => url('admin/voucher'), 'icon' => 'fa-solid fa-ticket', 'label' => 'Voucher'],
		['slug' => 'admin', 'url' => url('admin/admin'), 'icon' => 'fa-solid fa-user-shield', 'label' => 'Nhân viên'],
	];
	$warehouse = [
		['slug' => 'inventory', 'url' => url('admin/inventory'), 'icon' => 'fa-solid fa-boxes-stacked', 'label' => 'Tồn kho'],
		['slug' => 'receipts', 'url' => url('admin/receipts'), 'icon' => 'fa-solid fa-dolly', 'label' => 'Phiếu nhập'],
		['slug' => 'inventory-adjust', 'url' => url('admin/inventory/low-stock'), 'icon' => 'fa-solid fa-clipboard-check', 'label' => 'Kiểm kê kho'],
		['slug' => 'stock-movements', 'url' => url('admin/stock-movements'), 'icon' => 'fa-solid fa-clock-rotate-left', 'label' => 'Lịch sử biến động'],
		['slug' => 'suppliers', 'url' => url('admin/suppliers'), 'icon' => 'fa-solid fa-truck-field', 'label' => 'Nhà cung cấp'],
	];
	$info = [
		['slug' => 'catalog', 'url' => url('admin/catalog'), 'icon' => 'fa-solid fa-folder-tree', 'label' => 'Danh mục'],
		['slug' => 'posts', 'url' => url('admin/posts'), 'icon' => 'fa-solid fa-newspaper', 'label' => 'Quản lý tin tức'],
		['slug' => 'page', 'url' => url('admin/page'), 'icon' => 'fa-solid fa-file-lines', 'label' => 'Quản lý trang'],
		['slug' => 'banner', 'url' => url('admin/banner'), 'icon' => 'fa-solid fa-panorama', 'label' => 'Banner danh mục'],
		['slug' => 'branding', 'url' => url('admin/branding'), 'icon' => 'fa-solid fa-image', 'label' => 'Logo website'],
		['slug' => 'store', 'url' => url('admin/store'), 'icon' => 'fa-solid fa-map-location-dot', 'label' => 'Quản lý bản đồ'],
		['slug' => 'tuyendung', 'url' => url('admin/tuyendung'), 'icon' => 'fa-solid fa-briefcase', 'label' => 'Tuyển dụng'],
		['slug' => 'hoptac', 'url' => url('admin/hoptac'), 'icon' => 'fa-solid fa-handshake', 'label' => 'Quản lý hợp tác'],
		['slug' => 'vanchuyen', 'url' => url('admin/vanchuyen'), 'icon' => 'fa-solid fa-truck', 'label' => 'Chính sách vận chuyển'],
	];
	$can = fn (string $permission) => \App\Support\Admin\AdminPermission::can($login ?? null, $permission);
	$infoSlugs = ['catalog', 'posts', 'page', 'banner', 'branding', 'store', 'tuyendung', 'hoptac', 'vanchuyen'];
	$whSlugs = ['inventory', 'receipts', 'inventory-adjust', 'stock-movements', 'suppliers', 'stock-receipts', 'stock-inventory'];
@endphp
<div class="offcanvas offcanvas-start offcanvas-lg admin-sidebar" tabindex="-1" id="adminSidebar">
	<div class="offcanvas-body p-0 d-flex flex-column">
		<div class="admin-sidebar-brand">
			<span class="admin-brand-mark">W</span>
			<div class="admin-sidebar-brand-title">Webshop Admin<small>Thời trang &amp; bán lẻ</small></div>
		</div>
		<nav class="admin-nav nav flex-column flex-grow-1" aria-label="Admin navigation">
			<div class="admin-nav-section">Tổng quan</div>
			@foreach ($main as $item)
				@continue(! $allowed($item['slug']))
				<a class="nav-link {{ $active($item['slug']) }}" href="{{ $item['url'] }}">
					<i class="{{ $item['icon'] }} admin-nav-icon"></i>
					<span>{{ $item['label'] }}</span>
					@if ($item['label'] === 'Đơn đặt hàng' && ($admin_pending_orders ?? 0) > 0)
						<span class="admin-nav-badge admin-nav-badge--urgent">{{ $admin_pending_orders > 99 ? '99+' : (int) $admin_pending_orders }}</span>
					@endif
				</a>
			@endforeach
			@php
				$warehouseVisible = array_values(array_filter($warehouse, fn ($item) => $allowed($item['slug'])));
				$whOpen = in_array($current, $whSlugs, true);
			@endphp
			@if ($warehouseVisible !== [])
				<div class="admin-nav-group {{ $whOpen ? 'is-open' : '' }}">
					<button type="button" class="nav-link admin-nav-group-toggle w-100 border-0 bg-transparent text-start" data-bs-toggle="collapse" data-bs-target="#adminNavWarehouse" aria-expanded="{{ $whOpen ? 'true' : 'false' }}">
						<i class="fa-solid fa-warehouse admin-nav-icon"></i>
						<span class="flex-grow-1">Quản lý kho</span>
						<i class="fa-solid fa-chevron-down admin-nav-group-chevron"></i>
					</button>
					<div class="collapse {{ $whOpen ? 'show' : '' }}" id="adminNavWarehouse">
						<div class="admin-nav-sub">
							@foreach ($warehouseVisible as $item)
								<a class="nav-link admin-nav-sub-link {{ $active($item['slug']) }}" href="{{ $item['url'] }}">
									<i class="{{ $item['icon'] }} admin-nav-icon"></i>
									<span>{{ $item['label'] }}</span>
								</a>
							@endforeach
						</div>
					</div>
				</div>
			@endif
			@php
				$infoVisible = array_values(array_filter($info, fn ($item) => $allowed($item['slug'])));
				$infoOpen = in_array($current, $infoSlugs, true);
			@endphp
			@if ($infoVisible !== [])
				<div class="admin-nav-group {{ $infoOpen ? 'is-open' : '' }}">
					<button type="button" class="nav-link admin-nav-group-toggle w-100 border-0 bg-transparent text-start" data-bs-toggle="collapse" data-bs-target="#adminNavInfo" aria-expanded="{{ $infoOpen ? 'true' : 'false' }}">
						<i class="fa-solid fa-circle-info admin-nav-icon"></i>
						<span class="flex-grow-1">Thông tin</span>
						<i class="fa-solid fa-chevron-down admin-nav-group-chevron"></i>
					</button>
					<div class="collapse {{ $infoOpen ? 'show' : '' }}" id="adminNavInfo">
						<div class="admin-nav-sub">
							@foreach ($infoVisible as $item)
								<a class="nav-link admin-nav-sub-link {{ $active($item['slug']) }}" href="{{ $item['url'] }}">
									<i class="{{ $item['icon'] }} admin-nav-icon"></i>
									<span>{{ $item['label'] }}</span>
								</a>
							@endforeach
						</div>
					</div>
				</div>
			@endif
			@if ($can('order.chat'))
				<div class="admin-nav-section">Hỗ trợ</div>
				<a class="nav-link {{ $current === 'support-chat' ? 'active' : '' }}" href="{{ url('admin/support-chat') }}">
					<i class="fa-solid fa-headset admin-nav-icon"></i>
					<span>Hỗ trợ khách hàng</span>
					@if (($admin_support_waiting ?? 0) > 0)
						<span class="admin-nav-badge admin-nav-badge--urgent">{{ (int) $admin_support_waiting }}</span>
					@endif
				</a>
			@endif
			@if ($can('ai.manage'))
				<div class="admin-nav-section">Trợ lý AI</div>
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
	</div>
</div>
