<!DOCTYPE html>
<html lang="vi">
<head>
	@include("layouts.storefront.head")
</head>
<body>

	@include("layouts.storefront.header")

	<div class="container">
		@include("layouts.storefront.message")
		@include("layouts.storefront.slider")
		@yield("content")
	</div>

	@include("layouts.storefront.footer")

	@include("layouts.storefront.scripts")
</body>
</html>
