@extends('layouts.storefront.sub')

@section('content')
<div class="col-xs-12 col-sm-9 col-md-9 col-lg-9">
	<div class="panel panel-info">
		<div class="panel-heading"><h1 class="panel-title">Tin tức</h1></div>
		<div class="panel-body">
			@if ($posts->isNotEmpty())
				<div class="list-group">
					@foreach ($posts as $item)
						<a class="list-group-item" href="{{ \App\Support\Storefront\Urls::newsPost($item) }}">{{ $item->title }}</a>
					@endforeach
				</div>
				@if ($total > $perPage)
					<div class="jm-modern-pagination">
						<ul>
							@if ($offset > 0)
								<li><a href="{{ url('tin-tuc/' . max(0, $offset - $perPage)) }}">Prev</a></li>
							@endif
							@if ($offset + $perPage < $total)
								<li><a href="{{ url('tin-tuc/' . ($offset + $perPage)) }}">Next</a></li>
							@endif
						</ul>
					</div>
				@endif
			@else
				<p class="text-muted mb-0">Hiện chưa có tin tức.</p>
			@endif
		</div>
	</div>
</div>
@endsection
