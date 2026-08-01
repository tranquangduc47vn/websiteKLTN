<?php
$this->load->library('stock_service');
$this->load->helper('permission');
$status_label = $this->stock_service->status_label($receipt->status);
$is_confirmed = ($receipt->status === 'confirmed');
$actor_name = $is_confirmed && !empty($receipt->confirmed_by) && !empty($receipt->confirmer_name)
	? $receipt->confirmer_name
	: ($receipt->creator_name ?? '');
$actor_level = $is_confirmed && !empty($receipt->confirmed_by) && isset($receipt->confirmer_level)
	? $receipt->confirmer_level
	: ($receipt->creator_level ?? null);
$actor_role = admin_role_label($actor_level);
$actor_field_label = $is_confirmed ? 'Người nhập kho' : 'Người tạo';
?>
<div class="admin-breadcrumb">
	<nav aria-label="breadcrumb">
		<ol class="breadcrumb">
			<li class="breadcrumb-item"><a href="<?php echo admin_url('home'); ?>">Trang chủ</a></li>
			<li class="breadcrumb-item"><a href="<?php echo admin_url('stock-receipts'); ?>">Phiếu nhập</a></li>
			<li class="breadcrumb-item active"><?php echo htmlspecialchars($receipt->receipt_code, ENT_QUOTES, 'UTF-8'); ?></li>
		</ol>
	</nav>
</div>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
	<div>
		<h1 class="h4 mb-1"><?php echo htmlspecialchars($receipt->receipt_code, ENT_QUOTES, 'UTF-8'); ?></h1>
		<span class="badge text-bg-<?php echo $receipt->status === 'confirmed' ? 'success' : ($receipt->status === 'draft' ? 'warning' : 'danger'); ?>">
			<?php echo htmlspecialchars($status_label, ENT_QUOTES, 'UTF-8'); ?>
		</span>
	</div>
	<div class="d-flex flex-wrap gap-2">
		<?php if ($receipt->status === 'draft' && !empty($can_receipt)) { ?>
			<a href="<?php echo admin_url('stock-receipts/edit/' . (int) $receipt->id); ?>" class="btn btn-outline-primary btn-sm">Sửa nháp</a>
			<form method="post" action="<?php echo admin_url('stock-receipts/confirm'); ?>" class="d-inline" onsubmit="return confirm('Xác nhận phiếu nháp cũ và cộng tồn kho?');">
				<input type="hidden" name="receipt_id" value="<?php echo (int) $receipt->id; ?>">
				<button type="submit" class="btn btn-success btn-sm">Xác nhận nhập kho</button>
			</form>
			<form method="post" action="<?php echo admin_url('stock-receipts/cancel'); ?>" class="d-inline" onsubmit="return confirm('Hủy phiếu nháp?');">
				<input type="hidden" name="receipt_id" value="<?php echo (int) $receipt->id; ?>">
				<button type="submit" class="btn btn-outline-danger btn-sm">Hủy phiếu</button>
			</form>
		<?php } ?>
		<a href="<?php echo admin_url('stock-receipts'); ?>" class="btn btn-outline-secondary btn-sm">Quay lại</a>
	</div>
</div>

<div class="row g-3 mb-3">
	<div class="col-md-6">
		<div class="admin-card h-100"><div class="admin-card-body">
			<div class="small text-muted">Nhà cung cấp</div>
			<div><?php echo htmlspecialchars($receipt->supplier_name ?: '—', ENT_QUOTES, 'UTF-8'); ?></div>
			<div class="small text-muted mt-2">Ghi chú</div>
			<div><?php echo $receipt->note !== '' ? nl2br(htmlspecialchars($receipt->note, ENT_QUOTES, 'UTF-8')) : '—'; ?></div>
		</div></div>
	</div>
	<div class="col-md-6">
		<div class="admin-card h-100"><div class="admin-card-body">
			<div class="small text-muted">Tổng SL</div>
			<div class="fs-5 fw-semibold"><?php echo number_format((int) $receipt->total_qty); ?></div>
			<div class="small text-muted mt-2">Tổng tiền nhập</div>
			<div class="fs-5 fw-semibold"><?php echo number_format((float) ($receipt->total_amount ?? 0), 0, ',', '.'); ?> ₫</div>
			<div class="small text-muted mt-2"><?php echo htmlspecialchars($actor_field_label, ENT_QUOTES, 'UTF-8'); ?></div>
			<div>
				<?php if ($actor_name !== '') { ?>
					<?php echo htmlspecialchars($actor_name, ENT_QUOTES, 'UTF-8'); ?>
					<span class="badge text-bg-secondary ms-1"><?php echo htmlspecialchars($actor_role, ENT_QUOTES, 'UTF-8'); ?></span>
				<?php } else { ?>
					—
				<?php } ?>
			</div>
			<div class="small text-muted mt-2">Thời gian</div>
			<div>Tạo: <?php echo date('d/m/Y H:i', (int) $receipt->created); ?></div>
			<?php if (!empty($receipt->confirmed_at)) { ?>
				<div>Nhập kho: <?php echo date('d/m/Y H:i', (int) $receipt->confirmed_at); ?></div>
			<?php } ?>
		</div></div>
	</div>
</div>

<div class="admin-card">
	<div class="admin-card-header">Chi tiết dòng hàng</div>
	<div class="table-responsive">
		<table class="table table-sm align-middle mb-0">
			<thead>
				<tr>
					<th>Sản phẩm</th>
					<th>Size</th>
					<th>Màu</th>
					<th>SL</th>
					<th class="text-end">Tổng tiền nhập</th>
					<th class="text-end">Giá vốn</th>
				</tr>
			</thead>
			<tbody>
			<?php
			$view_total = 0;
			foreach ($items as $item) {
				$line_subtotal = isset($item->subtotal) && (float) $item->subtotal > 0
					? (float) $item->subtotal
					: (float) $item->unit_cost * (int) $item->qty;
				$view_total += $line_subtotal;
			?>
				<tr>
					<td><?php echo htmlspecialchars($item->product_name, ENT_QUOTES, 'UTF-8'); ?></td>
					<td><?php echo $item->size !== '' ? htmlspecialchars($item->size, ENT_QUOTES, 'UTF-8') : '—'; ?></td>
					<td><?php echo $item->color !== '' ? htmlspecialchars($item->color, ENT_QUOTES, 'UTF-8') : '—'; ?></td>
					<td><?php echo number_format((int) $item->qty); ?></td>
					<td class="text-end"><?php echo number_format($line_subtotal, 0, ',', '.'); ?> ₫</td>
					<td class="text-end"><?php echo number_format((float) $item->unit_cost, 0, ',', '.'); ?> ₫</td>
				</tr>
			<?php } ?>
			</tbody>
			<tfoot>
				<tr>
					<th colspan="4" class="text-end">Tổng cộng</th>
					<th class="text-end"><?php echo number_format($view_total > 0 ? $view_total : (float) ($receipt->total_amount ?? 0), 0, ',', '.'); ?> ₫</th>
					<th></th>
				</tr>
			</tfoot>
		</table>
	</div>
</div>
