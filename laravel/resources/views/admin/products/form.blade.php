@extends('layouts.admin.app')

@section('content')
@php
	$isEdit = $product !== null;
	$action = $isEdit ? url('admin/products/edit/'.$product->id) : url('admin/products/add');
	$selectedColors = old('color', $product && $product->color ? explode(',', (string) $product->color) : []);
	$selectedSizes = old('size', $product && $product->size ? explode(',', (string) $product->size) : []);
	$discountValue = old('discount', $product->discount_percent ?? '');
	$gallery = [];
	if ($product && $product->image_list) {
		$decoded = json_decode((string) $product->image_list, true);
		$gallery = is_array($decoded) ? $decoded : [];
	}
@endphp
<nav aria-label="breadcrumb" class="mb-3">
	<ol class="breadcrumb mb-0">
		<li class="breadcrumb-item"><a href="{{ url('admin/products') }}">Sản phẩm</a></li>
		<li class="breadcrumb-item active">{{ $isEdit ? 'Chỉnh sửa sản phẩm' : 'Thêm sản phẩm' }}</li>
	</ol>
</nav>
<form method="post" action="{{ $action }}" enctype="multipart/form-data" class="admin-form">
	@csrf
	<div class="mb-3">
		<label for="name" class="form-label">Tên sản phẩm</label>
		<input type="text" name="name" id="name" class="form-control" maxlength="255" value="{{ old('name', $product->name ?? '') }}">
		@error('name')<div class="text-danger">{{ $message }}</div>@enderror
	</div>
	<div class="mb-3">
		<label for="catalog_id" class="form-label">Danh mục</label>
		<select name="catalog_id" id="catalog_id" class="form-select">
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
		@error('catalog_id')<div class="text-danger">{{ $message }}</div>@enderror
	</div>
	<div class="row">
		<div class="col-md-4 mb-3">
			<label for="price" class="form-label">Giá bán</label>
			<input type="text" name="price" id="price" class="form-control" inputmode="numeric" value="{{ old('price', $product ? (int) $product->price : '') }}">
			@error('price')<div class="text-danger">{{ $message }}</div>@enderror
			@error('price_value')<div class="text-danger">{{ $message }}</div>@enderror
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
					<option value="1" @selected((string) old('status', $product->status) === '1')>Còn kinh doanh</option>
					<option value="0" @selected((string) old('status', $product->status) === '0')>Ngừng kinh doanh</option>
				</select>
			</div>
		@endif
	</div>
	<div class="mb-3">
		<label for="image" class="form-label">Ảnh đại diện</label>
		@if ($isEdit && $product->image_link)
			<input type="hidden" name="product_image_main" value="{{ $product->image_link }}">
			<input type="hidden" name="product_image_list" value="{{ $product->image_list }}">
			<div class="mb-1"><img src="{{ \App\Support\Storefront\Product::imageUrl($product->image_link) }}" alt="" style="max-height:80px"></div>
		@endif
		<input type="file" name="image" id="image" accept="image/jpeg,image/png,image/gif,image/webp,image/bmp,.jpg,.jpeg,.png,.webp,.gif,.bmp" @unless($isEdit) required @endunless>
		@error('image')<div class="text-danger">{{ $message }}</div>@enderror
	</div>
	<div class="mb-3">
		<label for="list_image" class="form-label">Ảnh kèm theo</label>
		<input type="file" name="list_image[]" id="list_image" multiple accept="image/jpeg,image/png,image/gif,image/webp,image/bmp,.jpg,.jpeg,.png,.webp,.gif,.bmp">
		@if ($gallery !== [])
			<ul class="small mb-0">
				@foreach ($gallery as $file)
					<li>
						{{ $file }}
						<label class="ms-2"><input type="checkbox" name="product_images_remove[]" value="{{ $file }}"> Gỡ</label>
					</li>
				@endforeach
			</ul>
		@endif
	</div>
	<div class="mb-3">
		<div class="form-label">Màu</div>
		@foreach ($colors as $colorName => $hex)
			<label class="me-3">
				<input type="checkbox" name="color[]" value="{{ $colorName }}" @checked(in_array($colorName, $selectedColors, true))>
				{{ $colorName }}
			</label>
		@endforeach
	</div>
	<div class="mb-3">
		<div class="form-label">Size</div>
		@foreach ($sizes as $sizeName)
			<label class="me-3">
				<input type="checkbox" name="size[]" value="{{ $sizeName }}" @checked(in_array($sizeName, $selectedSizes, true))>
				{{ $sizeName }}
			</label>
		@endforeach
	</div>
	<div class="mb-3">
		<label for="content" class="form-label">Chi tiết</label>
		<textarea name="content" id="content" class="form-control" rows="6">{{ old('content', $product->content ?? '') }}</textarea>
	</div>
	@if ($isEdit && $variants->isNotEmpty())
		<div class="mb-3">
			<div class="form-label">Biến thể</div>
			<div class="table-responsive">
				<table class="table table-sm">
					<thead><tr><th>SKU</th><th>Màu</th><th>Size</th><th>Giá</th><th>Giá vốn</th><th>Tồn</th><th>Tồn tối thiểu</th><th>Bán</th></tr></thead>
					<tbody>
						@foreach ($variants as $variant)
							<tr>
								<td>{{ $variant->sku }}</td>
								<td>{{ $variant->color }}</td>
								<td>{{ $variant->size }}</td>
								<td><input class="form-control form-control-sm" name="variant[{{ $variant->id }}][price]" value="{{ (int) $variant->price }}"></td>
								<td><input class="form-control form-control-sm" name="variant[{{ $variant->id }}][cost_price]" value="{{ (int) $variant->cost_price }}"></td>
								<td>{{ (int) $variant->stock }}</td>
								<td><input class="form-control form-control-sm" type="number" min="0" name="variant[{{ $variant->id }}][min_stock]" value="{{ (int) $variant->min_stock }}"></td>
								<td>
									<select class="form-select form-select-sm" name="variant[{{ $variant->id }}][status]">
										<option value="1" @selected((int) $variant->status === 1)>Bán</option>
										<option value="0" @selected((int) $variant->status !== 1)>Ẩn</option>
									</select>
								</td>
							</tr>
						@endforeach
					</tbody>
				</table>
			</div>
		</div>
	@endif
	<button class="btn btn-primary" type="submit">{{ $isEdit ? 'Cập nhật' : 'Thêm mới' }}</button>
	<a class="btn btn-outline-secondary" href="{{ url('admin/products') }}">Hủy</a>
</form>
<script src="{{ url('assets/admin/vendor/ckeditor/ckeditor.js') }}"></script>
<script>if (window.CKEDITOR) { CKEDITOR.replace('content'); }</script>
@endsection
