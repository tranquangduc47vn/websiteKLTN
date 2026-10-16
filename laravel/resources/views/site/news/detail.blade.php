@extends('layouts.storefront.main')

@section('content')
<div class="gt-wrapper-fix">
	<div class="gt-main-content">
		<div class="gt-col-right">
			<h1>{{ $post->title }}</h1>
			<p class="date">{{ $post->created_at ? $post->created_at->format('d/m/Y H:i') : '' }}</p>
			@if ($post->image)
				<p><img src="{{ url('upload/news/' . $post->image) }}" alt="{{ $post->title }}" style="max-width:100%"></p>
			@endif
			@if ($post->intro)
				<p style="font-weight:bold">{{ $post->intro }}</p>
			@endif
			<div>{!! \App\Support\HtmlSanitizer::clean($post->content) !!}</div>
		</div>
	</div>
	<div class="gt-other-news-section">
		<h2>Các tin tức khác</h2>
		@foreach ($other_news as $row)
			<p><a href="{{ \App\Support\Storefront\Urls::newsPost($row) }}">{{ $row->title }}</a></p>
		@endforeach
	</div>
</div>
@endsection
