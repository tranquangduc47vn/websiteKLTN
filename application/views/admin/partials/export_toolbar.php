<?php
defined('BASEPATH') OR exit('No direct script access allowed');
$export_module = isset($export_module) ? (string) $export_module : '';
$export_query = isset($export_query) ? (string) $export_query : '';
$base = admin_url('export');
$q = $export_query !== '' ? '?' . $export_query : '';
$q_all = $export_query !== '' ? '?' . $export_query . '&scope=all' : '?scope=all';
$q_page = $export_query !== '' ? '?' . $export_query . '&scope=page' : '?scope=page';
$has_page_scope = in_array($export_module, array('orders', 'products', 'inventory'), true);
?>
<div class="admin-export-toolbar card border-0 shadow-sm mb-3">
	<div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
		<div class="admin-export-toolbar__info">
			<div class="admin-export-toolbar__title">
				<i class="fa-solid fa-file-export text-primary me-2"></i>Xuất báo cáo
			</div>
			<div class="admin-export-toolbar__hint text-muted small">
				Tải PDF/Excel hoặc xem trước để in. Bộ lọc hiện tại được giữ nguyên.
			</div>
		</div>
		<div class="admin-export-toolbar__actions d-flex flex-wrap gap-2">
			<a class="btn btn-sm btn-outline-danger" href="<?php echo $base . '/pdf/' . rawurlencode($export_module) . $q_all; ?>" title="Xuất PDF toàn bộ">
				<i class="fa-solid fa-file-pdf me-1"></i> PDF
			</a>
			<a class="btn btn-sm btn-outline-success" href="<?php echo $base . '/excel/' . rawurlencode($export_module) . $q_all; ?>" title="Xuất Excel toàn bộ">
				<i class="fa-solid fa-file-excel me-1"></i> Excel
			</a>
			<a class="btn btn-sm btn-primary" href="<?php echo $base . '/preview/' . rawurlencode($export_module) . $q_all; ?>" target="_blank" rel="noopener" title="Xem &amp; in">
				<i class="fa-solid fa-print me-1"></i> Xem &amp; In
			</a>
			<?php if ($has_page_scope) { ?>
				<div class="btn-group btn-group-sm" role="group" aria-label="Xuất trang hiện tại">
					<a class="btn btn-outline-secondary" href="<?php echo $base . '/pdf/' . rawurlencode($export_module) . $q_page; ?>" title="PDF trang hiện tại">PDF trang</a>
					<a class="btn btn-outline-secondary" href="<?php echo $base . '/excel/' . rawurlencode($export_module) . $q_page; ?>" title="Excel trang hiện tại">Excel trang</a>
				</div>
			<?php } ?>
		</div>
	</div>
</div>
