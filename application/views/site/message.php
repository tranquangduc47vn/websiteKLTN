<?php
$store = get_store_flash();
?>
<?php if (!empty($store['success'])) { ?>
	<div class="alert alert-success alert-dismissible fade show jm-store-flash" role="alert">
		<?php echo htmlspecialchars($store['success'], ENT_QUOTES, 'UTF-8'); ?>
		<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Đóng"></button>
	</div>
<?php } ?>
<?php if (!empty($store['fail'])) { ?>
	<div class="alert alert-danger alert-dismissible fade show jm-store-flash" role="alert">
		<?php echo htmlspecialchars($store['fail'], ENT_QUOTES, 'UTF-8'); ?>
		<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Đóng"></button>
	</div>
<?php } ?>
<?php if (!empty($store['info'])) { ?>
	<div class="alert alert-info alert-dismissible fade show jm-store-flash" role="alert">
		<?php echo htmlspecialchars($store['info'], ENT_QUOTES, 'UTF-8'); ?>
		<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Đóng"></button>
	</div>
<?php } ?>
