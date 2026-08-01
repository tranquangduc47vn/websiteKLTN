<?php
$product = isset($product) ? $product : null;
$variants = isset($variants) ? $variants : array();
if (!$product) {
	echo '<p class="text-danger">Không có dữ liệu sản phẩm.</p>';
	return;
}
$this->load->view('admin/product/edit', array(
	'product' => $product,
	'variants' => $variants,
	'catalog' => isset($catalog) ? $catalog : array(),
	'available_colors' => isset($available_colors) ? $available_colors : product_master_colors(),
	'available_sizes' => isset($available_sizes) ? $available_sizes : product_master_sizes(),
));
