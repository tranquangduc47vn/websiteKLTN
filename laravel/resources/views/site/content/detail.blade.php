@extends('layouts.storefront.main')

@section('content')
<article class="container" style="max-width: 900px; margin: 0 auto; padding: 40px 15px;">
	<h1>{{ $page->title }}</h1>
	@if (! empty($page->image))
		<p><img src="{{ url('upload/' . $page->image) }}" alt="{{ $page->title }}" style="max-width: 100%;"></p>
	@endif
	<div>{!! \App\Support\HtmlSanitizer::clean($page->content) !!}</div>
</article>
@endsection
