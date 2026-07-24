<div class="admin-breadcrumb">
	<nav aria-label="breadcrumb">
		<ol class="breadcrumb">
			<li class="breadcrumb-item">
				<a href="<?php echo admin_url('home'); ?>"><i class="fa-solid fa-house me-1"></i> Trang chủ</a>
			</li>
			<li class="breadcrumb-item active" aria-current="page">Danh mục</li>
		</ol>
	</nav>
</div>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
	<p class="admin-page-subtitle mb-0">Danh sách danh mục sản phẩm và thứ tự hiển thị.</p>
	<a href="<?php echo admin_url('catalog/add'); ?>" class="btn btn-primary">
		<i class="fa-solid fa-plus me-1"></i> Thêm mới
	</a>
</div>

<div class="admin-card">
	<div class="admin-card-header">
		<span><i class="fa-solid fa-folder-tree me-2 text-primary"></i>Danh sách</span>
		<div class="admin-toolbar">
			<div class="admin-search">
				<i class="fa-solid fa-search"></i>
				<input type="search" class="form-control" placeholder="Tìm danh mục..."
					data-admin-table-search="catalogListTable" aria-label="Tìm danh mục">
			</div>
		</div>
	</div>
	<div class="admin-card-body">

		<div class="table-responsive">
			<table class="table table-hover admin-table" id="catalogListTable">
				<thead>
					<tr>
						<th style="width: 80px;">ID</th>
						<th>Tên danh mục</th>
						<th style="width: 120px;">Parent ID</th>
						<th style="width: 100px;">Thứ tự</th>
						<th style="width: 120px;" class="text-center">Hành động</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($list as $value) { ?>
						<tr>
							<td><span class="fw-semibold"><?php echo $value->id; ?></span></td>
							<td><?php echo htmlspecialchars($value->name, ENT_QUOTES, 'UTF-8'); ?></td>
							<td><?php echo $value->parent_id; ?></td>
							<td><?php echo $value->sort_order; ?></td>
							<td class="text-center">
								<a class="btn btn-sm btn-outline-primary me-1"
									href="<?php echo admin_url('catalog/edit/' . $value->id); ?>"
									title="Sửa">
									<i class="fa-solid fa-pen"></i>
								</a>
								<?php if ($this->session->userdata('login')->level == ROLE_ADMIN) { ?>
									<?php echo admin_csrf_post_button(
										admin_url('catalog/del/' . $value->id),
										'<i class="fa-solid fa-trash"></i>',
										array(
											'title' => 'Xóa',
											'confirm' => 'Bạn chắc chắn muốn xóa danh mục này? Việc này có thể ảnh hưởng đến các sản phẩm bên trong!',
										)
									); ?>
								<?php } ?>
							</td>
						</tr>
					<?php } ?>
				</tbody>
			</table>
		</div>
	</div>
</div>
