@extends('backend.auth-layout')

@section('title', __('Admin sign in'))

@section('form')
  <h1>{{ __('Admin sign in') }}</h1>
  <p class="taa-lead">{{ __('Use the admin username and password you were given.') }}</p>
  <form action="{{ route('admin.auth') }}" method="POST" novalidate>
    @csrf
    <div class="taa-field">
      <label for="taa-user">{{ __('Username') }}</label>
      <input id="taa-user" type="text" name="username" value="{{ old('username') }}" autocomplete="username" required autofocus @error('username') aria-invalid="true" aria-describedby="taa-user-e" @enderror>
      @error('username')<p class="taa-err" id="taa-user-e">{{ $message }}</p>@enderror
    </div>
    <div class="taa-field">
      <div class="taa-row"><label for="taa-pass">{{ __('Password') }}</label><a href="{{ route('admin.forget_password') }}">{{ __('Forgot password?') }}</a></div>
      <div class="taa-pass">
        <input id="taa-pass" type="password" name="password" autocomplete="current-password" required @error('password') aria-invalid="true" aria-describedby="taa-pass-e" @enderror>
        <button class="taa-toggle" type="button" id="taa-pass-toggle" aria-label="{{ __('Show password') }}" aria-controls="taa-pass">{{ __('Show') }}</button>
      </div>
      @error('password')<p class="taa-err" id="taa-pass-e">{{ $message }}</p>@enderror
    </div>
    <button class="taa-btn" type="submit">{{ __('Sign in') }}</button>
  </form>
  <script>
    (function () {
      var b = document.getElementById('taa-pass-toggle'), i = document.getElementById('taa-pass');
      if (!b || !i) return;
      b.addEventListener('click', function () {
        var show = i.type === 'password';
        i.type = show ? 'text' : 'password';
        b.textContent = show ? @json(__('Hide')) : @json(__('Show'));
        b.setAttribute('aria-label', show ? @json(__('Hide password')) : @json(__('Show password')));
      });
    })();
  </script>
@endsection
