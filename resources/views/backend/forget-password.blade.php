@extends('backend.auth-layout')

@section('title', __('Reset your admin password'))

@section('form')
  <h1>{{ __('Forgot your password?') }}</h1>
  <p class="taa-lead">{{ __('Enter the email on your admin account. We\'ll send your username and a link to set a new password.') }}</p>
  <form action="{{ route('admin.mail_for_forget_password') }}" method="POST" novalidate>
    @csrf
    <div class="taa-field">
      <label for="taa-email">{{ __('Email address') }}</label>
      <input id="taa-email" type="email" name="email" value="{{ old('email') }}" autocomplete="email" required autofocus @error('email') aria-invalid="true" aria-describedby="taa-email-e" @enderror>
      @error('email')<p class="taa-err" id="taa-email-e">{{ $message }}</p>@enderror
    </div>
    <button class="taa-btn" type="submit">{{ __('Send reset link') }}</button>
  </form>
  <a class="taa-back" href="{{ route('admin.login') }}">{{ __('Back to sign in') }}</a>
@endsection
