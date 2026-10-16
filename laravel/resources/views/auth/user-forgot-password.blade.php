@extends('layouts.app')

@section('title', 'Quên mật khẩu')

@section('content')
<div class="wrap" style="max-width:440px;margin:40px auto;">
    <h1>Quên mật khẩu</h1>

    @if (session('message_success'))
        <div class="alert alert-success">{{ session('message_success') }}</div>
    @endif
    @if (session('message_fail'))
        <div class="alert alert-danger">{{ session('message_fail') }}</div>
    @endif

    <form method="POST" action="{{ route('storefront.user.forgot_password.post') }}">
        @csrf

        <div>
            <label for="email">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="email">
            @error('email')<span class="error">{{ $message }}</span>@enderror
        </div>

        <button type="submit">Gửi liên kết đặt lại mật khẩu</button>
    </form>

    <p><a href="{{ route('storefront.user.login') }}">Đăng nhập</a> ·
       <a href="{{ route('storefront.user.register') }}">Tạo tài khoản mới</a></p>
</div>
@endsection
