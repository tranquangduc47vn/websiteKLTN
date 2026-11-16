@extends('layouts.app')

@section('title', 'Đăng nhập')

@section('content')
	<div class="jm-auth-standalone-wrap">
		<a href="{{ url('/') }}" class="jm-auth-back" title="Về trang chủ">
			<span class="jm-auth-back-icon" aria-hidden="true">←</span>
			Quay lại
		</a>

		<div class="jm-auth-card">
			<div class="jm-auth-card-head">
				<h1 class="jm-auth-title">Đăng nhập</h1>
				<p class="jm-auth-subtitle">Chào mừng bạn quay lại {{ \App\Support\Storefront\Shop::name() }}</p>
			</div>

			@if (session('message_success'))
				<div class="jm-auth-alert jm-auth-alert-success">{{ session('message_success') }}</div>
			@endif
			@if (session('message_fail'))
				<div class="jm-auth-alert jm-auth-alert-error">{{ session('message_fail') }}</div>
			@endif

			<form class="jm-auth-form" method="POST" action="{{ route('storefront.user.login.post') }}" novalidate>
				@csrf

				<div class="jm-auth-field">
					<label for="inputEmail3">Email</label>
					<input type="email" class="jm-auth-input" id="inputEmail3" name="email"
						placeholder="email@example.com"
						value="{{ old('email') }}"
						autocomplete="email" required autofocus>
					@error('email')<span class="jm-auth-field-error">{{ $message }}</span>@enderror
				</div>

				<div class="jm-auth-field">
					<label for="inputPassword3">Mật khẩu</label>
					<input type="password" class="jm-auth-input" id="inputPassword3" name="password"
						placeholder="••••••••" autocomplete="current-password" required>
					@error('password')<span class="jm-auth-field-error">{{ $message }}</span>@enderror
				</div>

				<div class="jm-auth-field">
					<label>
						<input type="checkbox" name="remember" value="1" @checked(old('remember'))>
						Ghi nhớ đăng nhập
					</label>
				</div>

				<p class="jm-auth-forgot">
					<a href="{{ route('storefront.user.forgot_password') }}">Quên mật khẩu?</a>
				</p>

				<button type="submit" class="jm-auth-submit">Đăng nhập</button>
			</form>

			<p class="jm-auth-footer-link">
				Chưa có tài khoản?
				<a href="{{ route('storefront.user.register') }}">Đăng ký ngay</a>
			</p>
		</div>
	</div>
@endsection
