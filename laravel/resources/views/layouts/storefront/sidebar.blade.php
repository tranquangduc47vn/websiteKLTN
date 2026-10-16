@php
    $catalog_id = $catalog_id ?? null;
    $price_from = $price_from ?? null;
    $price_to = $price_to ?? null;
@endphp
<div class="col-xs-12 col-sm-3 col-md-3 col-lg-3 clearpaddingl">
	<div class="panel panel-info" style="margin-bottom: 5px;">
		<div class="panel-heading">
		    <h3 class="panel-title">Tìm kiếm</h3>
		</div>
	    <div class="panel-body">
	  	    <form class="form-horizontal text-center" method="get" action="{{ url("tim-kiem") }}">
				<div class="form-group form-group-sm">
					<label class="col-sm-5 clearpaddingl control-label" for="formGroupInputSmall">Danh mục</label>
					<div class="col-sm-7" style="padding-left: 0px">
				    	@php $catalogList = $catalog ?? collect(); @endphp
				    	<select class="form-control" name="catalog_id">
				    		@foreach ($catalogList as $value)
							  	<option value="{{ $value->id }}" style="font-weight: bold" {{ (int) $value->id == (int) $catalog_id ? "selected" : "" }}>{{ $value->name }}</option>
								  	@foreach ($value->subs ?? [] as $val)
									  	<option value="{{ $val->id }}" {{ (int) $val->id == (int) $catalog_id ? "selected" : "" }}>&nbsp;&nbsp;&nbsp;{{ $val->name }}</option>
								  @endforeach
								@if ($loop->last)
								</select>
								@endif
				    		@endforeach
				    </div>
				</div>
				<div class="form-group form-group-sm">
					<label class="col-sm-5 control-label" for="formGroupInputSmall">Giá từ:</label>
					<div class="col-sm-7" style="padding-left: 0px">
				    	<select class="form-control" name="price_from">
				    	@for ($i = 0; $i < 1400000; $i = $i + 100000)
				    		<option value="{{ $i }}" {{ $i == $price_from ? "selected" : "" }}>{{ number_format($i) }} VNĐ</option>
				    	@endfor
						</select>
				    </div>
				</div>
				<div class="form-group form-group-sm">
					<label class="col-sm-5 control-label" for="formGroupInputSmall">đến:</label>
					<div class="col-sm-7" style="padding-left: 0px">
				    	<select class="form-control" name="price_to">
				    	@for ($i = 100000; $i < 1500000; $i = $i + 100000)
				    		<option value="{{ $i }}" {{ $i == $price_to ? "selected" : "" }}>{{ number_format($i) }} VND</option>
				    	@endfor
						</select>
				    </div>
				</div>
				<button class="btn btn-info" type="submit" name="submit">Tìm kiếm</button>
			</form>
	    </div>
	</div>
	<div class="panel panel-info">
	  <div class="panel-body" style="padding:0px">
	  	<div class="list-group">
	  		<!-- 🌟 KHỐI SẢN PHẨM NỔI BẬT (Dùng chung khung chuẩn với danh mục) -->
	  		<div class="list-group">
	  			<a href="#" class="list-group-item active">
		    		SẢN PHẨM NỔI BẬT
		  		</a>
		  		<a href="{{ url("product/news") }}" class="list-group-item">Sản phẩm mới</a>
		  		<a href="{{ url("ban-chay") }}" class="list-group-item">Sản phẩm bán chạy</a>
		  		<a href="{{ url("product/views") }}" class="list-group-item">Sản phẩm xem nhiều</a>
	  		</div>

			<!-- 👕 DANH MỤC SẢN PHẨM ĐỘNG -->
	  		@foreach ($catalog as $value)
	  			@php
	  				$name = strtolower(\App\Support\Storefront\Slug::viToEn($value->name));
	  			@endphp
	  			<div class="list-group">
	  				<a href="{{ \App\Support\Storefront\Urls::category($value) }}" class="list-group-item active">
		    		{{ $value->name }}
		  		</a>
		  			@foreach ($value->subs as $val)
		  				<a href="{{ \App\Support\Storefront\Urls::category($val) }}" class="list-group-item">{{ $val->name }}</a>
		  			@endforeach
		 		</div>
	  		@endforeach

	    </div>
	  </div>
	</div>
	<div class="panel panel-info" style="margin-top:10px;margin-bottom:10px;">
	  <div class="panel-body" style="padding:0px;">
	  	<img style="width: 100%" src="{{ url("upload/qc.gif") }}" alt="">
	  </div>
	</div>
</div>
