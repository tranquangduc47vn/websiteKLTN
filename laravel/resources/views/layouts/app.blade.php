<!DOCTYPE html>
<html lang="vi">
<head>
@php
    $page_title = trim($__env->yieldContent('title'));
@endphp
	@include('layouts.storefront.head')
	<link rel="stylesheet" type="text/css" href="{{ \App\Support\Storefront\Shop::assetUrl('css/responsive-2026.css') }}?v=16">
</head>
<body class="@yield('body_class', 'jm-page-auth jm-page-auth-standalone')">
	@yield('content')
	<script src="{{ \App\Support\Storefront\Shop::assetUrl('js/store-flash-auto.js') }}?v=1"></script>
	@stack('scripts')
</body>
</html>
