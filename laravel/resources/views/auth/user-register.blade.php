@extends('layouts.app')

@section('title', 'Đăng ký')

@section('body_class', 'jm-page-auth jm-page-auth-standalone jm-page-auth-standalone--scroll')

@section('content')
	<div class="jm-auth-standalone-wrap jm-auth-standalone-wrap-register">
		<a href="{{ route('storefront.user.login') }}" class="jm-auth-back" title="Quay lại đăng nhập">
			<span class="jm-auth-back-icon" aria-hidden="true">←</span>
			Quay lại
		</a>

		<div class="jm-auth-card">
			<div class="jm-auth-card-head">
				<h1 class="jm-auth-title">Đăng ký</h1>
				<p class="jm-auth-subtitle">Tạo tài khoản để mua sắm tại {{ \App\Support\Storefront\Shop::name() }}</p>
			</div>

			@if (session('message_success'))
				<div class="jm-auth-alert jm-auth-alert-success">{{ session('message_success') }}</div>
			@endif
			@if (session('message_fail'))
				<div class="jm-auth-alert jm-auth-alert-error">{{ session('message_fail') }}</div>
			@endif

			<form class="jm-auth-form" method="POST" action="{{ route('storefront.user.register.post') }}" novalidate>
				@csrf
				<div class="jm-auth-field">
					<label for="regName">Họ tên</label>
					<input type="text" class="jm-auth-input" id="regName" name="name"
						placeholder="Nguyễn Văn A"
						value="{{ old('name') }}"
						autocomplete="name" required>
					@error('name')<span class="jm-auth-field-error">{{ $message }}</span>@enderror
				</div>

				<div class="jm-auth-field">
					<label for="regEmail">Email</label>
					<input type="email" class="jm-auth-input" id="regEmail" name="email"
						placeholder="email@example.com"
						value="{{ old('email') }}"
						autocomplete="email" required>
					@error('email')<span class="jm-auth-field-error">{{ $message }}</span>@enderror
				</div>

				<div class="jm-auth-field">
					<label for="regPassword">Mật khẩu</label>
					<input type="password" class="jm-auth-input" id="regPassword" name="password"
						placeholder="Tối thiểu 8 ký tự, có chữ và số" autocomplete="new-password" required minlength="8">
					<p class="jm-auth-hint">Ít nhất 8 ký tự, gồm ít nhất 1 chữ cái và 1 chữ số.</p>
					@error('password')<span class="jm-auth-field-error">{{ $message }}</span>@enderror
				</div>

				<div class="jm-auth-field">
					<label for="regRePassword">Nhập lại mật khẩu</label>
					<input type="password" class="jm-auth-input" id="regRePassword" name="re_password"
						placeholder="••••••••" autocomplete="new-password" required>
					@error('re_password')<span class="jm-auth-field-error">{{ $message }}</span>@enderror
				</div>

				<div class="jm-auth-field">
					<label>Địa chỉ (Việt Nam) <span class="jm-auth-required">*</span></label>
					<p class="jm-auth-hint">Chọn đúng tỉnh, quận/huyện, phường/xã — không nhập tỉnh thành tùy ý.</p>
					<select class="jm-auth-input jm-auth-select" id="regProvince" name="province_id" required>
						<option value="">— Đang tải tỉnh thành —</option>
					</select>
					@error('province_id')<span class="jm-auth-field-error">{{ $message }}</span>@enderror
					<select class="jm-auth-input jm-auth-select" id="regDistrict" name="district_id" required disabled>
						<option value="">— Chọn quận / huyện —</option>
					</select>
					@error('district_id')<span class="jm-auth-field-error">{{ $message }}</span>@enderror
					<select class="jm-auth-input jm-auth-select" id="regWard" name="ward_id" required disabled>
						<option value="">— Chọn phường / xã —</option>
					</select>
					@error('ward_id')<span class="jm-auth-field-error">{{ $message }}</span>@enderror
				</div>

				<div class="jm-auth-field">
					<label for="regAddressNote">Ghi chú địa chỉ (số nhà, tên đường, …) <span class="jm-auth-required">*</span></label>
					<input type="text" class="jm-auth-input" id="regAddressNote" name="address_note"
						placeholder="Ví dụ: 123 Nguyễn Trãi, tòa A, căn 502"
						value="{{ old('address_note') }}"
						autocomplete="address-line1" required maxlength="255">
					@error('address_note')<span class="jm-auth-field-error">{{ $message }}</span>@enderror
				</div>

				<div class="jm-auth-field">
					<label for="regPhone">Số điện thoại</label>
					<input type="tel" class="jm-auth-input" id="regPhone" name="phone"
						placeholder="09xxxxxxxx"
						value="{{ old('phone') }}"
						autocomplete="tel" required>
					@error('phone')<span class="jm-auth-field-error">{{ $message }}</span>@enderror
				</div>

				<button type="submit" class="jm-auth-submit">Đăng ký</button>
			</form>

			<p class="jm-auth-footer-link">
				Đã có tài khoản?
				<a href="{{ route('storefront.user.login') }}">Đăng nhập</a>
			</p>
		</div>
	</div>
@endsection

@push('scripts')
	<script src="{{ \App\Support\Storefront\Shop::assetUrl('js/vn-address.js?v=1') }}"></script>
	<script>
	(function ($) {
		if (!$ || !window.VnAddressForm) {
			return;
		}
		var form = new VnAddressForm({
			province: '#regProvince',
			district: '#regDistrict',
			ward: '#regWard',
			dataUrl: @json(\App\Support\Storefront\VnAddress::jsonUrl()),
			initial: {
				province_id: @json(old('province_id', '')),
				district_id: @json(old('district_id', '')),
				ward_id: @json(old('ward_id', ''))
			}
		});
		form.init().fail(function () {
			$('#regProvince').html('<option value="">Không tải được dữ liệu địa giới</option>');
		});
	})(window.jQuery);
	</script>
@endpush
