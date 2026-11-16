@extends('layouts.admin.app')

@section('content')
<nav aria-label="breadcrumb" class="admin-breadcrumb">
	<ol class="breadcrumb mb-0">
		<li class="breadcrumb-item"><a href="{{ url('admin') }}">Trang chủ</a></li>
		<li class="breadcrumb-item active">Slider</li>
	</ol>
</nav>
<div class="admin-page-header d-flex flex-wrap justify-content-between align-items-start gap-2">
	<div>
		<h1 class="admin-page-title">Slider</h1>
		<p class="admin-page-subtitle">Ảnh trượt trên trang chủ.</p>
	</div>
	<a class="btn btn-primary" href="{{ url('admin/slider/add') }}">Thêm slider</a>
</div>
<div class="admin-card">
	<div class="table-responsive adm-table-wrap">
		<table class="table admin-table table-hover mb-0" id="sliderListTable">
			<thead><tr><th>Tên</th><th>Hình ảnh</th><th>Liên kết</th><th>Thứ tự</th><th></th></tr></thead>
			<tbody>
				@forelse ($list as $row)
					<tr>
						<td>{{ $row->name }}</td>
						<td>@if ($row->image_link)<img class="adm-media" src="{{ url('upload/slider/' . $row->image_link) }}" alt="">@endif</td>
						<td>{{ $row->link }}</td>
						<td>{{ $row->sort_order }}</td>
						<td>
							<div class="adm-actions">
								<a class="btn btn-sm btn-outline-primary" href="{{ url('admin/slider/edit/' . $row->id) }}">Sửa</a>
								<form method="post" action="{{ url('admin/slider/del/' . $row->id) }}" data-adm-confirm="Xóa slider này?">
									@csrf
									<button class="btn btn-sm btn-outline-danger" type="submit">Xóa</button>
								</form>
							</div>
						</td>
					</tr>
				@empty
					<tr><td colspan="5" class="admin-empty">Chưa có slider.</td></tr>
				@endforelse
			</tbody>
		</table>
	</div>
</div>
@endsection
