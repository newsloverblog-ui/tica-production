@extends('backend.layout')

@php
  $symbol = \App\Support\TicaCurrency::symbol($base);
  $fee = (float) ($basic->booking_fee_percentage ?? 6);
  $on = fn ($key) => (bool) ($platform['features'][$key] ?? true);
  $sections = [
    'ta-fees' => __('Booking fee and checkout'),
    'ta-features' => __('Event features'),
    'ta-currencies' => __('Currencies'),
    'ta-organisers' => __('Organiser sign-up'),
    'ta-more' => __('More settings'),
  ];
@endphp

@section('content')
  <header class="ta-head">
    <p class="ta-kicker">{{ __('Settings') }}</p>
    <h1>{{ __('Platform settings') }}</h1>
    <p>{{ __('Choose how TICA works for every organiser and buyer: what you charge, what organisers can switch on when they create an event, and which currencies they can sell in.') }}</p>
  </header>

  @if ($errors->any())
    <div class="ta-alert bad" role="alert">
      <b>{{ __('Nothing was saved.') }}</b>
      <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
  @endif

  <div class="ta-set">
    <nav class="ta-toc" aria-label="{{ __('Sections on this page') }}">
      @foreach ($sections as $id => $label)
        <a href="#{{ $id }}">{{ $label }}</a>
      @endforeach
    </nav>

    <form class="ta-forms" method="POST" action="{{ route('admin.tica.platform.update') }}" id="ta-platform">
      @csrf

      <section class="ta-card" id="ta-fees" aria-labelledby="ta-fees-h">
        <div class="ta-card-h">
          <span class="ta-ic orange" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 8h16v3a2 2 0 0 0 0 3v3H4v-3a2 2 0 0 0 0-3zM14 8v2M14 13v1M14 16v1"/></svg></span>
          <div><h2 id="ta-fees-h">{{ __('Booking fee and checkout') }}</h2><p>{{ __('TICA earns this fee on every paid ticket. Free tickets have no fee.') }}</p></div>
        </div>

        <div class="ta-row">
          <div><label for="ta-fee">{{ __('Booking fee') }}</label><small>{{ __('A percentage of each paid ticket, up to 30%.') }}</small></div>
          <div class="ta-pct"><input class="form-control" id="ta-fee" name="booking_fee_percentage" type="number" min="0" max="30" step="0.01" value="{{ old('booking_fee_percentage', $fee) }}" required><span>%</span></div>
        </div>

        <div class="ta-row">
          <div><span class="ta-l" id="ta-payer-l">{{ __('Who pays it on a new event') }}</span><small>{{ __('Organisers start with this choice and can change it per event, if you allow it below.') }}</small></div>
          <div class="ta-seg" role="radiogroup" aria-labelledby="ta-payer-l">
            <label><input type="radio" name="default_fee_payer" value="buyer" @checked(old('default_fee_payer', $platform['default_fee_payer']) === 'buyer')><span>{{ __('Buyers pay it') }}</span></label>
            <label><input type="radio" name="default_fee_payer" value="organizer" @checked(old('default_fee_payer', $platform['default_fee_payer']) === 'organizer')><span>{{ __('Organisers absorb it') }}</span></label>
          </div>
        </div>

        <div class="ta-example" aria-live="polite">
          <span>{{ __('On a :price ticket', ['price' => $symbol . '5,000']) }}</span>
          <b id="ta-ex-buyer"></b>
          <b id="ta-ex-org"></b>
          <b id="ta-ex-tica"></b>
        </div>

        <div class="ta-row">
          <div><label for="ta-guest">{{ __('Guest checkout') }}</label><small>{{ __('Buyers can get tickets without creating an account. They still receive their tickets by email.') }}</small></div>
          <input type="hidden" name="guest_checkout" value="0">
          <input class="ta-sw" id="ta-guest" type="checkbox" name="guest_checkout" value="1" @checked((int) ($basic->event_guest_checkout_status ?? 0) === 1)>
        </div>
      </section>

      <section class="ta-card" id="ta-features" aria-labelledby="ta-features-h">
        <div class="ta-card-h">
          <span class="ta-ic blue" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="4" y="4" width="7" height="7" rx="1.5"/><rect x="13" y="4" width="7" height="7" rx="1.5"/><rect x="4" y="13" width="7" height="7" rx="1.5"/><path d="M16.5 13v7M13 16.5h7"/></svg></span>
          <div><h2 id="ta-features-h">{{ __('Event features') }}</h2><p>{{ __('What organisers can switch on when they create or edit an event. Turning a feature off hides it for new events; events that already use it keep it.') }}</p></div>
        </div>
        <ul class="ta-list">
          @foreach ($features as $key => [$label, $help])
            <li>
              <div><label for="ta-f-{{ $key }}">{{ __($label) }}</label><small>{{ __($help) }}</small></div>
              <input type="hidden" name="features[{{ $key }}]" value="0">
              <input class="ta-sw" id="ta-f-{{ $key }}" type="checkbox" name="features[{{ $key }}]" value="1" @checked($on($key))>
            </li>
          @endforeach
        </ul>
      </section>

      <section class="ta-card" id="ta-currencies" aria-labelledby="ta-cur-h">
        <div class="ta-card-h">
          <span class="ta-ic green" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="9" cy="9" r="5.5"/><path d="M14.5 9.6A5.5 5.5 0 1 1 9.6 14.5"/></svg></span>
          <div><h2 id="ta-cur-h">{{ __('Currencies') }}</h2><p>{{ __('Events sell in one currency, picked from the event\'s country. Sales in other currencies are kept in separate balances, so amounts never mix.') }}</p></div>
        </div>
        <p class="ta-note">{{ __(':name (:base) is your base currency and is always on. Paid tickets need a currency your payment provider can charge in; the rest can only be used for free events.', ['name' => __($currencies[$base]['name'] ?? $base), 'base' => $base]) }} <a href="{{ route('admin.basic_settings.general_settings') }}">{{ __('Change the base currency') }}</a></p>
        <div class="ta-curs">
          @foreach ($currencies as $code => $meta)
            @php($isBase = $code === $base)
            <label class="ta-cur @if ($isBase) base @endif">
              <input type="checkbox" name="currencies[]" value="{{ $code }}" @checked($isBase || in_array($code, old('currencies', $platform['currencies']), true)) @disabled($isBase)>
              <span class="ta-cur-b">
                <b>{{ $code }} <i>{{ $meta['symbol'] }}</i></b>
                <small>{{ __($meta['name']) }}</small>
                <span class="ta-tags">
                  @if ($isBase)<em class="tag-blue">{{ __('Base currency') }}</em>@endif
                  @if (in_array($code, $paystack, true))<em class="tag-green">{{ __('Card payments') }}</em>@else<em class="tag-grey">{{ __('Free events only') }}</em>@endif
                  @if (!empty($eventsByCurrency[$code]))<em class="tag-orange">{{ trans_choice(':count event|:count events', $eventsByCurrency[$code], ['count' => $eventsByCurrency[$code]]) }}</em>@endif
                </span>
              </span>
            </label>
          @endforeach
        </div>
      </section>

      <section class="ta-card" id="ta-organisers" aria-labelledby="ta-org-h">
        <div class="ta-card-h">
          <span class="ta-ic purple" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><path d="m15.5 12 2 2 4-4"/></svg></span>
          <div><h2 id="ta-org-h">{{ __('Organiser sign-up') }}</h2><p>{{ __('Decide what happens when someone registers to sell tickets on TICA.') }}</p></div>
        </div>
        <ul class="ta-list">
          <li>
            <div><label for="ta-o-email">{{ __('Confirm their email address') }}</label><small>{{ __('New organisers click a link in their email before they can sign in.') }}</small></div>
            <input type="hidden" name="organizer_email_verification" value="0">
            <input class="ta-sw" id="ta-o-email" type="checkbox" name="organizer_email_verification" value="1" @checked((int) ($basic->organizer_email_verification ?? 0) === 1)>
          </li>
          <li>
            <div><label for="ta-o-approve">{{ __('Approve each organiser') }}</label><small>{{ __('An admin approves new organisers before they can publish events.') }}</small></div>
            <input type="hidden" name="organizer_admin_approval" value="0">
            <input class="ta-sw" id="ta-o-approve" type="checkbox" name="organizer_admin_approval" value="1" @checked((int) ($basic->organizer_admin_approval ?? 0) === 1)>
          </li>
        </ul>
        <div class="ta-field">
          <label for="ta-o-notice">{{ __('Message while they wait for approval') }}</label>
          <textarea class="form-control" id="ta-o-notice" name="admin_approval_notice" rows="3" maxlength="1000" placeholder="{{ __('Thanks for joining TICA. We review new organisers within one working day.') }}">{{ old('admin_approval_notice', $basic->admin_approval_notice ?? '') }}</textarea>
        </div>
      </section>

      <div class="ta-save">
        <p>{{ __('Changes apply to everyone as soon as you save.') }}</p>
        <button class="btn btn-primary" type="submit">{{ __('Save settings') }}</button>
      </div>
    </form>

    <section class="ta-card ta-more" id="ta-more" aria-labelledby="ta-more-h">
      <div class="ta-card-h"><div><h2 id="ta-more-h">{{ __('More settings') }}</h2><p>{{ __('Everything else, each on its own page.') }}</p></div></div>
      <div class="ta-links">
        <a href="{{ route('admin.basic_settings.general_settings') }}"><b>{{ __('Brand and contact') }}</b><small>{{ __('Site name, logo, favicon, time zone and base currency') }}</small></a>
        <a href="{{ route('admin.payment_gateways.online_gateways') }}"><b>{{ __('Payments') }}</b><small>{{ __('Paystack and Monnify keys, and other ways to pay') }}</small></a>
        <a href="{{ route('admin.withdraw.payment_method', ['language' => $defaultLang->code]) }}"><b>{{ __('Payout methods') }}</b><small>{{ __('How organisers get paid, with limits and charges') }}</small></a>
        <a href="{{ route('admin.basic_settings.mail_from_admin') }}"><b>{{ __('Email sending') }}</b><small>{{ __('SMTP server and the sender name buyers see') }}</small></a>
        <a href="{{ route('admin.basic_settings.mail_templates') }}"><b>{{ __('Email templates') }}</b><small>{{ __('Tickets, receipts, reminders and sign-up emails') }}</small></a>
        <a href="{{ route('admin.basic_settings.plugins') }}"><b>{{ __('Sign-in and security') }}</b><small>{{ __('Google sign-in, reCAPTCHA and analytics') }}</small></a>
        <a href="{{ route('admin.basic_settings.seo', ['language' => $defaultLang->code]) }}"><b>{{ __('Search engines') }}</b><small>{{ __('Page descriptions and keywords for Google') }}</small></a>
        <a href="{{ route('admin.pwa.scanner') }}"><b>{{ __('Gate scanner app') }}</b><small>{{ __('Name and icons of the installable scanner') }}</small></a>
        <a href="{{ route('admin.basic_settings.maintenance_mode') }}"><b>{{ __('Maintenance mode') }}</b><small>{{ __('Take the site offline while you work on it') }}</small></a>
      </div>
    </section>
  </div>
@endsection

@section('script')
  <script>
    (function () {
      var fee = document.getElementById('ta-fee'), sym = @json($symbol);
      function money(n) { return sym + Math.round(n).toLocaleString('en-NG'); }
      function payer() { var c = document.querySelector('[name=default_fee_payer]:checked'); return c ? c.value : 'buyer'; }
      function draw() {
        var pct = Math.max(0, Math.min(30, parseFloat(fee.value) || 0)), cut = 5000 * pct / 100, buyerPays = payer() === 'buyer';
        document.getElementById('ta-ex-buyer').textContent = @json(__('Buyer pays')) + ' ' + money(buyerPays ? 5000 + cut : 5000);
        document.getElementById('ta-ex-org').textContent = @json(__('Organiser gets')) + ' ' + money(buyerPays ? 5000 : 5000 - cut);
        document.getElementById('ta-ex-tica').textContent = @json(__('TICA earns')) + ' ' + money(cut);
      }
      fee.addEventListener('input', draw);
      [].forEach.call(document.querySelectorAll('[name=default_fee_payer]'), function (r) { r.addEventListener('change', draw); });
      var absorb = document.getElementById('ta-f-organizer_pays'), orgOpt = document.querySelector('[name=default_fee_payer][value=organizer]');
      function lock() { orgOpt.disabled = !absorb.checked; if (!absorb.checked && orgOpt.checked) { document.querySelector('[name=default_fee_payer][value=buyer]').checked = true; draw(); } }
      absorb.addEventListener('change', lock);
      lock(); draw();
    })();
  </script>
@endsection
