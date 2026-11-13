@extends('organizer.layout')

@section('content')
  @php
    $kycStatus = $organizer->kyc_status ?: 'unverified';
    // A verified organiser never sees the identity form again, even if they were verified by an admin without a submission.
    $canSubmit = $kycStatus !== 'verified' && !($submission && in_array($submission->status, ['pending', 'verified'], true));
    $statusText = [
      'verified' => [__('Verified'), 'ok', __('You can be paid. Payouts go to the bank account below.')],
      'pending' => [__('In review'), 'wait', __('We are checking your details. This usually takes 1 working day.')],
      'rejected' => [__('Needs changes'), 'bad', __('Please fix the details below and send them again.')],
    ][$kycStatus] ?? [__('Not verified'), 'idle', __('Tell us who you are and where to send your money. It takes about 5 minutes.')];
    $bankForm = !$bankLoadError && ($kycStatus === 'verified' || ($submission && $submission->status === 'pending'));
  @endphp

  <div class="kv">
    <header class="kv-top">
      <div>
        <p class="kv-crumb"><a href="{{ url('/dashboard') }}">{{ __('Dashboard') }}</a> / {{ __('Identity and bank account') }}</p>
        <h1>{{ __('Identity and bank account') }}</h1>
        <p class="kv-lead">{{ $statusText[2] }}</p>
      </div>
      <span class="kv-badge {{ $statusText[1] }}">{{ $statusText[0] }}</span>
    </header>

    @if ($submission && $submission->status === 'rejected')
      <div class="kv-note bad"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/></svg><p><b>{{ __('What to fix:') }}</b> {{ $submission->rejection_reason }}</p></div>
    @endif

    <div class="kv-grid">
      <div class="kv-main">
        @if ($canSubmit)
          <form method="post" action="{{ route('organizer.kyc.submit') }}" enctype="multipart/form-data" id="kv-form" novalidate>
            @csrf
            <section class="kv-card">
              <div class="kv-ch"><span class="kv-n">1</span><div><h2>{{ __('About you') }}</h2><p>{{ __('Use the details on your ID, exactly as written.') }}</p></div></div>

              <fieldset class="kv-seg">
                <legend>{{ __('You are selling as') }}</legend>
                <label><input type="radio" name="entity_type" value="individual" @checked(old('entity_type', 'individual') === 'individual')><span>{{ __('An individual') }}</span></label>
                <label><input type="radio" name="entity_type" value="business" @checked(old('entity_type') === 'business')><span>{{ __('A registered business') }}</span></label>
              </fieldset>

              <div class="kv-f">
                <label for="kv-legal">{{ __('Legal name') }}</label>
                <input id="kv-legal" name="legal_name" value="{{ old('legal_name') }}" maxlength="191" autocomplete="name" required>
                <small id="kv-legal-hint">{{ __('As it appears on your ID. We can fill this in from your bank account.') }}</small>
                @error('legal_name')<p class="kv-err">{{ $message }}</p>@enderror
              </div>
              <div class="kv-two" id="kv-biz">
                <div class="kv-f"><label for="kv-bname">{{ __('Business name') }}</label><input id="kv-bname" name="business_name" value="{{ old('business_name') }}" maxlength="191">@error('business_name')<p class="kv-err">{{ $message }}</p>@enderror</div>
                <div class="kv-f"><label for="kv-reg">{{ __('CAC registration number') }}</label><input id="kv-reg" name="registration_number" value="{{ old('registration_number') }}" maxlength="100" placeholder="RC 1234567">@error('registration_number')<p class="kv-err">{{ $message }}</p>@enderror</div>
              </div>
              <div class="kv-two">
                <div class="kv-f">
                  <label for="kv-idtype">{{ __('ID type') }}</label>
                  <select id="kv-idtype" name="id_type" required>
                    @foreach (['national_id' => __('National ID (NIN slip or card)'), 'drivers_license' => __('Driver’s licence'), 'passport' => __('International passport'), 'voters_card' => __('Voter’s card')] as $value => $label)
                      <option value="{{ $value }}" @selected(old('id_type') === $value)>{{ $label }}</option>
                    @endforeach
                  </select>
                </div>
                <div class="kv-f">
                  <label for="kv-id">{{ __('Photo or scan of your ID') }}</label>
                  <input id="kv-id" type="file" name="identity_document" accept=".jpg,.jpeg,.png,.pdf" required>
                  <small>{{ __('JPG, PNG or PDF, up to 10 MB') }}</small>
                  @error('identity_document')<p class="kv-err">{{ $message }}</p>@enderror
                </div>
              </div>
              <div class="kv-two">
                <div class="kv-f kv-bizdoc"><label for="kv-bdoc">{{ __('CAC certificate') }}</label><input id="kv-bdoc" type="file" name="business_document" accept=".jpg,.jpeg,.png,.pdf">@error('business_document')<p class="kv-err">{{ $message }}</p>@enderror</div>
                <div class="kv-f"><label for="kv-addr">{{ __('Proof of address') }} <em>{{ __('optional') }}</em></label><input id="kv-addr" type="file" name="address_document" accept=".jpg,.jpeg,.png,.pdf"><small>{{ __('A utility bill or bank statement from the last 3 months') }}</small></div>
              </div>
            </section>

            <section class="kv-card">
              <div class="kv-ch"><span class="kv-n">2</span><div><h2>{{ __('Where we pay you') }}</h2><p>{{ __('We ask your bank for the account name. It has to match the name above.') }}</p></div></div>
              @if ($bankLoadError)
                <div class="kv-note"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/></svg><p>{{ $bankLoadError }}</p></div>
              @else
                <div class="kv-note"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/></svg><p>{{ __('Payouts are paid in naira (NGN) to one account at a time.') }}</p></div>
                @include('organizer.kyc.partials.bank-fields', ['banks' => $banks])
              @endif
            </section>

            <div class="kv-actions">
              <p>{{ __('Your documents are private. Only the TICA review team can see them.') }}</p>
              <button class="btn btn-primary" type="submit" id="kv-submit">{{ __('Send for review') }}</button>
            </div>
          </form>
        @elseif (!$submission)
          <section class="kv-card">
            <div class="kv-ch"><div><h2>{{ __('Your details') }}</h2><p>{{ __('TICA verified your account. To change your legal name, contact TICA support.') }}</p></div></div>
            <dl class="kv-dl">
              <div><dt>{{ __('Verified name') }}</dt><dd>{{ $organizer->verified_legal_name ?: optional($organizer->organizer_info)->name ?: $organizer->username }}</dd></div>
            </dl>
          </section>
        @else
          <section class="kv-card">
            <div class="kv-ch"><div><h2>{{ __('Your details') }}</h2><p>{{ $submission->status === 'verified' ? __('To change your legal name, contact TICA support.') : __('You can’t change these while we review them.') }}</p></div></div>
            <dl class="kv-dl">
              <div><dt>{{ __('Legal name') }}</dt><dd>{{ $submission->legal_name }}</dd></div>
              <div><dt>{{ __('Selling as') }}</dt><dd>{{ $submission->entity_type === 'business' ? __('A registered business') : __('An individual') }}</dd></div>
              @if ($submission->business_name)<div><dt>{{ __('Business name') }}</dt><dd>{{ $submission->business_name }}</dd></div>@endif
              <div><dt>{{ __('Sent') }}</dt><dd>{{ optional($submission->submitted_at)->format('j M Y, g:i A') ?: '—' }}</dd></div>
            </dl>
            @if ($submission->documents->isNotEmpty())
              <div class="kv-docs">
                @foreach ($submission->documents as $document)
                  <a href="{{ route('organizer.kyc.document', $document) }}"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 3h7l5 5v13H7z"/><path d="M14 3v5h5"/></svg>{{ __(ucwords(str_replace('_', ' ', $document->document_type))) }}</a>
                @endforeach
              </div>
            @endif
          </section>
        @endif
      </div>

      <aside class="kv-side">
        <section class="kv-card">
          <div class="kv-ch tight"><div><h2>{{ __('Payout account') }}</h2></div></div>
          @php $shown = $payoutAccount ?: $stagedAccount; @endphp
          @if ($shown)
            <div class="kv-acct">
              <div class="kv-acct-top"><b>{{ $shown->bank_name }}</b><span>NGN</span></div>
              <p class="kv-acct-no">•••• {{ $shown->account_last4 }}</p>
              <p class="kv-acct-name">{{ $shown->resolved_account_name }}</p>
              @if ($payoutAccount)
                <span class="kv-chip ok">{{ __('Verified') }}</span>
                @if ($payoutAccount->hold_until && now()->lt($payoutAccount->hold_until))
                  <p class="kv-small">{{ __('New account. Payouts start :time.', ['time' => $payoutAccount->hold_until->diffForHumans()]) }}</p>
                @endif
              @elseif ($stagedAccount->status === 'name_mismatch')
                <span class="kv-chip bad">{{ __('Name doesn’t match') }}</span>
                <p class="kv-small">{{ __('This name doesn’t match your approved name. Add an account in your name.') }}</p>
              @else
                <span class="kv-chip wait">{{ __('Switches on when you’re approved') }}</span>
              @endif
            </div>
            @if ($bankForm)
              <button class="kv-change" type="button" id="kv-change" aria-expanded="false" aria-controls="kv-bankform">{{ __('Change') }}</button>
            @endif
          @else
            <div class="kv-empty">
              <i aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M3 10l9-6 9 6M5 10v8M19 10v8M9 10v8M15 10v8M3 20h18"/></svg></i>
              <b>{{ __('No bank account yet') }}</b>
              <p>{{ $bankLoadError ?: ($canSubmit ? __('Add it in step 2 and send everything together.') : __('Add the account your sales should go to.')) }}</p>
              @if ($bankForm)
                <button class="btn btn-primary" type="button" id="kv-change" aria-expanded="false" aria-controls="kv-bankform">+ {{ __('Add payout account') }}</button>
              @endif
            </div>
          @endif

          @if (!$canSubmit && $bankForm)
            <form method="post" action="{{ route('organizer.kyc.payout-account') }}" class="kv-bankform" id="kv-bankform" @unless ($errors->has('account_number') || $errors->has('bank_code')) hidden @endunless novalidate>
              @csrf
              @include('organizer.kyc.partials.bank-fields', ['banks' => $banks, 'compact' => true])
              <button class="btn btn-primary" type="submit" id="kv-banksave" disabled>{{ __('Save account') }}</button>
              <p class="kv-small">{{ $kycStatus === 'verified' ? __('We pause payouts for 24 hours after a change, and email you, to protect you from fraud.') : __('It switches on as soon as your identity is approved.') }}</p>
            </form>
          @endif
        </section>

      </aside>
    </div>
  </div>
@endsection

@section('script')
  <script>
    (function () {
      var form = document.getElementById('kv-form');
      var legal = document.getElementById('kv-legal');
      var biz = document.getElementById('kv-bname');
      var pending = {{ $submission && $submission->status === 'pending' ? 'true' : 'false' }};

      // business fields only for businesses
      if (form) {
        var sync = function () {
          var isBiz = form.querySelector('[name=entity_type]:checked').value === 'business';
          document.getElementById('kv-biz').hidden = !isBiz;
          form.querySelector('.kv-bizdoc').hidden = !isBiz;
        };
        [].forEach.call(form.querySelectorAll('[name=entity_type]'), function (r) { r.addEventListener('change', sync); });
        sync();
      }

      // the side "Add / Change" button opens the bank form
      var change = document.getElementById('kv-change'), bankform = document.getElementById('kv-bankform');
      if (change && bankform) change.addEventListener('click', function () {
        bankform.hidden = !bankform.hidden;
        if (change.classList.contains('btn')) change.hidden = !bankform.hidden;
        change.setAttribute('aria-expanded', String(!bankform.hidden));
        if (!bankform.hidden) bankform.querySelector('select').focus();
      });

      // same rule as OrganizerPayoutAccountService::namesMatch; the server checks again on submit
      function tokens(name) {
        var skip = ['LIMITED', 'LTD', 'PLC', 'LLC', 'INC', 'ENTERPRISES', 'ENTERPRISE', 'COMPANY', 'CO'];
        return String(name || '').toUpperCase().replace(/[^A-Z0-9]+/g, ' ').trim().split(' ')
          .filter(function (t, i, a) { return t.length > 1 && skip.indexOf(t) < 0 && a.indexOf(t) === i; }).sort();
      }
      function sameName(a, b) {
        var l = tokens(a), r = tokens(b);
        if (!l.length || !r.length) return false;
        if (l.join(' ') === r.join(' ')) return true;
        var s = l.length <= r.length ? l : r, g = l.length <= r.length ? r : l;
        var shared = s.filter(function (t) { return g.indexOf(t) >= 0; }).length;
        return s.length === 1 ? shared === 1 : shared >= 2 && shared / s.length >= 0.75;
      }

      // live account name lookup, for every bank block on the page
      [].forEach.call(document.querySelectorAll('.kv-bank'), function (box) {
        var bank = box.querySelector('select'), num = box.querySelector('input'), out = box.querySelector('.kv-res');
        var owner = box.closest('form'), save = owner.querySelector('#kv-banksave, #kv-submit');
        var timer = null, seq = 0, last = null;
        function names() { return { legal_name: legal ? legal.value.trim() : '', business_name: biz && !biz.closest('[hidden]') ? biz.value.trim() : '' }; }
        function setState(ok) { box.dataset.ok = ok; if (save && save.id === 'kv-banksave') save.disabled = ok !== 'yes'; }
        function show(html) { out.innerHTML = html; }
        function esc(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
        function lookup() {
          num.value = num.value.replace(/\D/g, '').slice(0, 10);
          clearTimeout(timer); last = null; setState('');
          if (!bank.value || num.value.length < 10) {
            show(num.value.length && num.value.length < 10 ? '<p class="kv-small">' + (10 - num.value.length) + ' more digit' + (num.value.length === 9 ? '' : 's') + '</p>' : '');
            return;
          }
          show('<p class="kv-checking"><i class="kv-spin" aria-hidden="true"></i>Checking with ' + esc(bank.selectedOptions[0].text) + '…</p>');
          var mine = ++seq;
          timer = setTimeout(function () {
            var body = Object.assign({ bank_code: bank.value, account_number: num.value }, pending ? {} : names());
            fetch('/tica-api/organizer/bank/resolve', {
              method: 'POST', credentials: 'same-origin',
              headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'X-Requested-With': 'XMLHttpRequest' },
              body: JSON.stringify(body)
            }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); }).then(function (res) {
              if (mine !== seq) return;
              if (!res.ok) { setState('no'); show('<p class="kv-err">' + esc((res.j.errors && Object.values(res.j.errors)[0][0]) || res.j.message || 'We couldn’t check this account. Try again.') + '</p>'); return; }
              last = res.j; render();
            }).catch(function () { if (mine === seq) show('<p class="kv-err">We couldn’t reach the bank. Check your connection and try again.</p>'); });
          }, 450);
        }
        // compare again in the browser when the typed name changes, so the result stays current
        function render() {
          if (!last) return;
          var matches = last.matches;
          if (!pending && legal && box.closest('#kv-form')) {
            if (!legal.value.trim()) {
              legal.value = last.name;
              document.getElementById('kv-legal-hint').textContent = 'Filled in from your bank. Check it matches your ID.';
            }
            var n = names();
            matches = sameName(n.legal_name, last.name) || (!!n.business_name && sameName(n.business_name, last.name));
          }
          var ok = matches !== false;
          setState(ok ? 'yes' : 'no');
          show('<div class="kv-name ' + (ok ? 'ok' : 'bad') + '"><span>Account name</span><b>' + esc(last.name) + '</b><p>' +
            (ok ? '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>Matches your name' :
              '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 8v5M12 16h.01"/><circle cx="12" cy="12" r="9"/></svg>Doesn’t match the legal name you entered. Use an account in your name' + (names().business_name ? ' or your business’s name' : '') + '.') + '</p></div>');
        }
        num.addEventListener('input', lookup);
        bank.addEventListener('change', lookup);
        if (legal && box.closest('#kv-form')) {
          legal.addEventListener('input', render);
          if (biz) biz.addEventListener('input', render);
        }
        if (num.value.length === 10 && bank.value) lookup();
      });

      // the identity form can't go with a bank account that doesn't match
      if (form) form.addEventListener('submit', function (e) {
        var box = form.querySelector('.kv-bank');
        if (box && box.dataset.ok === 'no') {
          e.preventDefault();
          box.querySelector('input').focus();
        } else if (box && !box.querySelector('input').value) {
          e.preventDefault();
          box.querySelector('.kv-res').innerHTML = '<p class="kv-err">Add the bank account your sales should go to.</p>';
          box.querySelector('select').focus();
        } else if (box && box.dataset.ok !== 'yes') {
          e.preventDefault();
          box.querySelector('.kv-res').innerHTML = '<p class="kv-err">Wait for the bank to confirm the account name.</p>';
        }
      });
    })();
  </script>
@endsection
