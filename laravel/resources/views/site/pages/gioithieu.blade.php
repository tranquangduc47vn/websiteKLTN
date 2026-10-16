@extends('layouts.storefront.main')

@section('content')
<div class="gt-wrapper-fix">
	<div class="gt-main-content">
		<div class="gt-col-right">
			<h1>{{ $page_title }}</h1>
			<p class="date">2022-02-16</p>
			<div class="gt-detail-text">{!! \App\Support\HtmlSanitizer::clean($page_content) !!}</div>
		</div>
	</div>
	<div class="gt-other-news-section">
		<h2>Các tin tức khác</h2>
		@forelse ($other_news as $row)
			<p>
				<a href="{{ \App\Support\Storefront\Urls::newsPost($row) }}">{{ $row->title }}</a>
			</p>
		@empty
			<p>Chưa có dữ liệu tin tức nào khác để hiển thị.</p>
		@endforelse
	</div>
</div>
@endsection
