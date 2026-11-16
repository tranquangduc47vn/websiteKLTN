@if (session('message_success') && ! session('message_fail') && ! $errors->any())
	<div class="alert alert-success" role="status">{{ session('message_success') }}</div>
@endif
@if (session('message_fail'))
	<div class="alert alert-danger" role="alert">{{ session('message_fail') }}</div>
@endif
