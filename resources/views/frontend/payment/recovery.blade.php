@extends('frontend.layout')

@section('pageHeading')
  {{ __('Confirm your payment') }}
@endsection

@section('style')
  <style>
    .payment-recovery-shell {
      min-height: 68vh;
      padding: 72px 0 96px;
      background: #f7f8fb;
    }
    .payment-recovery-card {
      max-width: 720px;
      margin: 0 auto;
      padding: clamp(28px, 5vw, 52px);
      border: 1px solid #e4e7ee;
      border-radius: 16px;
      background: #fff;
    }
    .payment-recovery-mark {
      display: grid;
      width: 56px;
      height: 56px;
      margin-bottom: 24px;
      place-items: center;
      border-radius: 12px;
      color: #081426;
      background: #fe9b59;
      font-size: 22px;
    }
    .payment-recovery-card h1 {
      margin: 0 0 12px;
      color: #081426;
      font-size: clamp(30px, 5vw, 44px);
      line-height: 1.08;
    }
    .payment-recovery-card .lead-copy {
      max-width: 590px;
      margin-bottom: 28px;
      color: #586174;
      font-size: 17px;
      line-height: 1.65;
    }
    .payment-recovery-summary {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 1px;
      overflow: hidden;
      margin-bottom: 24px;
      border: 1px solid #e4e7ee;
      border-radius: 12px;
      background: #e4e7ee;
    }
    .payment-recovery-summary > div {
      min-width: 0;
      padding: 16px 18px;
      background: #fff;
    }
    .payment-recovery-summary span {
      display: block;
      margin-bottom: 4px;
      color: #7a8292;
      font-size: 12px;
      font-weight: 700;
      letter-spacing: .08em;
      text-transform: uppercase;
    }
    .payment-recovery-summary strong {
      display: block;
      overflow-wrap: anywhere;
      color: #081426;
      font-size: 16px;
    }
    .payment-recovery-alert {
      margin-bottom: 22px;
      padding: 14px 16px;
      border-radius: 12px;
      color: #49311f;
      background: #fff1e8;
      line-height: 1.5;
    }
    .payment-recovery-alert.is-error { color: #7b1f1f; background: #fff0f0; }
    .payment-recovery-actions { display: flex; flex-wrap: wrap; gap: 12px; }
    .payment-recovery-actions .action {
      display: inline-flex;
      min-height: 50px;
      align-items: center;
      justify-content: center;
      padding: 12px 21px;
      border: 1px solid #081426;
      border-radius: 10px;
      color: #fff;
      background: #081426;
      font-weight: 700;
      transition: background-color .18s ease, border-color .18s ease;
    }
    .payment-recovery-actions .action:hover,
    .payment-recovery-actions .action:focus {
      color: #fff;
      background: #142847;
      border-color: #142847;
    }
    .payment-recovery-actions .action.secondary { color: #081426; background: #fff; }
    .payment-recovery-note {
      margin: 22px 0 0;
      color: #727b8c;
      font-size: 13px;
      line-height: 1.55;
    }
    @media (max-width: 575px) {
      .payment-recovery-shell { padding: 42px 0 72px; }
      .payment-recovery-card { border-radius: 14px; }
      .payment-recovery-summary { grid-template-columns: 1fr; }
      .payment-recovery-actions, .payment-recovery-actions form,
      .payment-recovery-actions .action { width: 100%; }
    }
    @media (prefers-reduced-motion: reduce) {
      .payment-recovery-actions .action { transition: none; }
    }
  </style>
@endsection

@section('content')
  @php
    $statusLabels = [
      'initialized' => __('Awaiting confirmation'),
      'processing' => __('Finalizing ticket'),
      'verification_required' => __('Action needed'),
      'failed' => __('Not completed'),
      'expired' => __('Expired'),
    ];
  @endphp
  <main class="payment-recovery-shell">
    <div class="container">
      <section class="payment-recovery-card" aria-labelledby="payment-recovery-title">
        <div class="payment-recovery-mark" aria-hidden="true"><i class="fas fa-shield-alt"></i></div>
        <h1 id="payment-recovery-title">{{ __('Confirm your payment') }}</h1>
        <p class="lead-copy">
          {{ __('If your checkout was interrupted or confirmation was delayed, check the same transaction with :provider here. This never creates a second charge.', ['provider' => $providerName]) }}
        </p>

        @if (session('error'))
          <div class="payment-recovery-alert is-error" role="alert">{{ session('error') }}</div>
        @elseif (session('warning'))
          <div class="payment-recovery-alert" role="status">{{ session('warning') }}</div>
        @elseif (session('success'))
          <div class="payment-recovery-alert" role="status">{{ session('success') }}</div>
        @endif

        <div class="payment-recovery-summary" aria-label="{{ __('Payment attempt details') }}">
          <div>
            <span>{{ __('Amount') }}</span>
            <strong>{{ strtoupper($intent->currency) }} {{ number_format((float) $intent->amount, 2) }}</strong>
          </div>
          <div>
            <span>{{ __('Status') }}</span>
            <strong>{{ $statusLabels[$intent->status] ?? __(ucfirst(str_replace('_', ' ', $intent->status))) }}</strong>
          </div>
          <div>
            <span>{{ __('Email') }}</span>
            <strong>{{ $intent->email }}</strong>
          </div>
          <div>
            <span>{{ __('Reference') }}</span>
            <strong>{{ $intent->reference }}</strong>
          </div>
        </div>

        <div class="payment-recovery-actions">
          @unless ($intent->status === 'expired')
            <form method="POST" action="{{ $verifyUrl }}">
              @csrf
              <button class="action" type="submit">
                <i class="fas fa-sync-alt mr-2" aria-hidden="true"></i>{{ __('Check payment status') }}
              </button>
            </form>
          @endunless
          @if ($authorizationUrl && $intent->status === 'initialized' && (!$intent->expires_at || $intent->expires_at->isFuture()))
            <a class="action secondary" href="{{ $authorizationUrl }}" rel="nofollow noopener">
              {{ __('Return to secure :provider checkout', ['provider' => $providerName]) }}
            </a>
          @endif
          @if (in_array($intent->status, ['failed', 'expired'], true))
            <a class="action secondary" href="{{ $newCheckoutUrl ?? route('check-out') }}">{{ __('Start a new checkout') }}</a>
          @endif
        </div>

        <p class="payment-recovery-note">
          <i class="fas fa-lock mr-1" aria-hidden="true"></i>
          {{ __('Your :subject is confirmed only after :provider verifies the payment. Keep this page private because its secure link identifies your payment attempt.', ['provider' => $providerName, 'subject' => $recoverySubject ?? __('tickets')]) }}
        </p>
      </section>
    </div>
  </main>
  @unless (in_array($intent->status, ['failed', 'expired', 'completed'], true))
    <script>
      // Paystack's confirmation usually lands within seconds: look again a few times,
      // then leave the "Check payment status" button to the buyer.
      (function () {
        try {
          var key = 'tica-recovery-{{ substr(hash('sha256', (string) $intent->reference), 0, 16) }}';
          var tries = parseInt(sessionStorage.getItem(key) || '0', 10);
          if (tries >= 5) return;
          sessionStorage.setItem(key, String(tries + 1));
          setTimeout(function () { location.reload(); }, 3000 + tries * 1000);
        } catch (e) {}
      })();
    </script>
  @endunless
@endsection
