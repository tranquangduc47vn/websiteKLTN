@php
	$admin = auth('admin')->user();
	$show = $admin && (int) $admin->level === \App\Models\Admin::ROLE_ADMIN;
	$query = $query ?? [];
	$base = array_filter($query, fn ($value) => $value !== '' && $value !== null);
	$all = $base;
	$all['scope'] = 'all';
	$pageQuery = $base;
	$pageQuery['scope'] = 'page';
	$qAll = '?' . http_build_query($all);
	$qPage = '?' . http_build_query($pageQuery);
	$paged = in_array($module, ['orders', 'products', 'inventory'], true);
@endphp
@if ($show)
	<div class="card border-0 shadow-sm mb-3">
		<div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
			<div>
				<div class="fw-semibold"><i class="fa-solid fa-file-export me-2"></i>Xuất báo cáo</div>
				<div class="text-muted small">Tải PDF/Excel hoặc xem trước để in. Bộ lọc hiện tại được giữ nguyên.</div>
			</div>
			<div class="d-flex flex-wrap gap-2">
				<a class="btn btn-sm btn-outline-danger" href="{{ url('admin/export/pdf/' . $module) }}{{ $qAll }}">PDF</a>
				<a class="btn btn-sm btn-outline-success" href="{{ url('admin/export/excel/' . $module) }}{{ $qAll }}">Excel</a>
				<a class="btn btn-sm btn-primary" href="{{ url('admin/export/preview/' . $module) }}{{ $qAll }}" target="_blank" rel="noopener">Xem &amp; In</a>
				@if ($paged)
					<a class="btn btn-sm btn-outline-secondary" href="{{ url('admin/export/pdf/' . $module) }}{{ $qPage }}">PDF trang</a>
					<a class="btn btn-sm btn-outline-secondary" href="{{ url('admin/export/excel/' . $module) }}{{ $qPage }}">Excel trang</a>
				@endif
			</div>
		</div>
	</div>
@endif
