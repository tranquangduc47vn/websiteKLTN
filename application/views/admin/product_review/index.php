<div class="admin-breadcrumb">
	<nav aria-label="breadcrumb">
		<ol class="breadcrumb">
			<li class="breadcrumb-item"><a href="<?php echo admin_url('home'); ?>">Trang chủ</a></li>
			<li class="breadcrumb-item active">Duyệt đánh giá sản phẩm</li>
		</ol>
	</nav>
</div>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
	<h1 class="h4 mb-0">Duyệt đánh giá sản phẩm</h1>
	<form class="d-flex flex-wrap gap-2" method="get" action="<?php echo admin_url('product-reviews'); ?>">
		<input type="search" name="q" class="form-control form-control-sm" placeholder="Tìm SP, khách, nội dung..." value="<?php echo html_escape($keyword); ?>">
		<select name="status" class="form-select form-select-sm">
			<option value="pending"<?php echo ($filter_status === 'pending') ? ' selected' : ''; ?>>Chờ duyệt (<?php echo (int) $counts['pending']; ?>)</option>
			<option value="approved"<?php echo ($filter_status === 'approved') ? ' selected' : ''; ?>>Đã duyệt (<?php echo (int) $counts['approved']; ?>)</option>
			<option value="rejected"<?php echo ($filter_status === 'rejected') ? ' selected' : ''; ?>>Từ chối (<?php echo (int) $counts['rejected']; ?>)</option>
			<option value="all"<?php echo ($filter_status === 'all') ? ' selected' : ''; ?>>Tất cả</option>
		</select>
		<button type="submit" class="btn btn-outline-secondary btn-sm">Lọc</button>
	</form>
</div>

<div class="admin-card">
	<div class="table-responsive">
		<table class="table table-sm align-middle mb-0">
			<thead>
				<tr>
					<th>Thời gian</th>
					<th>Sản phẩm</th>
					<th>Khách</th>
					<th>Sao</th>
					<th>Nội dung</th>
					<th>Trạng thái</th>
					<th class="text-end">Thao tác</th>
				</tr>
			</thead>
			<tbody>
			<?php if (empty($list)) { ?>
				<tr><td colspan="7" class="text-center text-muted py-4">Không có đánh giá nào.</td></tr>
			<?php } ?>
			<?php foreach ($list as $row) {
				$status = isset($row->status) ? (int) $row->status : Product_review_model::STATUS_PENDING;
				$product_url = build_product_url((object) array('id' => (int) $row->product_id, 'name' => !empty($row->product_name) ? $row->product_name : ('SP #' . (int) $row->product_id)));
			?>
				<tr>
					<td><?php echo date('d/m/Y H:i', (int) $row->created); ?></td>
					<td>
						<a href="<?php echo html_escape($product_url); ?>" target="_blank" rel="noopener">
							<?php echo html_escape(!empty($row->product_name) ? $row->product_name : ('SP #' . (int) $row->product_id)); ?>
						</a>
					</td>
					<td><?php echo html_escape($row->user_name); ?></td>
					<td><?php echo (int) $row->stars; ?>/5</td>
					<td style="max-width: 360px;">
						<div class="text-break"><?php echo nl2br(html_escape(isset($row->comment) ? $row->comment : '')); ?></div>
					</td>
					<td><?php echo html_escape($this->product_review_model->status_label($status)); ?></td>
					<td class="text-end">
						<div class="d-inline-flex flex-wrap gap-1 justify-content-end">
							<?php if ($status !== Product_review_model::STATUS_APPROVED) { ?>
							<form method="post" action="<?php echo admin_url('product-reviews/approve/' . (int) $row->id); ?>">
								<?php echo csrf_field(); ?>
								<button type="submit" class="btn btn-success btn-sm">Duyệt</button>
							</form>
							<?php } ?>
							<?php if ($status !== Product_review_model::STATUS_REJECTED) { ?>
							<form method="post" action="<?php echo admin_url('product-reviews/reject/' . (int) $row->id); ?>">
								<?php echo csrf_field(); ?>
								<button type="submit" class="btn btn-outline-warning btn-sm">Từ chối</button>
							</form>
							<?php } ?>
							<form method="post" action="<?php echo admin_url('product-reviews/delete/' . (int) $row->id); ?>" onsubmit="return confirm('Xóa đánh giá này?');">
								<?php echo csrf_field(); ?>
								<button type="submit" class="btn btn-outline-danger btn-sm">Xóa</button>
							</form>
						</div>
					</td>
				</tr>
			<?php } ?>
			</tbody>
		</table>
	</div>
</div>
