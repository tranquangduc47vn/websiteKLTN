@extends('layouts.admin.app')

@section('content')
<nav aria-label="breadcrumb" class="admin-breadcrumb">
	<ol class="breadcrumb mb-0">
		<li class="breadcrumb-item"><a href="{{ url('admin') }}">Trang chủ</a></li>
		<li class="breadcrumb-item active">Tin tức</li>
	</ol>
</nav>
<div class="admin-page-header d-flex flex-wrap justify-content-between align-items-start gap-2">
	<div>
		<h1 class="admin-page-title">Tin tức</h1>
		<p class="admin-page-subtitle">Bài viết hiển thị trên trang tin.</p>
	</div>
	<a class="btn btn-primary" href="{{ url('admin/posts/add') }}">Thêm mới</a>
</div>
<div class="admin-card">
	<div class="table-responsive adm-table-wrap">
		<table class="table admin-table table-hover mb-0" id="newsListTable">
			<thead><tr><th>Ảnh</th><th>Tiêu đề</th><th>Ngày</th><th></th></tr></thead>
			<tbody>
				@forelse ($list as $row)
					<tr>
						<td>
							@if ($row->image)
								<img class="adm-media" src="{{ url('upload/news/' . $row->image) }}" alt="">
							@endif
						</td>
						<td>{{ $row->title }}</td>
						<td>{{ $row->created_at ? $row->created_at->format('d/m/Y H:i') : '' }}</td>
						<td>
							<div class="adm-actions">
								<a class="btn btn-sm btn-outline-primary" href="{{ url('admin/posts/edit/' . $row->id) }}">Sửa</a>
								<form method="post" action="{{ url('admin/posts/del/' . $row->id) }}" data-adm-confirm="Bạn chắc chắn muốn xóa bài viết này?">
									@csrf
									<button class="btn btn-sm btn-outline-danger" type="submit">Xóa</button>
								</form>
							</div>
						</td>
					</tr>
				@empty
					<tr><td colspan="4" class="admin-empty">Chưa có bài viết.</td></tr>
				@endforelse
			</tbody>
		</table>
	</div>
</div>
@endsection
