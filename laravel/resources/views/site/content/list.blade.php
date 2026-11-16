@extends('layouts.storefront.main')

@section('content')
<div class="container jm-content-page" style="max-width: 1200px; margin: 0 auto; padding: 40px 15px;">
	<h1 style="font-size: 24px; font-weight: bold; margin-bottom: 40px; text-transform: uppercase; text-align: center;">{{ $heading }}</h1>
	@php $rows = $list->values(); $featured = $rows->shift(); @endphp
	@if ($featured)
		<article style="display: flex; flex-wrap: wrap; background: #fff; border: 1px solid #eee; margin-bottom: 40px;">
			<div style="flex: 1; min-width: 280px; padding: 32px;">
				<h2 style="font-size: 22px; margin-top: 0;">
					<a href="{{ url($detail_prefix . '/' . $featured->slug) }}">{{ $featured->title }}</a>
				</h2>
				<p>{{ $featured->intro }}</p>
				<a href="{{ url($detail_prefix . '/' . $featured->slug) }}">Xem chi tiết</a>
			</div>
			@if (! empty($featured->image))
				<div style="flex: 1; min-width: 280px;">
					<img src="{{ url('upload/' . $featured->image) }}" alt="{{ $featured->title }}" style="width: 100%; height: 100%; object-fit: cover;">
				</div>
			@endif
		</article>
	@endif
	@foreach ($rows as $row)
		<article style="margin-bottom: 24px;">
			<h2 style="font-size: 18px;"><a href="{{ url($detail_prefix . '/' . $row->slug) }}">{{ $row->title }}</a></h2>
			<p>{{ $row->intro }}</p>
		</article>
	@endforeach
	@if ($list->isEmpty())
		<p>Nội dung đang được cập nhật.</p>
	@endif
</div>
@endsection
