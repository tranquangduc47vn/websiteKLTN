@if (!empty($flash['success']))
	<div class="alert alert-success alert-dismissible fade show jm-store-flash" role="alert">
		{{ $flash['success'] }}
		<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Đóng"></button>
	</div>
@endif
@if (!empty($flash['fail']))
	<div class="alert alert-danger alert-dismissible fade show jm-store-flash" role="alert">
		{{ $flash['fail'] }}
		<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Đóng"></button>
	</div>
@endif
@if (!empty($flash['info']))
	<div class="alert alert-info alert-dismissible fade show jm-store-flash" role="alert">
		{{ $flash['info'] }}
		<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Đóng"></button>
	</div>
@endif
