@extends('layouts.app')

@section('title', 'Đặt lại mật khẩu')

@section('content')
	<div class="jm-auth-standalone-wrap">
		<a href="{{ route('storefront.user.login') }}" class="jm-auth-back" title="Quay lại đăng nhập">
			<span class="jm-auth-back-icon" aria-hidden="true">←</span>
			Quay lại
		</a>

		<div class="jm-auth-card">
			<div class="jm-auth-card-head">
				<h1 class="jm-auth-title">Đặt lại mật khẩu</h1>
				<p class="jm-auth-subtitle">Nhập mật khẩu mới cho tài khoản của bạn</p>
			</div>

			@if (session('message_success'))
				<div class="jm-auth-alert jm-auth-alert-success">{{ session('message_success') }}</div>
			@endif
			@if (session('message_fail'))
				<div class="jm-auth-alert jm-auth-alert-error">{{ session('message_fail') }}</div>
			@endif

			@if (empty($token_valid))
				<div class="jm-auth-alert jm-auth-alert-error">
					Liên kết đặt lại mật khẩu không hợp lệ hoặc đã hết hạn.
				</div>
				<p class="jm-auth-footer-link">
					<a href="{{ route('storefront.user.forgot_password') }}">Yêu cầu liên kết mới</a>
				</p>
			@else
				<form class="jm-auth-form" method="POST" action="{{ route('storefront.user.reset_password.post') }}" novalidate>
					@csrf
					<input type="hidden" name="token" value="{{ $reset_token }}">

					<div class="jm-auth-field">
						<label for="resetPassword">Mật khẩu mới</label>
						<input type="password" class="jm-auth-input" id="resetPassword" name="password"
							placeholder="Tối thiểu 8 ký tự, có chữ và số" autocomplete="new-password" required minlength="8">
						<p class="jm-auth-hint">Ít nhất 8 ký tự, gồm ít nhất 1 chữ cái và 1 chữ số.</p>
						@error('password')<span class="jm-auth-field-error">{{ $message }}</span>@enderror
					</div>

					<div class="jm-auth-field">
						<label for="resetRePassword">Nhập lại mật khẩu</label>
						<input type="password" class="jm-auth-input" id="resetRePassword" name="re_password"
							placeholder="••••••••" autocomplete="new-password" required>
						@error('re_password')<span class="jm-auth-field-error">{{ $message }}</span>@enderror
					</div>

					<button type="submit" class="jm-auth-submit">Lưu mật khẩu mới</button>
				</form>
			@endif
		</div>
	</div>
@endsection
