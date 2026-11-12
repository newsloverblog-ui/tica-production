@extends('frontend.layout')
@section('body-class', 'tica-transactional')

@section('pageHeading', __('Ticket transfer'))

@section('hero-section')
  <section class="page-banner overlay pt-120 pb-125 rpt-90 rpb-95 lazy"
    data-bg="{{ asset('assets/admin/img/' . $basicInfo->breadcrumb) }}">
    <div class="container">
      <div class="banner-inner">
        <h1 class="page-title">{{ __('Ticket transfer') }}</h1>
      </div>
    </div>
  </section>
@endsection

@section('content')
  @php
    $event = optional(optional($invitation->ticket)->booking)->event;
    $maskedEmail = preg_replace('/(^.).*(@.*$)/', '$1••••$2', $invitation->recipient_email);
  @endphp
  <section class="py-120">
    <div class="container">
      <div class="row justify-content-center">
        <div class="col-lg-7">
          <div class="card border-0 shadow-sm">
            <div class="card-body p-4 p-md-5">
              <p class="text-uppercase font-weight-bold mb-2" style="color: #FE9B59; letter-spacing: .08em;">
                {{ __('Single-use invitation') }}
              </p>
              <h2 class="mb-3">{{ __('A ticket is waiting for you') }}</h2>
              <p class="lead mb-4">
                {{ $invitation->recipient_name }}, {{ __('you have been invited to accept an admission for') }}
                <strong>{{ $event->title ?? __('this event') }}</strong>.
              </p>

              <div class="rounded p-3 mb-4" style="background: #f7f8fb;">
                <div><strong>{{ __('Recipient') }}:</strong> {{ $maskedEmail }}</div>
                <div><strong>{{ __('Expires') }}:</strong> {{ $invitation->expires_at->translatedFormat('D, M j, Y g:i a') }}</div>
                <div><strong>{{ __('Protection') }}:</strong> {{ __('The old QR is invalidated only after acceptance.') }}</div>
              </div>

              @if (!$customer)
                <p>{{ __('Sign in or create and verify an account using the recipient email address before accepting.') }}</p>
                <div class="d-flex flex-wrap" style="gap: .75rem;">
                  <a class="btn" href="{{ route('customer.login') }}">{{ __('Sign in to accept') }}</a>
                  <a class="btn btn-outline-secondary" href="{{ route('customer.signup') }}">{{ __('Create account') }}</a>
                </div>
              @elseif (!$emailMatches)
                <div class="alert alert-warning">
                  {{ __('This invitation belongs to a different email address. Sign in with the address that received the invitation.') }}
                </div>
                <form method="post" action="{{ route('customer.logout') }}">
                  @csrf
                  <button class="btn" type="submit">{{ __('Sign out and use another account') }}</button>
                </form>
              @else
                <p>{{ __('Accepting moves control of this admission to your account and creates a new QR code. The sender will no longer be able to use or manage it.') }}</p>
                <form method="post" action="{{ $acceptUrl }}">
                  @csrf
                  <button class="btn" type="submit">{{ __('Accept ticket securely') }}</button>
                </form>
              @endif
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
@endsection
