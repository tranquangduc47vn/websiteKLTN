@extends('layouts.app')

@section('title', 'Đăng ký tài khoản')

@section('content')
<div class="wrap" style="max-width:560px;margin:40px auto;">
    <h1>Đăng ký tài khoản</h1>

    @if (session('message_success'))
        <div class="alert alert-success">{{ session('message_success') }}</div>
    @endif
    @if (session('message_fail'))
        <div class="alert alert-danger">{{ session('message_fail') }}</div>
    @endif

    <form method="POST" action="{{ route('storefront.user.register.post') }}">
        @csrf

        <div>
            <label for="name">Họ tên</label>
            <input id="name" name="name" type="text" value="{{ old('name') }}" required>
            @error('name')<span class="error">{{ $message }}</span>@enderror
        </div>

        <div>
            <label for="email">Email đăng nhập</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required>
            @error('email')<span class="error">{{ $message }}</span>@enderror
        </div>

        <div>
            <label for="password">Mật khẩu</label>
            <input id="password" name="password" type="password" required autocomplete="new-password">
            @error('password')<span class="error">{{ $message }}</span>@enderror
        </div>

        <div>
            <label for="re_password">Mật khẩu nhập lại</label>
            <input id="re_password" name="re_password" type="password" required autocomplete="new-password">
            @error('re_password')<span class="error">{{ $message }}</span>@enderror
        </div>

        <div>
            <label for="phone">Điện thoại</label>
            <input id="phone" name="phone" type="tel" value="{{ old('phone') }}" required>
            @error('phone')<span class="error">{{ $message }}</span>@enderror
        </div>

        <fieldset>
            <legend>Địa chỉ giao hàng</legend>

            <div>
                <label for="province_id">Tỉnh / Thành phố</label>
                <input id="province_id" name="province_id" type="text" value="{{ old('province_id') }}" required>
                @error('province_id')<span class="error">{{ $message }}</span>@enderror
            </div>

            <div>
                <label for="district_id">Quận / Huyện</label>
                <input id="district_id" name="district_id" type="text" value="{{ old('district_id') }}" required>
                @error('district_id')<span class="error">{{ $message }}</span>@enderror
            </div>

            <div>
                <label for="ward_id">Phường / Xã</label>
                <input id="ward_id" name="ward_id" type="text" value="{{ old('ward_id') }}" required>
                @error('ward_id')<span class="error">{{ $message }}</span>@enderror
            </div>

            <div>
                <label for="address_note">Ghi chú địa chỉ</label>
                <input id="address_note" name="address_note" type="text" value="{{ old('address_note') }}" required>
                @error('address_note')<span class="error">{{ $message }}</span>@enderror
            </div>
        </fieldset>

        <button type="submit">Tạo tài khoản</button>
    </form>

    <p>Đã có tài khoản? <a href="{{ route('storefront.user.login') }}">Đăng nhập</a></p>
</div>
@endsection
