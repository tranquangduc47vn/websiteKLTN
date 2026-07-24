<div class="admin-breadcrumb">
	<nav aria-label="breadcrumb">
		<ol class="breadcrumb">
			<li class="breadcrumb-item"><a href="<?php echo admin_url('home'); ?>"><i class="fa-solid fa-house me-1"></i> Trang chủ</a></li>
			<li class="breadcrumb-item active" aria-current="page">FAQ trợ lý AI</li>
		</ol>
	</nav>
</div>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
	<p class="admin-page-subtitle mb-0">AI ưu tiên trả lời từ FAQ trước khi gọi mô hình AI.</p>
	<a href="<?php echo admin_url('ai-assistant/faq/add'); ?>" class="btn btn-primary"><i class="fa-solid fa-plus me-1"></i> Thêm FAQ</a>
</div>

<div class="admin-card">
	<div class="admin-card-body table-responsive">
		<table class="table table-hover admin-table">
			<thead>
				<tr>
					<th>Câu hỏi</th>
					<th>Danh mục</th>
					<th>Thứ tự</th>
					<th>Trạng thái</th>
					<th class="text-center">Thao tác</th>
				</tr>
			</thead>
			<tbody>
				<?php if (!empty($list)) { ?>
					<?php foreach ($list as $row) { ?>
						<tr>
							<td><?php echo htmlspecialchars($row->question, ENT_QUOTES, 'UTF-8'); ?></td>
							<td><?php echo htmlspecialchars($row->category, ENT_QUOTES, 'UTF-8'); ?></td>
							<td><?php echo (int) $row->sort_order; ?></td>
							<td data-faq-status="<?php echo (int) $row->id; ?>"><?php echo (int) $row->is_active === 1 ? 'Đang bật' : 'Tắt'; ?></td>
							<td class="text-center">
								<div class="admin-voucher-actions">
									<label class="form-check form-switch admin-voucher-switch mb-0" title="Bật / tắt FAQ">
										<input type="checkbox" class="form-check-input js-faq-toggle" role="switch"
											data-url="<?php echo admin_url('ai-assistant/faq/toggle_active/' . (int) $row->id); ?>"
											<?php echo (int) $row->is_active === 1 ? 'checked' : ''; ?>>
									</label>
									<a class="btn btn-sm btn-outline-primary" href="<?php echo admin_url('ai-assistant/faq/edit/' . (int) $row->id); ?>" title="Sửa"><i class="fa-solid fa-pen"></i></a>
									<?php echo admin_csrf_post_button(admin_url('ai-assistant/faq/delete/' . (int) $row->id), '<i class="fa-solid fa-trash"></i>', array('title' => 'Xóa', 'confirm' => 'Xóa FAQ này?')); ?>
								</div>
							</td>
						</tr>
					<?php } ?>
				<?php } else { ?>
					<tr><td colspan="5" class="admin-empty">Chưa có FAQ nào.</td></tr>
				<?php } ?>
			</tbody>
		</table>
	</div>
</div>

<script>
(function () {
	document.querySelectorAll('.js-faq-toggle').forEach(function (input) {
		input.addEventListener('change', function () {
			var url = input.getAttribute('data-url');
			var row = input.closest('tr');
			var statusCell = row ? row.querySelector('[data-faq-status]') : null;
			var prev = !input.checked;

			var body = '';
			if (window.CI_CSRF) {
				body = encodeURIComponent(window.CI_CSRF.name) + '=' + encodeURIComponent(window.CI_CSRF.hash);
			}

			fetch(url, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded',
					'X-Requested-With': 'XMLHttpRequest'
				},
				body: body,
			})
				.then(function (res) { return res.json(); })
				.then(function (data) {
					if (typeof window.applyCsrfResponse === 'function') {
						window.applyCsrfResponse(data);
					}
					if (!data.ok) {
						input.checked = prev;
						return;
					}
					if (statusCell && data.label) {
						statusCell.textContent = data.label;
					}
				})
				.catch(function () {
					input.checked = prev;
				});
		});
	});
})();
</script>
