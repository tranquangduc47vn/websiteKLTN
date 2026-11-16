@extends('layouts.admin.app')

@section('content')
<nav aria-label="breadcrumb" class="admin-breadcrumb">
	<ol class="breadcrumb mb-0">
		<li class="breadcrumb-item"><a href="{{ url('admin') }}">Trang chủ</a></li>
		<li class="breadcrumb-item active">Trang tĩnh</li>
	</ol>
</nav>
<div class="admin-page-header d-flex flex-wrap justify-content-between align-items-start gap-2">
	<div>
		<h1 class="admin-page-title">Trang tĩnh</h1>
		<p class="admin-page-subtitle">Giới thiệu, chính sách và các trang nội dung.</p>
	</div>
	<a class="btn btn-primary" href="{{ url('admin/pages/add') }}">Thêm trang</a>
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
						<td><a class="btn btn-sm btn-outline-primary" href="{{ url('admin/pages/edit/' . $row->id) }}">Sửa</a></td>
					</tr>
				@empty
					<tr><td colspan="3" class="admin-empty">Chưa có trang.</td></tr>
				@endforelse
			</tbody>
		</table>
	</div>
</div>
@endsection
