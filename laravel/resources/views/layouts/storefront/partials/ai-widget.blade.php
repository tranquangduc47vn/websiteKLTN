@php
	$aiOn = \App\Models\AiSetting::isEnabled('ai_enabled', false);
	$staffOn = \App\Models\AiSetting::isEnabled('staff_support_enabled', true);
	$loggedIn = auth('web')->check();
	$welcome = (string) \App\Models\AiSetting::getValue('welcome_message', 'Xin chào 👋');
@endphp
@if ($aiOn || $staffOn)
<div id="support-chat-widget" class="support-chat-widget">
	<button type="button" id="support-chat-toggle" class="support-chat-toggle" aria-label="Mở hỗ trợ khách hàng" aria-expanded="false">
		<i class="fa-solid fa-comments" aria-hidden="true"></i>
	</button>
	<div id="support-chat-panel" class="support-chat-panel{{ $loggedIn ? '' : ' is-login-required' }}" hidden>
		<div class="support-chat-panel__header">
			<div class="support-chat-panel__avatar support-chat-panel__avatar--status" aria-hidden="true">
				<i class="fa-solid fa-robot"></i>
			</div>
			<div class="support-chat-panel__title">
				<strong>Hỗ trợ khách hàng</strong>
				<span id="support-chat-status-text" class="support-chat-panel__status">
					@if ($loggedIn)
						<span class="support-chat-panel__dot" aria-hidden="true"></span>
					@endif
					<span class="support-chat-panel__status-label">{{ $loggedIn ? 'Trợ lý AI đang trực tuyến' : 'Yêu cầu đăng nhập' }}</span>
				</span>
			</div>
			<button type="button" id="support-chat-close" class="support-chat-panel__close" aria-label="Đóng hỗ trợ khách hàng">
				<i class="fa-solid fa-xmark" aria-hidden="true"></i>
			</button>
		</div>
		<div class="support-chat-panel__body">
			<div id="support-chat-messages" class="support-chat-panel__messages" role="log" @if (! $loggedIn) hidden @endif></div>
			<div id="support-chat-login-gate" class="support-chat-panel__login-gate" @if ($loggedIn) hidden @endif>
				<h3>Đăng nhập để chat</h3>
				<p>Đăng nhập tài khoản để nhắn tin với trợ lý AI, tra cứu đơn hàng và kết nối nhân viên hỗ trợ.</p>
				<a href="{{ url('dang-nhap') }}">Đăng nhập</a>
				<a href="{{ url('dang-ky') }}">Tạo tài khoản mới</a>
			</div>
		</div>
		<div id="support-chat-quick-replies" @if (! $loggedIn) hidden @endif>
			@foreach (config('ai.quick_replies', []) as $reply)
				<button type="button" class="support-chat-quick-reply" data-text="{{ $reply }}">{{ $reply }}</button>
			@endforeach
		</div>
		<form id="support-chat-form" class="support-chat-panel__form" @if (! $loggedIn) hidden @endif>
			<input type="text" id="support-chat-input" class="support-chat-panel__input" placeholder="Hỏi trợ lý mua sắm..." maxlength="500" autocomplete="off">
			<button type="submit" class="support-chat-panel__send" aria-label="Gửi"><i class="fa-solid fa-paper-plane" aria-hidden="true"></i></button>
		</form>
	</div>
</div>
<script>
window.__SUPPORT_CHAT_CONFIG__ = {
	sendUrl: @json(url('ai-assistant/send')),
	historyUrl: @json(url('ai-assistant/history')),
	pollUrl: @json(url('ai-assistant/poll')),
	productOptionsUrl: @json(url('ai-assistant/product/')),
	cartAddUrl: @json(url('gio-hang/them/')),
	cartUrl: @json(\App\Support\Storefront\Urls::cart()),
	checkoutUrl: @json(\App\Support\Storefront\Urls::checkout()),
	ordersUrl: @json(url('user')),
	welcomeMessage: @json($welcome),
	welcomeIntro: @json((string) config('ai.welcome_intro')),
	pollIntervalMs: {{ (int) config('ai.poll_interval_ms', 1500) }},
	customerLoggedIn: {{ $loggedIn ? 'true' : 'false' }},
	loginUrl: @json(url('dang-nhap')),
	registerUrl: @json(url('dang-ky'))
};
</script>
<link rel="stylesheet" href="{{ \App\Support\Storefront\Shop::assetUrl('css/ai-chat.css') }}?v=5">
<script src="{{ \App\Support\Storefront\Shop::assetUrl('js/ai-chat.js') }}?v=11" defer></script>
@endif
