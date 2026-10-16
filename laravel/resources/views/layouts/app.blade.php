<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Webshop')</title>
</head>
<body>
    @if (session('message_success'))
        <div class="alert alert-success">{{ session('message_success') }}</div>
    @endif
    @if (session('message_fail'))
        <div class="alert alert-danger">{{ session('message_fail') }}</div>
    @endif

    @yield('content')
</body>
</html>
