@extends('layouts.admin.app')

@php
	use App\Support\Admin\SupportChatPresenter;
@endphp

@section('content')
@php
	$lastId = $messages->isNotEmpty() ? (int) $messages->last()->id : 0;
@endphp
<link rel="stylesheet" href="{{ url('assets/admin/css/support-chat.css') }}?v=1">
<nav aria-label="breadcrumb" class="admin-breadcrumb">
	<ol class="breadcrumb mb-0">
		<li class="breadcrumb-item"><a href="{{ url('admin/support-chat') }}">Hỗ trợ khách hàng</a></li>
		<li class="breadcrumb-item active">#{{ $conversation->id }}</li>
	</ol>
</nav>
<div class="admin-page-header">
	<h1 class="admin-page-title">Hội thoại #{{ $conversation->id }}</h1>
	<p class="admin-page-subtitle mb-0">
		Trạng thái: <strong id="support-admin-status">{{ SupportChatPresenter::statusLabel($conversation->status) }}</strong>
		@if ($customer) — {{ $customer->name }} @endif
	</p>
</div>
<div class="admin-card mb-3">
	<div id="support-admin-messages" class="admin-card-body support-admin-chat__messages">
	@foreach ($messages as $message)
		@php
			$sender = match ((string) $message->sender) {
				'customer' => 'customer',
				'staff' => 'staff',
				'ai' => 'ai',
				default => 'system',
			};
		@endphp
		<div class="support-admin-msg support-admin-msg--{{ $sender }}" data-msg-id="{{ $message->id }}">
			<div class="support-admin-msg__bubble">{!! nl2br(e($message->content)) !!}</div>
			<div class="support-admin-msg__meta small text-muted">{{ $message->sender }}</div>
		</div>
	@endforeach
	</div>
</div>
<form method="post" action="{{ url('admin/support-chat/send') }}" class="d-flex flex-wrap gap-2 mb-3" id="support-admin-form">
	@csrf
	<input type="hidden" name="conversation_id" value="{{ $conversation->id }}">
	<input class="form-control" name="message" id="support-admin-input" maxlength="500" placeholder="Nhập tin nhắn..." style="min-width:min(100%, 220px);flex:1" autocomplete="off">
	<button class="btn btn-primary" type="submit">Gửi</button>
</form>
<div class="d-flex flex-wrap gap-2 mb-4">
	<button type="button" class="btn btn-outline-primary btn-sm" id="support-admin-take">Nhận chat</button>
	<button type="button" class="btn btn-outline-secondary btn-sm" id="support-admin-end">Kết thúc</button>
	<button type="button" class="btn btn-outline-secondary btn-sm" id="support-admin-return-ai">Trả về AI</button>
</div>
<script>
window.__SUPPORT_ADMIN_CHAT__ = {
	conversationId: {{ (int) $conversation->id }},
	staffId: {{ (int) $staffId }},
	status: @json((string) $conversation->status),
	statusLabels: @json(\App\Support\Admin\SupportChatPresenter::statusLabels()),
	lastMessageId: {{ $lastId }},
	pollUrl: @json(url('admin/support-chat/poll')),
	sendUrl: @json(url('admin/support-chat/send')),
	takeUrl: @json(url('admin/support-chat/take/' . $conversation->id)),
	endUrl: @json(url('admin/support-chat/end/' . $conversation->id)),
	returnAiUrl: @json(url('admin/support-chat/return_ai/' . $conversation->id)),
	pollIntervalMs: 1500
};
</script>
<script src="{{ url('assets/admin/js/support-chat.js') }}?v=3" defer></script>
@endsection