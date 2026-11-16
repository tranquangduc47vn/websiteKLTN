@extends('layouts.admin.app')

@section('content')
<nav aria-label="breadcrumb" class="admin-breadcrumb">
	<ol class="breadcrumb mb-0">
		<li class="breadcrumb-item"><a href="{{ url('admin') }}">Trang chủ</a></li>
		<li class="breadcrumb-item active">{{ $heading }}</li>
	</ol>
</nav>
<div class="admin-page-header d-flex flex-wrap justify-content-between align-items-start gap-2">
	<div>
		<h1 class="admin-page-title">{{ $heading }}</h1>
	</div>
	<a class="btn btn-primary" href="{{ url(ltrim($base, '/') . '/add') }}">Thêm mới</a>
</div>
<div class="admin-card">
	<div class="table-responsive adm-table-wrap">
		<table class="table admin-table table-hover mb-0">
			<thead><tr><th>Tiêu đề</th><th>Slug</th><th></th></tr></thead>
			<tbody>
				@forelse ($list as $row)
					<tr>
						<td>{{ $row->title }}</td>
						<td><code>{{ $row->slug }}</code></td>
						<td>
							<div class="adm-actions">
								<a class="btn btn-sm btn-outline-primary" href="{{ url(ltrim($base, '/') . '/edit/' . $row->id) }}">Sửa</a>
								<form method="post" action="{{ url(ltrim($base, '/') . '/delete/' . $row->id) }}" data-adm-confirm="Xóa bản ghi này?">
									@csrf
									<button class="btn btn-sm btn-outline-danger" type="submit">Xóa</button>
								</form>
							</div>
						</td>
					</tr>
				@empty
					<tr><td colspan="3" class="admin-empty">Chưa có bản ghi.</td></tr>
				@endforelse
			</tbody>
		</table>
	</div>
</div>
@endsection
