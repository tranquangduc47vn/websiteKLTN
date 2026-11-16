@extends('layouts.admin.app')

@section('content')
@php
	$filters = $filters ?? [];
	$query = array_filter([
		'name' => $filters['name'] ?? '',
		'sku' => $filters['sku'] ?? '',
		'catalog_id' => ($filters['catalog_id'] ?? 0) > 0 ? $filters['catalog_id'] : '',
		'stock' => $filters['stock'] ?? '',
	], fn ($value) => $value !== '' && $value !== null);
	$qs = $query === [] ? '' : ('?' . http_build_query($query));
	$per = \App\Http\Controllers\Admin\InventoryController::PER_PAGE;
	$exportQuery = $query + ['offset' => (int) ($offset ?? 0), 'per_page' => $per];
@endphp
<nav aria-label="breadcrumb" class="admin-breadcrumb">
	<ol class="breadcrumb mb-0">
		<li class="breadcrumb-item"><a href="{{ url('admin') }}">Trang chủ</a></li>
		<li class="breadcrumb-item active">Tồn kho</li>
	</ol>
</nav>
<div class="admin-page-header d-flex flex-wrap justify-content-between align-items-start gap-2">
	<div>
		<h1 class="admin-page-title">Tồn kho theo biến thể</h1>
		<p class="admin-page-subtitle">Hết hàng khi tồn = 0. Sắp hết khi tồn &gt; 0 và tồn ≤ mức tối thiểu của biến thể. Còn hàng khi tồn &gt; 0.</p>
	</div>
	<div class="d-flex flex-wrap gap-2">
		<a class="btn btn-outline-primary btn-sm" href="{{ url('admin/receipts') }}">Phiếu nhập</a>
		<a class="btn btn-outline-primary btn-sm" href="{{ url('admin/stock-movements') }}">Lịch sử biến động</a>
	</div>
</div>
<div class="row g-3 mb-3">
	<div class="col-6 col-lg-3">
		<div class="admin-stat-card">
			<div><div class="stat-value">{{ number_format($stats['total']) }}</div><div class="stat-label">Tổng biến thể</div></div>
			<span class="admin-stat-icon icon-primary"><i class="fa-solid fa-boxes-stacked"></i></span>
		</div>
	</div>
	<div class="col-6 col-lg-3">
		<div class="admin-stat-card">
			<div><div class="stat-value">{{ number_format($stats['out_of_stock']) }}</div><div class="stat-label">Hết hàng</div></div>
			<span class="admin-stat-icon icon-warning"><i class="fa-solid fa-box-open"></i></span>
		</div>
	</div>
	<div class="col-6 col-lg-3">
		<div class="admin-stat-card">
			<div><div class="stat-value">{{ number_format($stats['low_stock']) }}</div><div class="stat-label">Sắp hết</div></div>
			<span class="admin-stat-icon icon-info"><i class="fa-solid fa-triangle-exclamation"></i></span>
		</div>
	</div>
	<div class="col-6 col-lg-3">
		<div class="admin-stat-card">
			<div><div class="stat-value">{{ number_format($stats['stock_value']) }}</div><div class="stat-label">Giá trị tồn (cost)</div></div>
			<span class="admin-stat-icon icon-success"><i class="fa-solid fa-coins"></i></span>
		</div>
	</div>
</div>
<div class="admin-card">
	<div class="admin-card-body">
		<form class="row g-2 align-items-end adm-filter" method="get" action="{{ url('admin/inventory') }}">
			<div class="col-12 col-md-3">
				<label class="form-label" for="inv-name">Tên SP</label>
				<input type="search" name="name" id="inv-name" class="form-control" value="{{ $filters['name'] }}">
			</div>
			<div class="col-12 col-md-2">
				<label class="form-label" for="inv-sku">SKU</label>
				<input type="search" name="sku" id="inv-sku" class="form-control" value="{{ $filters['sku'] }}">
			</div>
			<div class="col-12 col-md-3">
				<label class="form-label" for="inv-catalog">Danh mục</label>
				<select name="catalog_id" id="inv-catalog" class="form-select">
					<option value="">— Tất cả —</option>
					@foreach ($catalogs as $parent)
						@foreach ($parent->sub as $sub)
							<option value="{{ $sub->id }}" @selected((string) $filters['catalog_id'] === (string) $sub->id)>{{ $sub->name }}</option>
						@endforeach
					@endforeach
				</select>
			</div>
			<div class="col-12 col-md-2">
				<label class="form-label" for="inv-stock">Trạng thái</label>
				<select name="stock" id="inv-stock" class="form-select">
					<option value="">Tất cả</option>
					<option value="out" @selected($filters['stock'] === 'out')>Hết hàng</option>
					<option value="low" @selected($filters['stock'] === 'low')>Sắp hết</option>
					<option value="ok" @selected($filters['stock'] === 'ok')>Còn hàng</option>
				</select>
			</div>
			<div class="col-12 col-md-2"><button class="btn btn-primary w-100" type="submit">Lọc</button></div>
		</form>
	</div>
</div>
@include('admin.partials.export-toolbar', ['module' => 'inventory', 'query' => $exportQuery])
<div class="admin-card">
	<div class="table-responsive adm-table-wrap">
		<table class="table admin-table table-hover mb-0">
			<thead>
				<tr>
					<th>SKU</th><th>Sản phẩm</th><th>Màu</th><th>Size</th><th>Tồn</th><th>Trạng thái</th>
					@if ($canCount || $canAdjust)<th></th>@endif
				</tr>
			</thead>
			<tbody>
				@forelse ($rows as $row)
					@php
						$stock = (int) $row->stock;
						$min = (int) $row->min_stock;
						$label = $stock <= 0 ? 'Hết hàng' : ($stock <= $min ? 'Sắp hết' : 'Còn hàng');
						$tone = $stock <= 0 ? 'adm-stock--out' : ($stock <= $min ? 'adm-stock--low' : 'adm-stock--ok');
					@endphp
					<tr>
						<td><code>{{ $row->sku }}</code></td>
						<td>{{ $row->product_name }}</td>
						<td>{{ $row->color !== '' ? $row->color : '—' }}</td>
						<td>{{ $row->size !== '' ? $row->size : '—' }}</td>
						<td>{{ number_format($stock) }}</td>
						<td><span class="admin-status-badge {{ $tone }}">{{ $label }}</span></td>
						@if ($canCount || $canAdjust)
							<td><a class="btn btn-sm btn-outline-primary" href="{{ url('admin/inventory/adjust/'.$row->id) }}">Kiểm kê</a></td>
						@endif
					</tr>
				@empty
					<tr><td colspan="7" class="admin-empty">Không có dữ liệu.</td></tr>
				@endforelse
			</tbody>
		</table>
	</div>
</div>
@if ($total > $per)
	<nav class="admin-table-footer" aria-label="Phân trang tồn kho">
		<ul class="pagination mb-0">
			@for ($i = 0; $i < $total; $i += $per)
				<li class="page-item {{ $i === $offset ? 'active' : '' }}">
					<a class="page-link" href="{{ url('admin/inventory/index/'.$i).$qs }}">{{ (int) ($i / $per) + 1 }}</a>
				</li>
			@endfor
		</ul>
	</nav>
@endif
@endsection
