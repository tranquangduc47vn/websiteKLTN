@extends('layouts.admin.app')

@section('content')
@php
	$isEdit = $product !== null;
	$action = $isEdit ? url('admin/products/edit/'.$product->id) : url('admin/products/add');
	$selectedColors = old('color', $product ? $product->colorList() : []);
	$selectedSizes = old('size', $product ? $product->sizeList() : []);
	$listPrice = $product ? (float) $product->list_price : 0;
	$discountValue = old('discount', ($product && $listPrice > 0) ? (int) round(((float) $product->discount_amount / $listPrice) * 100) : '');
	$gallery = $product ? $product->imageList() : [];
@endphp
<nav aria-label="breadcrumb" class="admin-breadcrumb">
	<ol class="breadcrumb mb-0">
		<li class="breadcrumb-item"><a href="{{ url('admin/products') }}">Sản phẩm</a></li>
		<li class="breadcrumb-item active">{{ $isEdit ? 'Chỉnh sửa sản phẩm' : 'Thêm sản phẩm' }}</li>
	</ol>
</nav>
<div class="admin-page-header">
	<h1 class="admin-page-title">{{ $isEdit ? 'Chỉnh sửa sản phẩm' : 'Thêm sản phẩm' }}</h1>
	<p class="admin-page-subtitle">Thông tin, giá, ảnh, màu, size và biến thể dùng cùng quy tắc lưu hiện tại.</p>
</div>
<form method="post" action="{{ $action }}" enctype="multipart/form-data" class="admin-form adm-form">
	@csrf
	<div class="admin-card">
		<div class="admin-card-header"><span class="adm-section-title">Thông tin cơ bản</span></div>
		<div class="admin-card-body">
			<div class="mb-3">
				<label for="name" class="form-label">Tên sản phẩm</label>
				<input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror" maxlength="255" value="{{ old('name', $product->name ?? '') }}">
				@error('name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
			</div>
			<div class="mb-0">
				<label for="catalog_id" class="form-label">Danh mục</label>
				<select name="catalog_id" id="catalog_id" class="form-select @error('catalog_id') is-invalid @enderror">
					<option value="">Chọn danh mục</option>
					@foreach ($catalog as $group)
						@if (! empty($group->sub))
							<optgroup label="{{ $group->name }}">
								@foreach ($group->sub as $child)
									<option value="{{ $child->id }}" @selected((string) old('catalog_id', $product->catalog_id ?? '') === (string) $child->id)>{{ $child->name }}</option>
								@endforeach
							</optgroup>
						@else
							<option value="{{ $group->id }}" @selected((string) old('catalog_id', $product->catalog_id ?? '') === (string) $group->id)>{{ $group->name }}</option>
						@endif
					@endforeach
				</select>
				@error('catalog_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
			</div>
		</div>
	</div>
	<div class="admin-card">
		<div class="admin-card-header"><span class="adm-section-title">Giá và trạng thái bán</span></div>
		<div class="admin-card-body">
			<div class="row">
				<div class="col-md-4 mb-3">
					<label for="price" class="form-label">Giá bán</label>
					<input type="text" name="price" id="price" class="form-control @error('price') is-invalid @enderror @error('price_value') is-invalid @enderror" inputmode="numeric" value="{{ old('price', $product ? (int) $product->price : '') }}">
					@error('price')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
					@error('price_value')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
				</div>
				<div class="col-md-4 mb-3">
					<label for="discount" class="form-label">Giảm giá (%)</label>
					<input type="text" name="discount" id="discount" class="form-control" value="{{ $discountValue }}">
					<input type="hidden" name="discount_type" value="percent">
				</div>
				@if ($isEdit)
					<div class="col-md-4 mb-3">
						<label for="product_status" class="form-label">Trạng thái bán</label>
						<select name="status" id="product_status" class="form-select">
							<option value="1" @selected(in_array((string) old('status', $product->status), ['1', 'active'], true))>Còn kinh doanh</option>
							<option value="0" @selected(in_array((string) old('status', $product->status), ['0', 'inactive'], true))>Ngừng kinh doanh</option>
						</select>
					</div>
				@endif
			</div>
		</div>
	</div>
	<div class="admin-card">
		<div class="admin-card-header"><span class="adm-section-title">Hình ảnh</span></div>
		<div class="admin-card-body">
			<div class="mb-3">
				<label for="image" class="form-label">Ảnh đại diện</label>
				<div class="adm-preview" id="admMainPreview">
					@if ($isEdit && $product->image_link)
						<img src="{{ \App\Support\Storefront\Product::imageUrl($product->image_link) }}" alt="">
					@endif
				</div>
				@if ($isEdit && $product->image_link)
					<input type="hidden" name="product_image_main" value="{{ $product->image_link }}">
					<input type="hidden" name="product_image_list" value="{{ json_encode($gallery, JSON_UNESCAPED_UNICODE) }}">
				@endif
				<input type="file" name="image" id="image" class="form-control @error('image') is-invalid @enderror" accept="image/jpeg,image/png,image/gif,image/webp,image/bmp,.jpg,.jpeg,.png,.webp,.gif,.bmp" @unless($isEdit) required @endunless>
				@error('image')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
			</div>
			<div class="mb-0">
				<label for="list_image" class="form-label">Ảnh kèm theo</label>
				<input type="file" name="list_image[]" id="list_image" class="form-control" multiple accept="image/jpeg,image/png,image/gif,image/webp,image/bmp,.jpg,.jpeg,.png,.webp,.gif,.bmp">
				@if ($gallery !== [])
					<ul class="adm-gallery">
						@foreach ($gallery as $file)
							<li>
								<img src="{{ \App\Support\Storefront\Product::imageUrl($file) }}" alt="">
								<span>{{ $file }}</span>
								<label class="mb-0"><input type="checkbox" name="product_images_remove[]" value="{{ $file }}"> Gỡ</label>
							</li>
						@endforeach
					</ul>
				@endif
			</div>
		</div>
	</div>
	<div class="admin-card">
		<div class="admin-card-header"><span class="adm-section-title">Màu sắc và kích thước</span></div>
		<div class="admin-card-body">
			<div class="mb-3">
				<div class="form-label">Màu</div>
				<div class="adm-choice-grid">
					@foreach ($colors as $colorName => $hex)
						@php $swatch = is_string($hex) && preg_match('/^#[0-9a-fA-F]{3,8}$/', $hex) ? $hex : ''; @endphp
						<label class="adm-choice">
							<input type="checkbox" name="color[]" value="{{ $colorName }}" @checked(in_array($colorName, $selectedColors, true))>
							@if ($swatch !== '')<span class="adm-choice__swatch" style="background: {{ $swatch }}"></span>@endif
							<span>{{ $colorName }}</span>
						</label>
					@endforeach
				</div>
			</div>
			<div class="mb-0">
				<div class="form-label">Size</div>
				<div class="adm-choice-grid">
					@foreach ($sizes as $sizeName)
						<label class="adm-choice">
							<input type="checkbox" name="size[]" value="{{ $sizeName }}" @checked(in_array($sizeName, $selectedSizes, true))>
							<span>{{ $sizeName }}</span>
						</label>
					@endforeach
				</div>
			</div>
		</div>
	</div>
	<div class="admin-card">
		<div class="admin-card-header"><span class="adm-section-title">Chi tiết</span></div>
		<div class="admin-card-body">
			<label for="content" class="form-label">Mô tả</label>
			<textarea name="content" id="content" class="form-control" rows="6">{{ old('content', $product->content ?? '') }}</textarea>
		</div>
	</div>
	@if ($isEdit && $variants->isNotEmpty())
		<div class="admin-card">
			<div class="admin-card-header"><span class="adm-section-title">Biến thể</span></div>
			<div class="table-responsive adm-table-wrap">
				<table class="table admin-table table-sm mb-0">
					<thead><tr><th>SKU</th><th>Màu</th><th>Size</th><th>Giá</th><th>Giá vốn</th><th>Tồn</th><th>Tồn tối thiểu</th><th>Bán</th></tr></thead>
					<tbody>
						@foreach ($variants as $variant)
							<tr>
								<td><code>{{ $variant->sku }}</code></td>
								<td>{{ $variant->color }}</td>
								<td>{{ $variant->size }}</td>
								<td><input class="form-control form-control-sm" name="variant[{{ $variant->id }}][price]" value="{{ (int) $variant->price }}"></td>
								<td><input class="form-control form-control-sm" name="variant[{{ $variant->id }}][cost_price]" value="{{ (int) $variant->cost_price }}"></td>
								<td>{{ (int) $variant->stock }}</td>
								<td><input class="form-control form-control-sm" type="number" min="0" name="variant[{{ $variant->id }}][min_stock]" value="{{ (int) $variant->min_stock }}"></td>
								<td>
									<select class="form-select form-select-sm" name="variant[{{ $variant->id }}][status]">
										<option value="1" @selected((string) $variant->status === 'active')>Bán</option>
										<option value="0" @selected((string) $variant->status !== 'active')>Ẩn</option>
									</select>
								</td>
							</tr>
						@endforeach
					</tbody>
				</table>
			</div>
		</div>
	@endif
	<div class="d-flex flex-wrap gap-2 mb-4">
		<button class="btn btn-primary" type="submit">{{ $isEdit ? 'Cập nhật' : 'Thêm mới' }}</button>
		<a class="btn btn-outline-secondary" href="{{ url('admin/products') }}">Hủy</a>
	</div>
</form>
<script src="{{ url('assets/admin/vendor/ckeditor/ckeditor.js') }}"></script>
<script>if (window.CKEDITOR) { CKEDITOR.replace('content'); }</script>
@endsection
