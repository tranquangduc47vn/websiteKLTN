@extends('layouts.admin.app')

@section('content')
<nav aria-label="breadcrumb" class="admin-breadcrumb">
	<ol class="breadcrumb mb-0">
		<li class="breadcrumb-item"><a href="{{ url('admin') }}">Trang chủ</a></li>
		<li class="breadcrumb-item active">Banner danh mục</li>
	</ol>
</nav>
<div class="admin-page-header">
	<div>
		<h1 class="admin-page-title">Banner danh mục</h1>
		<p class="admin-page-subtitle">Tổng số: {{ $list->count() }} banner</p>
	</div>
</div>
<div class="admin-card">
	<div class="table-responsive adm-table-wrap">
		<table class="table admin-table table-hover mb-0" id="bannerListTable">
			<thead><tr><th>STT</th><th>Hình ảnh</th><th>Tên</th><th>Link</th><th></th></tr></thead>
			<tbody>
				@forelse ($list as $row)
					<tr>
						<td>{{ $row->sort_order }}</td>
						<td>
							@if ($row->image_link)
								<img class="adm-media" src="{{ url('upload/slider/' . $row->image_link) }}" alt="">
							@endif
						</td>
						<td>{{ $row->name }}</td>
						<td><code>{{ $row->link }}</code></td>
						<td><a class="btn btn-sm btn-outline-primary" href="{{ url('admin/banner/edit/' . $row->id) }}">Sửa</a></td>
					</tr>
				@empty
					<tr><td colspan="5" class="admin-empty">Chưa có banner.</td></tr>
				@endforelse
			</tbody>
		</table>
	</div>
</div>
@endsection
