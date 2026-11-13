@extends('backend.layout')

@section('style')
  <style>
    .tpa{--b:#2E4FD8;--b50:#EEF2FD;--ink:#0F172A;--soft:#526079;--line:#E2E8F1;--or:#B4682E;--or50:#FEF3E8;--ok:#0B6B3A;--ok50:#E8F7EE;--bad:#9B1C1C;--bad50:#FEF2F2;color:var(--ink)}
    .tpa-head{display:flex;flex-wrap:wrap;align-items:flex-end;justify-content:space-between;gap:16px;margin:8px 0 20px}
    .tpa-head h1{margin:0 0 4px;font-size:26px;font-weight:800;letter-spacing:-.01em}
    .tpa-head p{margin:0;color:var(--soft);font-size:15px}
    .tpa-card{background:#fff;border:1px solid var(--line);border-radius:16px}
    .tpa-prov{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:16px;align-items:center;padding:18px 20px;margin-bottom:20px}
    .tpa-prov h2{margin:0 0 4px;font-size:16px;font-weight:700}
    .tpa-prov p{margin:0;color:var(--soft);font-size:14px}
    .tpa-prov form{display:flex;flex-wrap:wrap;gap:8px;align-items:center}
    .tpa-seg{display:inline-flex;border:1px solid var(--line);border-radius:10px;overflow:hidden}
    .tpa-seg label{margin:0;cursor:pointer}
    .tpa-seg input{position:absolute;opacity:0}
    .tpa-seg span{display:block;padding:9px 16px;font-weight:600;font-size:14px;color:var(--soft)}
    .tpa-seg input:checked+span{background:var(--b);color:#fff}
    .tpa-seg input:focus-visible+span{outline:2px solid var(--b);outline-offset:-2px}
    .tpa-btn{display:inline-flex;align-items:center;gap:6px;height:38px;padding:0 14px;border-radius:9px;border:1px solid var(--line);background:#fff;color:var(--ink);font-weight:600;font-size:13.5px;cursor:pointer;text-decoration:none!important}
    .tpa-btn.pri{background:var(--b);border-color:var(--b);color:#fff}
    .tpa-btn.ok{background:var(--ok50);border-color:#BFE6CE;color:var(--ok)}
    .tpa-btn.bad{background:var(--bad50);border-color:#FBD5D5;color:var(--bad)}
    .tpa-tabs{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:14px}
    .tpa-tabs a{padding:8px 14px;border-radius:999px;border:1px solid var(--line);background:#fff;color:var(--soft);font-weight:600;font-size:13.5px;text-decoration:none}
    .tpa-tabs a.on{background:var(--ink);border-color:var(--ink);color:#fff}
    .tpa-tabs em{font-style:normal;margin-left:6px;padding:1px 7px;border-radius:999px;background:var(--or50);color:var(--or);font-size:12px}
    .tpa-tabs a.on em{background:rgba(255,255,255,.18);color:#fff}
    .tpa-search{display:flex;gap:8px;margin-left:auto}
    .tpa-search input{height:38px;border:1px solid var(--line);border-radius:9px;padding:0 12px;min-width:240px}
    .tpa-table{width:100%;border-collapse:collapse}
    .tpa-table th{padding:12px 16px;font-size:12px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--soft);background:#F8FAFD;border-bottom:1px solid var(--line);text-align:left}
    .tpa-table td{padding:14px 16px;border-bottom:1px solid var(--line);vertical-align:top;font-size:14.5px}
    .tpa-table tr:last-child td{border-bottom:0}
    .tpa-table small{display:block;color:var(--soft);font-size:12.5px;margin-top:2px}
    .tpa-st{display:inline-block;padding:3px 10px;border-radius:999px;font-size:12.5px;font-weight:700}
    .tpa-st.needs_review{background:var(--or50);color:var(--or)}
    .tpa-st.verified{background:var(--ok50);color:var(--ok)}
    .tpa-st.rejected,.tpa-st.locked{background:var(--bad50);color:var(--bad)}
    .tpa-st.replaced,.tpa-st.pending_kyc,.tpa-st.name_mismatch{background:#F1F4F9;color:var(--soft)}
    .tpa-names{display:grid;gap:4px}
    .tpa-names b{font-weight:600}
    .tpa-names .mis{color:var(--or)}
    .tpa-act{display:flex;flex-wrap:wrap;gap:6px}
    .tpa-act form{margin:0}
    .tpa-rej{display:none;margin-top:8px;gap:6px}
    .tpa-rej.open{display:flex}
    .tpa-rej input{flex:1;height:38px;border:1px solid var(--line);border-radius:9px;padding:0 10px;min-width:200px}
    .tpa-empty{padding:48px 20px;text-align:center;color:var(--soft)}
    .tpa-note{margin:8px 0 0;font-size:13px;color:var(--or)}
    @media (max-width:900px){.tpa-prov{grid-template-columns:1fr}.tpa-search{margin-left:0;width:100%}.tpa-search input{flex:1;min-width:0}}
  </style>
@endsection

@section('content')
  <div class="tpa">
    <div class="tpa-head">
      <div>
        <h1>Payout accounts</h1>
        <p>Bank accounts organisers get paid into. Accounts whose bank name didn't match the verified identity wait here for a decision.</p>
      </div>
    </div>

    <section class="tpa-card tpa-prov" aria-labelledby="tpa-prov-h">
      <div>
        <h2 id="tpa-prov-h">Payout provider</h2>
        <p>Checks new bank accounts and sends new payouts. Accounts already saved keep using the provider they were checked with.</p>
        @unless ($transfersEnabled)
          <p class="tpa-note">Transfers are switched off (PAYSTACK_TRANSFERS_ENABLED=false), so payouts are marked paid by hand.</p>
        @endunless
      </div>
      <div class="tpa-act">
        <form method="POST" action="{{ route('admin.payout_provider.update') }}">
          @csrf
          <div class="tpa-seg" role="radiogroup" aria-label="Payout provider">
            @foreach (['paystack' => 'Paystack', 'monnify' => 'Monnify'] as $key => $label)
              <label><input type="radio" name="provider" value="{{ $key }}" {{ $provider === $key ? 'checked' : '' }} data-autosubmit><span>{{ $label }}</span></label>
            @endforeach
          </div>
        </form>
        <form method="POST" action="{{ route('admin.payout_provider.test') }}">
          @csrf
          <input type="hidden" name="provider" value="{{ $provider }}">
          <button class="tpa-btn" type="submit">Test connection</button>
        </form>
      </div>
    </section>

    <div class="tpa-tabs">
      @foreach ($filters as $key => $label)
        @php($n = $key === 'all' ? null : (int) ($counts[$key] ?? 0))
        <a href="{{ route('admin.payout_accounts.index', ['status' => $key, 'q' => $search ?: null]) }}" class="{{ $filter === $key ? 'on' : '' }}">{{ $label }}@if ($key === 'needs_review' && $n)<em>{{ $n }}</em>@endif</a>
      @endforeach
      <form class="tpa-search" method="GET" action="{{ route('admin.payout_accounts.index') }}" role="search">
        <input type="hidden" name="status" value="{{ $filter }}">
        <label class="sr-only" for="tpa-q">Search payout accounts</label>
        <input id="tpa-q" type="search" name="q" value="{{ $search }}" placeholder="Organiser, email, account name or last 4">
        <button class="tpa-btn" type="submit">Search</button>
      </form>
    </div>

    <div class="tpa-card" style="overflow-x:auto">
      @if ($accounts->isEmpty())
        <div class="tpa-empty">{{ $filter === 'needs_review' ? 'Nothing waiting for review.' : 'No payout accounts here.' }}</div>
      @else
        <table class="tpa-table">
          <thead><tr><th>Organiser</th><th>Names</th><th>Bank</th><th>Status</th><th>Actions</th></tr></thead>
          <tbody>
            @foreach ($accounts as $account)
              @php($org = $organizers[$account->organizer_id] ?? null)
              <tr>
                <td>
                  <b>{{ $org?->username ?? 'Organiser #' . $account->organizer_id }}</b>
                  <small>{{ $org?->email }}</small>
                  <small>Added {{ $account->created_at?->format('j M Y, g:i A') }}</small>
                </td>
                <td class="tpa-names">
                  <span><small>Verified identity</small><b>{{ $org?->verified_legal_name ?: 'Not verified' }}</b></span>
                  <span><small>Name at the bank</small><b class="{{ $account->name_match_status === 'mismatch' ? 'mis' : '' }}">{{ $account->resolved_account_name }}</b></span>
                </td>
                <td>
                  <b>{{ $account->bank_name }}</b>
                  <small>•••• {{ $account->account_last4 }} · {{ ucfirst($account->provider) }}</small>
                  @if ($account->hold_until && now()->lt($account->hold_until))
                    <small>On hold until {{ $account->hold_until->format('j M, g:i A') }}</small>
                  @endif
                </td>
                <td>
                  <span class="tpa-st {{ $account->status }}">{{ str_replace('_', ' ', ucfirst($account->status)) }}</span>
                  @if ($account->review_reason)
                    <small>{{ $account->review_reason }}</small>
                  @endif
                </td>
                <td>
                  <div class="tpa-act">
                    @if ($account->status === 'needs_review')
                      <form method="POST" action="{{ route('admin.payout_accounts.review', $account->public_id) }}">
                        @csrf
                        <input type="hidden" name="decision" value="approve">
                        <button class="tpa-btn ok" type="submit">Approve</button>
                      </form>
                      <button class="tpa-btn bad" type="button" data-reject="{{ $account->public_id }}" aria-expanded="false" aria-controls="rej-{{ $account->public_id }}">Reject</button>
                    @endif
                    <form method="POST" action="{{ route('admin.payout_accounts.reverify', $account->public_id) }}">
                      @csrf
                      <button class="tpa-btn" type="submit">Re-verify</button>
                    </form>
                    @if (in_array($account->status, ['verified', 'needs_review'], true))
                      <form method="POST" action="{{ route('admin.payout_accounts.lock', $account->public_id) }}" data-confirm="Lock this account? No payouts will go to it.">
                        @csrf
                        <button class="tpa-btn" type="submit">Lock</button>
                      </form>
                    @endif
                  </div>
                  @if ($account->status === 'needs_review')
                    <form class="tpa-rej" id="rej-{{ $account->public_id }}" method="POST" action="{{ route('admin.payout_accounts.review', $account->public_id) }}">
                      @csrf
                      <input type="hidden" name="decision" value="reject">
                      <label class="sr-only" for="why-{{ $account->public_id }}">Reason for rejecting</label>
                      <input id="why-{{ $account->public_id }}" name="reason" maxlength="500" required placeholder="Reason the organiser will see">
                      <button class="tpa-btn bad" type="submit">Send</button>
                    </form>
                  @endif
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      @endif
    </div>
    <div class="mt-3">{{ $accounts->links() }}</div>
  </div>
@endsection

@section('script')
  <script>
    document.addEventListener('change', function (e) {
      if (e.target.matches('[data-autosubmit]')) e.target.form.submit();
    });
    document.addEventListener('submit', function (e) {
      var msg = e.target.getAttribute('data-confirm');
      if (msg && !window.confirm(msg)) e.preventDefault();
    });
    document.addEventListener('click', function (e) {
      var b = e.target.closest('[data-reject]');
      if (!b) return;
      var f = document.getElementById('rej-' + b.dataset.reject);
      var open = f.classList.toggle('open');
      b.setAttribute('aria-expanded', open ? 'true' : 'false');
      if (open) f.querySelector('input[name=reason]').focus();
    });
  </script>
@endsection
