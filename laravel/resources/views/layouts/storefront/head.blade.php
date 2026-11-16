@php
    $siteAssetUrl = \App\Support\Storefront\Shop::assetUrl('');
@endphp
	<meta charset="UTF-8">
	<title>@if (!empty($page_title)){{ $page_title }} · @endif{{ \App\Support\Storefront\Shop::name() }}</title>
	<meta name="description" content="{{ $meta_description ?? ($page_title ?? \App\Support\Storefront\Shop::name()) }}">
	@if (!empty($robots_meta))
	<meta name="robots" content="{{ $robots_meta }}">
	@endif
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<script src="{{ url('public/js/jquery-3.1.1.js') }}" type="text/javascript"></script>
	{!! \App\Support\Storefront\Csrf::bootstrapScript() !!}
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link rel="stylesheet" type="text/css" href="{{ $siteAssetUrl }}css/typography-luxury.css?v=1">
	<link rel="stylesheet" type="text/css" href="{{ $siteAssetUrl }}bootstrap/css/bootstrap.css">
	<link rel="stylesheet" type="text/css" href="{{ $siteAssetUrl }}css/style.css?v=20260724c">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
	<link rel="stylesheet" type="text/css" href="{{ $siteAssetUrl }}css/layout-chrome-2026.css?v=22">
	@if (!empty($canonical_url))
	<link rel="canonical" href="{{ $canonical_url }}">
	@endif
	<script type="text/javascript" src="{{ url('public/js/raty/jquery.raty.min.js') }}"></script>
	<script type="text/javascript">
      $(function() {
         $.fn.raty.defaults.path = "{{ url('public/js/raty/img') }}";
         $('.raty').raty({
           	  score: function() {
           	    return $(this).attr('data-score');
           	  },
              readOnly  : true,
          });
      });
     </script>
     <style>.raty img{width:16px !important;height:16px; !important;}</style>
