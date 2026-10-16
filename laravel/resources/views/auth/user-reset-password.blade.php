@extends('layouts.app')

@section('title', 'Đặt lại mật khẩu')

@section('content')
<div class="wrap" style="max-width:440px;margin:40px auto;">
    <h1>Đặt lại mật khẩu</h1>

    @if (session('message_success'))
        <div class="alert alert-success">{{ session('message_success') }}</div>
    @endif
    @if (session('message_fail'))
        <div class="alert alert-danger">{{ session('message_fail') }}</div>
    @endif

    @if (! $token_valid)
        <div class="alert alert-danger">Liên kết đặt lại mật khẩu không hợp lệ hoặc đã hết hạn.</div>
        <p><a href="{{ route('storefront.user.forgot_password') }}">Yêu cầu liên kết mới</a></p>
    @else
        <form method="POST" action="{{ route('storefront.user.reset_password.post') }}">
            @csrf
            <input type="hidden" name="token" value="{{ $reset_token }}">

            <div>
                <label for="password">Mật khẩu mới</label>
                <input id="password" name="password" type="password" required autocomplete="new-password">
                @error('password')<span class="error">{{ $message }}</span>@enderror
            </div>

            <div>
                <label for="re_password">Nhập lại mật khẩu</label>
                <input id="re_password" name="re_password" type="password" required autocomplete="new-password">
                @error('re_password')<span class="error">{{ $message }}</span>@enderror
            </div>

            <button type="submit">Đặt lại mật khẩu</button>
        </form>
    @endif

    <p><a href="{{ route('storefront.user.login') }}">Đăng nhập</a></p>
</div>
@endsection
