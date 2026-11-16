@extends('layouts.admin.app')

@section('content')
<nav aria-label="breadcrumb" class="admin-breadcrumb">
	<ol class="breadcrumb mb-0">
		<li class="breadcrumb-item"><a href="{{ url('admin') }}">Trang chủ</a></li>
		<li class="breadcrumb-item active">Cửa hàng</li>
	</ol>
</nav>
<div class="admin-page-header d-flex flex-wrap justify-content-between align-items-start gap-2">
	<div>
		<h1 class="admin-page-title">Cửa hàng</h1>
		<p class="admin-page-subtitle">Điểm bán hiển thị trên trang hệ thống cửa hàng.</p>
	</div>
	<a class="btn btn-primary" href="{{ url('admin/store/add') }}">Thêm cửa hàng</a>
</div>
<div class="admin-card">
	<div class="table-responsive adm-table-wrap">
		<table class="table admin-table table-hover mb-0">
			<thead><tr><th>Tên</th><th>Thành phố</th><th>Địa chỉ</th><th>Điện thoại</th><th></th></tr></thead>
			<tbody>
				@forelse ($list as $row)
					<tr>
						<td>{{ $row->name }}</td>
						<td>{{ $row->city }}</td>
						<td>{{ $row->address }}</td>
						<td>{{ $row->phone }}</td>
						<td>
							<div class="adm-actions">
								<a class="btn btn-sm btn-outline-primary" href="{{ url('admin/store/edit/' . $row->id) }}">Sửa</a>
								<form method="post" action="{{ url('admin/store/delete/' . $row->id) }}" data-adm-confirm="Xóa cửa hàng này?">
									@csrf
									<button class="btn btn-sm btn-outline-danger" type="submit">Xóa</button>
								</form>
							</div>
						</td>
					</tr>
				@empty
					<tr><td colspan="5" class="admin-empty">Chưa có cửa hàng.</td></tr>
				@endforelse
			</tbody>
		</table>
	</div>
</div>
@endsection
