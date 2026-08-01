<?php
$admin_asset_url = base_url('assets/admin/');
$admin_footer_minimal = !empty($admin_footer_minimal);
?>
<?php if (!$admin_footer_minimal) { ?>
<script src="<?php echo $admin_asset_url; ?>js/jquery-3.1.1.js"></script>
<?php if (!empty($admin_load_chart)) { ?>
<script src="<?php echo $admin_asset_url; ?>js/chart.min.js"></script>
<?php } ?>
<script src="<?php echo $admin_asset_url; ?>js/bootstrap-datepicker.js"></script>
<?php } ?>
<script src="<?php echo $admin_asset_url; ?>js/bootstrap5.bundle.min.js"></script>
<?php echo csrf_form_inject_script(); ?>
<?php
$poll_login = $this->session->userdata('login');
if ($poll_login && function_exists('admin_can') && admin_can('order.manage', $poll_login)) {
	$poll_orders_url = admin_url('orders/pending_count');
?>
<script>window.WEBSHOP_ADMIN_ORDERS_POLL_URL = <?php echo json_encode($poll_orders_url, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;</script>
<?php } ?>
<script src="<?php echo $admin_asset_url; ?>js/admin-layout.js?v=10"></script>
