<div class="admin-breadcrumb">
	<nav aria-label="breadcrumb">
		<ol class="breadcrumb">
			<li class="breadcrumb-item"><a href="<?php echo admin_url('home'); ?>"><i class="fa-solid fa-house me-1"></i> Trang chủ</a></li>
			<li class="breadcrumb-item active" aria-current="page">Logo website</li>
		</ol>
	</nav>
</div>

<p class="admin-page-subtitle mb-3">Thay logo hiển thị trên header storefront và các vị trí dùng logo mặc định.</p>

<div class="row g-3">
	<div class="col-lg-5">
		<div class="admin-card h-100">
			<div class="admin-card-body">
				<h2 class="h6 mb-3">Logo hiện tại</h2>
				<?php if (!empty($logo_exists)) { ?>
					<div class="border rounded bg-white p-3 text-center mb-3">
						<img src="<?php echo htmlspecialchars($logo_url, ENT_QUOTES, 'UTF-8'); ?>?v=<?php echo time(); ?>" alt="Logo website" style="max-width:100%;max-height:120px;height:auto;">
					</div>
					<p class="small text-muted mb-0">File: <code>upload/<?php echo htmlspecialchars($logo_path, ENT_QUOTES, 'UTF-8'); ?></code></p>
				<?php } else { ?>
					<p class="text-muted mb-0">Chưa có logo hoặc file không tồn tại.</p>
				<?php } ?>
			</div>
		</div>
	</div>

	<div class="col-lg-7">
		<div class="admin-card">
			<div class="admin-card-body">
				<h2 class="h6 mb-3">Upload logo mới</h2>
				<form class="admin-form" action="" method="post" enctype="multipart/form-data">
					<div class="mb-3">
						<label class="form-label" for="site-logo-file">Chọn hình logo</label>
						<input type="file" class="form-control" id="site-logo-file" name="logo" accept="image/png,image/jpeg,image/webp,image/gif" required>
						<div class="form-text">PNG nền trong suốt khuyến nghị. Tối đa 5MB. Kích thước gợi ý: cao khoảng 98px (header tự co giữ tỷ lệ).</div>
					</div>
					<div class="admin-form-actions">
						<button type="submit" name="submit" value="1" class="btn btn-primary">Lưu logo</button>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>
