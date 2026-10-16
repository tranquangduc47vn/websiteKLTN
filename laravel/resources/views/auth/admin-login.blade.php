<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Đăng nhập quản trị</title>
</head>
<body>
    <div class="wrap">
        <h1>Đăng nhập quản trị</h1>

        @if (session('message_success'))
            <div class="alert alert-success">{{ session('message_success') }}</div>
        @endif

        @if (session('message_fail'))
            <div class="alert alert-danger">{{ session('message_fail') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('admin.login.post') }}">
            @csrf

            <div>
                <label for="email">Email đăng nhập</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username">
                @error('email')
                    <span class="error">{{ $message }}</span>
                @enderror
            </div>

            <div>
                <label for="password">Mật khẩu</label>
                <input id="password" name="password" type="password" required autocomplete="current-password">
                @error('password')
                    <span class="error">{{ $message }}</span>
                @enderror
            </div>

            <button type="submit">Đăng nhập</button>
        </form>
    </div>
</body>
</html>
