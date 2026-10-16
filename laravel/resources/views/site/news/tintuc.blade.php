@extends('layouts.storefront.main')

@section('content')
<div class="gt-wrapper-fix">
	<h1>{{ $page_title }}</h1>
	@forelse ($posts as $row)
		<article>
			@if ($row->image)
				<a href="{{ \App\Support\Storefront\Urls::newsPost($row) }}">
					<img src="{{ url('upload/news/' . $row->image) }}" alt="{{ $row->title }}" width="280">
				</a>
			@endif
			<p>{{ $row->created_at ? $row->created_at->format('d/m/Y') : '' }}</p>
			<h3><a href="{{ \App\Support\Storefront\Urls::newsPost($row) }}">{{ $row->title }}</a></h3>
			@if ($row->intro)
				<p>{{ $row->intro }}</p>
			@endif
		</article>
	@empty
		<p>Hiện chưa có tin tức.</p>
	@endforelse
</div>
@endsection
