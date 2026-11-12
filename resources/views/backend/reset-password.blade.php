@extends('backend.auth-layout')

@section('title', __('Set a new admin password'))

@section('form')
  <h1>{{ __('Set a new password') }}</h1>
  <p class="taa-lead">{{ __('Use at least 8 characters. You\'ll sign in with it straight away.') }}</p>
  <form action="{{ route('admin.update_forgotten_password') }}" method="POST" novalidate>
    @csrf
    <input type="hidden" name="email" value="{{ request('email') }}">
    <input type="hidden" name="token" value="{{ request('token') }}">
    @error('token')<p class="taa-msg bad" role="alert">{{ $message }}</p>@enderror
    <div class="taa-field">
      <label for="taa-new">{{ __('New password') }}</label>
      <input id="taa-new" type="password" name="password" autocomplete="new-password" required autofocus @error('password') aria-invalid="true" aria-describedby="taa-new-e" @enderror>
      @error('password')<p class="taa-err" id="taa-new-e">{{ $message }}</p>@enderror
    </div>
    <div class="taa-field">
      <label for="taa-confirm">{{ __('Type it again') }}</label>
      <input id="taa-confirm" type="password" name="password_confirmation" autocomplete="new-password" required>
    </div>
    <button class="taa-btn" type="submit">{{ __('Save password') }}</button>
  </form>
  <a class="taa-back" href="{{ route('admin.login') }}">{{ __('Back to sign in') }}</a>
@endsection
