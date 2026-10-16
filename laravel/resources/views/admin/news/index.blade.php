@extends('layouts.admin.app')

@section('content')
<nav aria-label="breadcrumb" class="mb-3">
	<ol class="breadcrumb mb-0">
		<li class="breadcrumb-item"><a href="{{ url('admin') }}">Trang chủ</a></li>
		<li class="breadcrumb-item active">Tin tức</li>
	</ol>
</nav>
<div class="d-flex justify-content-end mb-3">
	<a class="btn btn-primary" href="{{ url('admin/posts/add') }}">Thêm mới</a>
</div>
<table class="table admin-table" id="newsListTable">
	<thead><tr><th>Ảnh</th><th>Tiêu đề</th><th>Ngày</th><th></th></tr></thead>
	<tbody>
		@foreach ($list as $row)
			<tr>
				<td>
					@if ($row->image)
						<img src="{{ url('upload/news/' . $row->image) }}" width="80" height="50" alt="">
					@endif
				</td>
				<td>{{ $row->title }}</td>
				<td>{{ $row->created_at ? $row->created_at->format('d/m/Y H:i') : '' }}</td>
				<td class="text-nowrap">
					<a class="btn btn-sm btn-outline-primary" href="{{ url('admin/posts/edit/' . $row->id) }}">Sửa</a>
					<form method="post" action="{{ url('admin/posts/del/' . $row->id) }}" class="d-inline" onsubmit="return confirm('Bạn chắc chắn muốn xóa bài viết này?');">
						@csrf
						<button class="btn btn-sm btn-outline-danger" type="submit">Xóa</button>
					</form>
				</td>
			</tr>
		@endforeach
	</tbody>
</table>
@endsection
