@extends('layouts.admin.app')

@section('content')
<nav aria-label="breadcrumb" class="mb-3">
	<ol class="breadcrumb mb-0">
		<li class="breadcrumb-item"><a href="{{ url('admin/support-chat') }}">Hỗ trợ khách hàng</a></li>
		<li class="breadcrumb-item active">#{{ $conversation->id }}</li>
	</ol>
</nav>
<p>Trạng thái: <strong>{{ $conversation->status }}</strong>
	@if ($customer) — {{ $customer->name }} @endif
</p>
<div id="support-admin-messages" class="border rounded p-3 mb-3" style="max-height:420px;overflow:auto">
	@foreach ($messages as $message)
		<p class="mb-2"><strong>{{ $message->sender }}:</strong> {!! nl2br(e($message->content)) !!}</p>
	@endforeach
</div>
<form method="post" action="{{ url('admin/support-chat/send') }}" class="d-flex gap-2 mb-3">
	@csrf
	<input type="hidden" name="conversation_id" value="{{ $conversation->id }}">
	<input class="form-control" name="message" maxlength="500" placeholder="Nhập tin nhắn...">
	<button class="btn btn-primary" type="submit">Gửi</button>
</form>
<div class="d-flex gap-2">
	<form method="post" action="{{ url('admin/support-chat/take/' . $conversation->id) }}">@csrf<button class="btn btn-outline-primary btn-sm" type="submit">Nhận chat</button></form>
	<form method="post" action="{{ url('admin/support-chat/end/' . $conversation->id) }}">@csrf<button class="btn btn-outline-secondary btn-sm" type="submit">Kết thúc</button></form>
	<form method="post" action="{{ url('admin/support-chat/return_ai/' . $conversation->id) }}">@csrf<button class="btn btn-outline-secondary btn-sm" type="submit">Trả về AI</button></form>
</div>
<script>
window.__SUPPORT_ADMIN_CHAT__ = {
	conversationId: {{ (int) $conversation->id }},
	staffId: {{ (int) $staffId }},
	status: @json((string) $conversation->status),
	pollUrl: @json(url('admin/support-chat/poll')),
	sendUrl: @json(url('admin/support-chat/send')),
	takeUrl: @json(url('admin/support-chat/take/' . $conversation->id)),
	endUrl: @json(url('admin/support-chat/end/' . $conversation->id)),
	returnAiUrl: @json(url('admin/support-chat/return_ai/' . $conversation->id)),
	pollIntervalMs: 1500
};
</script>
@endsection
