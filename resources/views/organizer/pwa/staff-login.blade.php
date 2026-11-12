<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>{{ __('Gate staff login') }} · Tica</title>
  <link href="{{ asset('assets/pwa_scanner/bootstrap.min.css') }}" rel="stylesheet">
  <style>
    body { min-height: 100vh; background: #071124; display: grid; place-items: center; padding: 1.5rem; }
    .gate-login { width: 100%; max-width: 460px; border: 0; border-radius: 18px; box-shadow: 0 24px 70px rgba(0,0,0,.32); }
    .gate-mark { color: #fe9b59; font-size: .78rem; font-weight: 800; letter-spacing: .13em; text-transform: uppercase; }
    .btn-gate { background: #fe9b59; border-color: #fe9b59; color: #101728; font-weight: 700; }
  </style>
</head>
<body>
  <main class="card gate-login">
    <div class="card-body p-4 p-sm-5">
      <p class="gate-mark mb-2">Tica gate operations</p>
      <h1 class="h3 mb-2">{{ __('Gate staff login') }}</h1>
      <p class="text-muted mb-4">{{ __('Use the dedicated credentials provided by your event organizer.') }}</p>
      @if(session('alert')) <div class="alert alert-danger">{{ session('alert') }}</div> @endif
      <form method="POST" action="{{ route('scanner.authentication') }}">
        @csrf
        <div class="form-group">
          <label for="email">{{ __('Email') }}</label>
          <input id="email" type="email" name="email" class="form-control" value="{{ old('email') }}" autocomplete="username" required autofocus>
          @error('email') <small class="text-danger">{{ $message }}</small> @enderror
        </div>
        <div class="form-group">
          <label for="password">{{ __('Password') }}</label>
          <input id="password" type="password" name="password" class="form-control" autocomplete="current-password" required>
          @error('password') <small class="text-danger">{{ $message }}</small> @enderror
        </div>
        <button type="submit" class="btn btn-gate btn-block py-2">{{ __('Open scanner') }}</button>
      </form>
      <p class="small text-muted mt-4 mb-0">{{ __('This account can only access authorized gate operations. Contact the organizer for password or access changes.') }}</p>
    </div>
  </main>
</body>
</html>
