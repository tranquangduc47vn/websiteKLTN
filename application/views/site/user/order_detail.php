<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$status_class = 'jm-account-status--wait';
$status_label = 'Chờ xử lý';
switch ((string) $transaction->status) {
	case '0':
		$status_class = 'jm-account-status--wait';
		$status_label = 'Chờ xử lý';
		break;
	case '1':
		$status_class = 'jm-account-status--ok';
		$status_label = 'Đã xác nhận';
		break;
	case '2':
		$status_class = 'jm-account-status--ship';
		$status_label = 'Đang giao';
		break;
	case '3':
		$status_class = 'jm-account-status--done';
		$status_label = 'Hoàn thành';
		break;
	case '4':
		$status_class = 'jm-account-status--cancel';
		$status_label = 'Đã hủy';
		break;
}

$discount = isset($transaction->discount_amount) ? (int) $transaction->discount_amount : 0;
$voucher_code = isset($transaction->voucher_code) ? trim((string) $transaction->voucher_code) : '';
$subtotal = 0;
foreach ($order_lines as $line) {
	$subtotal += (float) $line->amount;
}
?>
<div class="jm-account-order-detail">
	<div class="jm-account-order-detail__head">
		<p class="jm-account-order-detail__id">Đơn hàng #<?php echo (int) $transaction->id; ?></p>
		<span class="jm-account-status <?php echo $status_class; ?>"><?php echo $status_label; ?></span>
	</div>

	<dl class="jm-account-order-detail__meta">
		<div>
			<dt>Ngày đặt</dt>
			<dd><?php echo mdate('%H:%i %d/%m/%Y', $transaction->created); ?></dd>
		</div>
		<div>
			<dt>Người nhận</dt>
			<dd><?php echo htmlspecialchars($transaction->user_name, ENT_QUOTES, 'UTF-8'); ?></dd>
		</div>
		<div>
			<dt>SĐT nhận</dt>
			<dd><?php echo htmlspecialchars($transaction->user_phone, ENT_QUOTES, 'UTF-8'); ?></dd>
		</div>
		<div>
			<dt>Địa chỉ giao</dt>
			<dd><?php echo htmlspecialchars($transaction->user_address, ENT_QUOTES, 'UTF-8'); ?></dd>
		</div>
		<?php if (!empty($transaction->payment)) { ?>
		<div>
			<dt>Thanh toán</dt>
			<dd><?php echo htmlspecialchars($transaction->payment, ENT_QUOTES, 'UTF-8'); ?></dd>
		</div>
		<?php } ?>
		<?php if (!empty($transaction->message)) { ?>
		<div class="jm-account-order-detail__meta--full">
			<dt>Ghi chú</dt>
			<dd><?php echo nl2br(htmlspecialchars($transaction->message, ENT_QUOTES, 'UTF-8')); ?></dd>
		</div>
		<?php } ?>
		<?php if ((string) $transaction->status === '4' && !empty($transaction->reason)) { ?>
		<div class="jm-account-order-detail__meta--full">
			<dt>Lý do hủy</dt>
			<dd><?php echo htmlspecialchars($transaction->reason, ENT_QUOTES, 'UTF-8'); ?></dd>
		</div>
		<?php } ?>
	</dl>

	<?php if (!empty($order_lines)) { ?>
		<div class="jm-account-order-detail__items">
			<h3 class="jm-account-order-detail__items-title">Sản phẩm đã đặt</h3>
			<ul class="jm-account-order-detail__list">
				<?php foreach ($order_lines as $line) {
					$img = product_image_url($line->image_link);
					$product_url = $line->product_id > 0 ? build_product_url($line->product_id) : '#';
					$size = trim((string) $line->size);
					$color = trim((string) $line->color);
				?>
					<li class="jm-account-order-detail__item">
						<a href="<?php echo $product_url; ?>" class="jm-account-order-detail__thumb">
							<img src="<?php echo $img; ?>" alt="">
						</a>
						<div class="jm-account-order-detail__info">
							<a href="<?php echo $product_url; ?>" class="jm-account-order-detail__name">
								<?php echo htmlspecialchars(product_display_name($line->name), ENT_QUOTES, 'UTF-8'); ?>
							</a>
							<?php if ($size !== '' || $color !== '') { ?>
								<p class="jm-account-order-detail__variant">
									<?php if ($size !== '') { ?><span>Size: <?php echo htmlspecialchars($size, ENT_QUOTES, 'UTF-8'); ?></span><?php } ?>
									<?php if ($color !== '') { ?><span>Màu: <?php echo htmlspecialchars($color, ENT_QUOTES, 'UTF-8'); ?></span><?php } ?>
								</p>
							<?php } ?>
							<p class="jm-account-order-detail__qty">Số lượng: <?php echo (int) $line->qty; ?></p>
						</div>
						<div class="jm-account-order-detail__price">
							<?php echo number_format($line->amount); ?> ₫
						</div>
					</li>
				<?php } ?>
			</ul>
		</div>
	<?php } else { ?>
		<p class="jm-account-order-detail__empty">Không có dòng sản phẩm trong đơn này.</p>
	<?php } ?>

	<div class="jm-account-order-detail__summary">
		<?php if ($discount > 0 || $voucher_code !== '') { ?>
			<div class="jm-account-order-detail__summary-row">
				<span>Tạm tính</span>
				<strong><?php echo number_format($subtotal); ?> ₫</strong>
			</div>
			<?php if ($voucher_code !== '') { ?>
				<div class="jm-account-order-detail__summary-row">
					<span>Voucher</span>
					<strong><?php echo htmlspecialchars($voucher_code, ENT_QUOTES, 'UTF-8'); ?></strong>
				</div>
			<?php } ?>
			<?php if ($discount > 0) { ?>
				<div class="jm-account-order-detail__summary-row">
					<span>Giảm giá</span>
					<strong>-<?php echo number_format($discount); ?> ₫</strong>
				</div>
			<?php } ?>
		<?php } ?>
		<div class="jm-account-order-detail__summary-row jm-account-order-detail__summary-row--total">
			<span>Tổng thanh toán</span>
			<strong><?php echo number_format($transaction->amount); ?> ₫</strong>
		</div>
	</div>
</div>
