@php
	$adminName = $login->name ?? 'Admin';
	$adminRole = \App\Support\Admin\AdminPermission::roleLabel($login->level ?? null);
	$avatar = mb_strtoupper(mb_substr($adminName, 0, 1));
@endphp
<header class="admin-header">
	<div class="admin-header-inner">
		<div class="admin-header-brand">
			<button class="btn admin-btn-icon d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#adminSidebar" aria-label="Mở menu">
				<i class="fa-solid fa-bars"></i>
			</button>
			<div class="lh-sm admin-header-page-title min-w-0 flex-grow-1">
				<span class="admin-brand-text text-truncate d-block">{{ $admin_header_title ?? 'Webshop Admin' }}</span>
			</div>
		</div>
		<div class="admin-header-actions">
			<span class="admin-user-link">
				<span class="admin-avatar">{{ $avatar }}</span>
				<span class="admin-user-meta d-none d-md-flex">
					<small>{{ $adminRole }}</small>
					<strong>{{ $adminName }}</strong>
				</span>
			</span>
			<a class="btn btn-sm btn-primary admin-logout-btn rounded-pill px-3" id="logout" href="{{ route('admin.logout') }}">
				<i class="fa-solid fa-right-from-bracket me-1"></i>
				<span class="d-none d-sm-inline">Đăng xuất</span>
			</a>
		</div>
	</div>
</header>
