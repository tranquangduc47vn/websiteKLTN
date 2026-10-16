@extends('layouts.app')

@section('title', 'Đăng nhập')

@section('content')
<div class="wrap" style="max-width:440px;margin:40px auto;">
    <h1>Đăng nhập</h1>

    @if (session('message_success'))
        <div class="alert alert-success">{{ session('message_success') }}</div>
    @endif
    @if (session('message_fail'))
        <div class="alert alert-danger">{{ session('message_fail') }}</div>
    @endif

    <form method="POST" action="{{ route('storefront.user.login.post') }}">
        @csrf

        <div>
            <label for="email">Email đăng nhập</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username">
            @error('email')<span class="error">{{ $message }}</span>@enderror
        </div>

        <div>
            <label for="password">Mật khẩu</label>
            <input id="password" name="password" type="password" required autocomplete="current-password">
            @error('password')<span class="error">{{ $message }}</span>@enderror
        </div>

        <label>
            <input type="checkbox" name="remember" value="1"> Ghi nhớ đăng nhập
        </label>

        <button type="submit">Đăng nhập</button>
    </form>

    <p><a href="{{ route('storefront.user.register') }}">Tạo tài khoản mới</a> ·
       <a href="{{ route('storefront.user.forgot_password') }}">Quên mật khẩu?</a></p>
</div>
@endsection
