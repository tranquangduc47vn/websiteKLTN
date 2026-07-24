<?php
$msg_success = $this->session->flashdata('admin_message_success');
$msg_fail = $this->session->flashdata('admin_message_fail');
$msg_info = $this->session->flashdata('admin_message');
?>
<div class="admin-flash-stack">
	<?php if ($msg_success) { ?>
		<div class="alert alert-success alert-dismissible fade show" role="alert" data-admin-auto-dismiss="5000">
			<i class="fa-solid fa-circle-check me-2"></i>
			<strong>Thành công!</strong> <?php echo htmlspecialchars($msg_success, ENT_QUOTES, 'UTF-8'); ?>
			<button type="button" class="btn-close" data-bs-dismiss="alert" data-admin-dismiss="alert" aria-label="Đóng"></button>
		</div>
	<?php } ?>
	<?php if ($msg_fail) { ?>
		<div class="alert alert-danger alert-dismissible fade show" role="alert" data-admin-auto-dismiss="5000">
			<i class="fa-solid fa-triangle-exclamation me-2"></i>
			<strong>Lỗi!</strong> <?php echo htmlspecialchars($msg_fail, ENT_QUOTES, 'UTF-8'); ?>
			<button type="button" class="btn-close" data-bs-dismiss="alert" data-admin-dismiss="alert" aria-label="Đóng"></button>
		</div>
	<?php } ?>
	<?php if (!$msg_success && !$msg_fail && $msg_info) { ?>
		<div class="alert alert-info alert-dismissible fade show" role="alert" data-admin-auto-dismiss="5000">
			<i class="fa-solid fa-circle-info me-2"></i>
			<?php echo htmlspecialchars($msg_info, ENT_QUOTES, 'UTF-8'); ?>
			<button type="button" class="btn-close" data-bs-dismiss="alert" data-admin-dismiss="alert" aria-label="Đóng"></button>
		</div>
	<?php } ?>
</div>
