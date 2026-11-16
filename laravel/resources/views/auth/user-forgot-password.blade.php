@extends('layouts.app')

@section('title', 'Quên mật khẩu')

@section('content')
	<div class="jm-auth-standalone-wrap">
		<a href="{{ route('storefront.user.login') }}" class="jm-auth-back" title="Quay lại đăng nhập">
			<span class="jm-auth-back-icon" aria-hidden="true">←</span>
			Quay lại
		</a>

		<div class="jm-auth-card">
			<div class="jm-auth-card-head">
				<h1 class="jm-auth-title">Quên mật khẩu</h1>
				<p class="jm-auth-subtitle">Nhập email đăng ký — chúng tôi sẽ gửi liên kết đặt lại mật khẩu</p>
			</div>

			@if (session('message_success'))
				<div class="jm-auth-alert jm-auth-alert-success">{{ session('message_success') }}</div>
			@endif
			@if (session('message_fail'))
				<div class="jm-auth-alert jm-auth-alert-error">{{ session('message_fail') }}</div>
			@endif

			<form class="jm-auth-form" method="POST" action="{{ route('storefront.user.forgot_password.post') }}" novalidate>
				@csrf
				<div class="jm-auth-field">
					<label for="forgotEmail">Email</label>
					<input type="email" class="jm-auth-input" id="forgotEmail" name="email"
						placeholder="email@example.com"
						value="{{ old('email') }}"
						autocomplete="email" required autofocus>
					@error('email')<span class="jm-auth-field-error">{{ $message }}</span>@enderror
				</div>

				<button type="submit" class="jm-auth-submit">Gửi liên kết đặt lại</button>
			</form>

			<p class="jm-auth-footer-link">
				Nhớ mật khẩu?
				<a href="{{ route('storefront.user.login') }}">Đăng nhập</a>
			</p>
		</div>
	</div>
@endsection
