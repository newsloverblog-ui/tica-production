@extends('organizer.layout')

@section('style')
  @include('organizer.event.guest-list._styles')
@endsection

@section('content')
  @php
    $title = fn ($e) => $e?->information?->title ?: __('Event #:id', ['id' => $e?->id]);
    $statusTone = ['accepted' => ['green', __('Going')], 'pending' => ['amber', __('Waiting')], 'declined' => ['grey', __('Declined')], 'cancelled' => ['grey', __('Cancelled')], 'expired' => ['grey', __('Expired')]];
    $tabs = ['' => [__('All'), $stats['invited']], 'accepted' => [__('Going'), $stats['accepted']], 'pending' => [__('Waiting'), $stats['pending']], 'declined' => [__('Declined'), $stats['declined']], 'checked_in' => [__('Checked in'), $stats['checked_in']]];
    $inviteErrors = $errors->hasAny(['name', 'email', 'party_size', 'source_attested']) && !$errors->has('guest_file');
    $openDialog = $inviteErrors ? 'gl-invite' : ($errors->has('guest_file') ? 'gl-import' : ($errors->has('export') ? 'gl-export' : null));
  @endphp

  <div class="gl" data-gl @if ($openDialog) data-gl-autoopen="{{ $openDialog }}" @endif>
    <header class="gl-head">
      <div>
        <h1>{{ __('Guest list & RSVPs') }}</h1>
        <p>{{ __('Invite guests, see who is coming and check them in at the door.') }}</p>
      </div>
      @if ($event)
        <div class="gl-head__actions">
          <button class="t-btn t-btn--secondary" type="button" data-gl-open="gl-import">{{ __('Import') }}</button>
          <button class="t-btn t-btn--secondary" type="button" data-gl-open="gl-export">{{ __('Export') }}</button>
          <button class="t-btn t-btn--primary" type="button" data-gl-open="gl-invite"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>{{ __('Invite guest') }}</button>
        </div>
      @endif
    </header>

    @if ($events->isEmpty())
      <section class="gl-card gl-empty">
        <span class="gl-empty__icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="4" y="5" width="16" height="15" rx="2"/><path d="M8 3v4M16 3v4M4 10h16"/></svg></span>
        <h2>{{ __('Create an event first') }}</h2>
        <p>{{ __('Guest lists belong to an event. Once you have one, you can invite guests here.') }}</p>
        <a class="t-btn t-btn--primary" href="{{ route('organizer.event_management.event') }}">{{ __('Go to events') }}</a>
      </section>
    @else
      <form class="gl-event" method="GET" action="{{ route('organizer.event.guest-list.index') }}" data-gl-event>
        <label for="gl-event-select">{{ __('Event') }}</label>
        <select id="gl-event-select" class="t-select" name="event_id">
          @foreach ($events as $option)
            <option value="{{ $option->id }}" @selected($event && $event->id === $option->id)>{{ $title($option) }}</option>
          @endforeach
        </select>
        <noscript><button class="t-btn t-btn--secondary" type="submit">{{ __('Open') }}</button></noscript>
      </form>

      <section class="gl-stats" aria-label="{{ __('RSVP summary') }}">
        <div class="gl-stat"><span>{{ __('Invited') }}</span><b>{{ number_format($stats['invited']) }}</b><small>{{ trans_choice(':count person expected|:count people expected', $stats['people'], ['count' => number_format($stats['people'])]) }}</small></div>
        <div class="gl-stat"><span>{{ __('Going') }}</span><b>{{ number_format($stats['accepted']) }}</b><small>{{ __('Accepted the invite') }}</small></div>
        <div class="gl-stat"><span>{{ __('Waiting') }}</span><b>{{ number_format($stats['pending']) }}</b><small>{{ __('Not answered yet') }}</small></div>
        <div class="gl-stat"><span>{{ __('Checked in') }}</span><b>{{ number_format($stats['checked_in']) }}</b>
          <small>{{ $stats['accepted'] ? __(':pct% of guests going', ['pct' => (int) round($stats['checked_in'] / max(1, $stats['accepted']) * 100)]) : __('At the door') }}</small>
          <i class="gl-meter" aria-hidden="true"><i style="width:{{ $stats['accepted'] ? min(100, (int) round($stats['checked_in'] / $stats['accepted'] * 100)) : 0 }}%"></i></i>
        </div>
      </section>

      <section class="gl-card gl-list" aria-labelledby="gl-list-h">
        <div class="gl-list__head">
          <h2 id="gl-list-h">{{ $title($event) }}</h2>
          <nav class="gl-tabs" aria-label="{{ __('Filter guests') }}">
            @foreach ($tabs as $key => [$label, $count])
              <a class="gl-tab{{ ($filter ?? '') === $key ? ' is-on' : '' }}" href="{{ route('organizer.event.guest-list.index', array_filter(['event_id' => $event->id, 'status' => $key ?: null])) }}" @if (($filter ?? '') === $key) aria-current="page" @endif>{{ $label }} <span>{{ number_format($count) }}</span></a>
            @endforeach
          </nav>
        </div>

        @if ($invitations->isEmpty())
          <div class="gl-empty gl-empty--inline">
            <span class="gl-empty__icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="3.5"/><path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6M17 8v6M14 11h6"/></svg></span>
            @if ($filter)
              <h3>{{ __('No guests here') }}</h3>
              <p>{{ __('Nobody on this list matches this filter yet.') }}</p>
              <a class="t-btn t-btn--secondary" href="{{ route('organizer.event.guest-list.index', ['event_id' => $event->id]) }}">{{ __('Show everyone') }}</a>
            @else
              <h3>{{ __('No guests yet') }}</h3>
              <p>{{ __('Invite your first guest, or import a list from a spreadsheet.') }}</p>
              <div class="gl-empty__actions">
                <button class="t-btn t-btn--primary" type="button" data-gl-open="gl-invite">{{ __('Invite guest') }}</button>
                <button class="t-btn t-btn--secondary" type="button" data-gl-open="gl-import">{{ __('Import a list') }}</button>
              </div>
            @endif
          </div>
        @else
          <div class="gl-table" role="table" aria-label="{{ __('Guests') }}">
            <div class="gl-row gl-row--head" role="row">
              <span role="columnheader">{{ __('Guest') }}</span>
              <span role="columnheader">{{ __('Party') }}</span>
              <span role="columnheader">{{ __('RSVP') }}</span>
              <span role="columnheader">{{ __('Invite') }}</span>
              <span role="columnheader"><span class="t-sr">{{ __('Actions') }}</span></span>
            </div>
            @foreach ($invitations as $invitation)
              @php
                [$tone, $label] = $statusTone[$invitation->status] ?? ['grey', ucfirst($invitation->status)];
                $initials = collect(preg_split('/\s+/', trim((string) $invitation->name)))->filter()->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('');
              @endphp
              <div class="gl-row" role="row">
                <span class="gl-guest" role="cell">
                  <span class="gl-avatar" aria-hidden="true">{{ $initials ?: '?' }}</span>
                  <span class="gl-guest__text"><b>{{ $invitation->name }}</b><small>{{ $invitation->email }}</small></span>
                </span>
                <span class="gl-cell" role="cell"><span class="gl-cell__label">{{ __('Party') }}</span>{{ $invitation->party_size == 1 ? __('Just them') : __(':n people', ['n' => $invitation->party_size]) }}</span>
                <span class="gl-cell" role="cell">
                  <span class="gl-cell__label">{{ __('RSVP') }}</span>
                  @if ($invitation->checked_in_at)
                    <span class="gl-pill gl-pill--blue">{{ __('Checked in') }} · {{ $invitation->checked_in_at->format('H:i') }}</span>
                  @else
                    <span class="gl-pill gl-pill--{{ $tone }}">{{ $label }}</span>
                  @endif
                  @if ($invitation->marketing_consent_at)<small class="gl-note">{{ __('Happy to hear from you') }}</small>@endif
                </span>
                <span class="gl-cell gl-muted" role="cell"><span class="gl-cell__label">{{ __('Invite') }}</span>{{ ucfirst(str_replace('_', ' ', (string) $invitation->delivery_status)) }}</span>
                <span class="gl-actions" role="cell">
                  @if ($invitation->status === 'accepted' && !$invitation->checked_in_at)
                    <form method="POST" action="{{ route('organizer.event.guest-list.check-in', $invitation) }}">@csrf<button class="t-btn t-btn--primary t-btn--sm" type="submit">{{ __('Check in') }}</button></form>
                  @endif
                  @if (!$invitation->checked_in_at && $invitation->status !== 'cancelled')
                    <form method="POST" action="{{ route('organizer.event.guest-list.cancel', $invitation) }}" data-gl-confirm="{{ __('Cancel invite?') }}">@csrf<button class="gl-link gl-link--danger" type="submit">{{ __('Cancel') }}</button></form>
                  @endif
                </span>
              </div>
            @endforeach
          </div>
          @if ($invitations->hasPages())
            <div class="gl-pages">{{ $invitations->links() }}</div>
          @endif
        @endif
        <p class="gl-privacy"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>{{ __('Guest details are encrypted and used only for this event. Guests must accept before they can be checked in.') }}</p>
      </section>

      @if ($imports->isNotEmpty())
        <details class="gl-card gl-imports">
          <summary><span><b>{{ __('Recent imports') }}</b><small>{{ trans_choice(':count import|:count imports', $imports->count(), ['count' => $imports->count()]) }}</small></span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg></summary>
          <ul>
            @foreach ($imports as $import)
              @php $iTone = $import->status === 'completed' ? 'green' : ($import->status === 'failed' ? 'red' : 'amber'); @endphp
              <li>
                <div class="gl-imports__row">
                  <span><b>{{ $import->created_at->format('d M Y, H:i') }}</b><small>{{ __(':ok added · :bad skipped', ['ok' => $import->accepted_rows, 'bad' => $import->rejected_rows]) }}</small></span>
                  <span class="gl-pill gl-pill--{{ $iTone }}">{{ ucfirst(str_replace('_', ' ', $import->status)) }}</span>
                </div>
                @if ($import->rows->isNotEmpty())
                  <ul class="gl-imports__errors">
                    @foreach ($import->rows->take(20) as $row)<li>{{ __('Row :n', ['n' => $row->row_number]) }}: {{ $row->error_message }}</li>@endforeach
                    @if ($import->rows->count() > 20)<li>{{ __('More rows were skipped; fix the file and import again.') }}</li>@endif
                  </ul>
                @endif
              </li>
            @endforeach
          </ul>
        </details>
      @endif
    @endif
  </div>

  @if ($event)
    {{-- Invite --}}
    <dialog class="gl-dlg" id="gl-invite" aria-labelledby="gl-invite-h">
      <form method="POST" action="{{ route('organizer.event.guest-list.store') }}" class="gl-dlg__form">
        @csrf
        <input type="hidden" name="event_id" value="{{ $event->id }}">
        <div class="gl-dlg__head"><div><h2 id="gl-invite-h">{{ __('Invite a guest') }}</h2><p>{{ __('They get a private link to say yes or no. It expires on its own.') }}</p></div><button class="gl-x" type="button" data-gl-close aria-label="{{ __('Close') }}"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg></button></div>
        <div class="gl-dlg__body">
          <label class="gl-field"><span>{{ __('Name') }}</span><input class="t-input" name="name" value="{{ old('name') }}" maxlength="150" required autocomplete="off">@error('name')<small class="gl-err">{{ $message }}</small>@enderror</label>
          <label class="gl-field"><span>{{ __('Email') }}</span><input class="t-input" type="email" name="email" value="{{ old('email') }}" maxlength="191" required autocomplete="off">@error('email')<small class="gl-err">{{ $message }}</small>@enderror</label>
          <label class="gl-field gl-field--short"><span>{{ __('Party size') }}</span><input class="t-input" type="number" name="party_size" value="{{ old('party_size', 1) }}" min="1" max="20" required><small>{{ __('Including the guest, up to 20.') }}</small>@error('party_size')<small class="gl-err">{{ $message }}</small>@enderror</label>
          <label class="gl-check"><input type="checkbox" name="source_attested" value="1" required @checked(old('source_attested'))><span>{{ __('I know this guest and may contact them about this event.') }}</span></label>
          @error('source_attested')<small class="gl-err">{{ $message }}</small>@enderror
        </div>
        <div class="gl-dlg__foot"><button class="t-btn t-btn--ghost" type="button" data-gl-close>{{ __('Cancel') }}</button><button class="t-btn t-btn--primary" type="submit">{{ __('Send invite') }}</button></div>
      </form>
    </dialog>

    {{-- Import --}}
    <dialog class="gl-dlg" id="gl-import" aria-labelledby="gl-import-h">
      <form method="POST" enctype="multipart/form-data" action="{{ route('organizer.event.guest-list.import') }}" class="gl-dlg__form">
        @csrf
        <input type="hidden" name="event_id" value="{{ $event->id }}">
        <div class="gl-dlg__head"><div><h2 id="gl-import-h">{{ __('Import guests') }}</h2><p>{{ __('Upload a CSV with name, email and party size. Each guest gets their own invite.') }}</p></div><button class="gl-x" type="button" data-gl-close aria-label="{{ __('Close') }}"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg></button></div>
        <div class="gl-dlg__body">
          <a class="gl-template" href="{{ route('organizer.event.guest-list.import-template') }}"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 4v11M7 10l5 5 5-5M5 20h14"/></svg><span><b>{{ __('Download the template') }}</b><small>{{ __('Fill it in, then upload it below.') }}</small></span></a>
          <label class="gl-drop" data-gl-drop>
            <input type="file" name="guest_file" accept=".csv,text/csv" required>
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 16V5M7 10l5-5 5 5M5 19h14"/></svg>
            <span data-gl-file>{{ __('Choose a CSV file or drop it here') }}</span>
          </label>
          @error('guest_file')<small class="gl-err">{{ $message }}</small>@enderror
          <label class="gl-check"><input type="checkbox" name="source_attested" value="1" required><span>{{ __('I may contact every guest in this file about this event.') }}</span></label>
        </div>
        <div class="gl-dlg__foot"><button class="t-btn t-btn--ghost" type="button" data-gl-close>{{ __('Cancel') }}</button><button class="t-btn t-btn--primary" type="submit">{{ __('Import guests') }}</button></div>
      </form>
    </dialog>

    {{-- Export --}}
    <dialog class="gl-dlg" id="gl-export" aria-labelledby="gl-export-h">
      <form method="POST" action="{{ route('organizer.event.guest-list.export') }}" class="gl-dlg__form">
        @csrf
        <input type="hidden" name="event_id" value="{{ $event->id }}">
        <div class="gl-dlg__head"><div><h2 id="gl-export-h">{{ __('Export guest list') }}</h2><p>{{ __('We prepare the file privately; it appears in Data exports when ready.') }}</p></div><button class="gl-x" type="button" data-gl-close aria-label="{{ __('Close') }}"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg></button></div>
        <div class="gl-dlg__body">
          <div class="gl-two">
            <label class="gl-field"><span>{{ __('Who to include') }}</span>
              <select class="t-select" name="status">
                <option value="">{{ __('Everyone') }}</option>
                <option value="accepted">{{ __('Going') }}</option>
                <option value="pending">{{ __('Waiting') }}</option>
                <option value="declined">{{ __('Declined') }}</option>
                <option value="cancelled">{{ __('Cancelled') }}</option>
                <option value="expired">{{ __('Expired') }}</option>
              </select>
            </label>
            <label class="gl-field"><span>{{ __('Format') }}</span><select class="t-select" name="format" required><option value="csv">CSV</option><option value="xlsx">Excel (XLSX)</option></select></label>
          </div>
          <label class="gl-check"><input type="checkbox" name="privacy_confirmed" value="1" required><span>{{ __('I will use this file only to run this event.') }}</span></label>
          @error('export')<small class="gl-err">{{ $message }}</small>@enderror
        </div>
        <div class="gl-dlg__foot"><button class="t-btn t-btn--ghost" type="button" data-gl-close>{{ __('Cancel') }}</button><button class="t-btn t-btn--primary" type="submit">{{ __('Prepare export') }}</button></div>
      </form>
    </dialog>
  @endif

  <script>
    (function () {
      var root = document.querySelector('[data-gl]');
      if (!root) return;
      // Switching event opens its list straight away.
      var ev = document.querySelector('[data-gl-event] select');
      if (ev) ev.addEventListener('change', function () { root.classList.add('is-loading'); ev.form.submit(); });
      // Dialogs.
      function open(id) { var d = document.getElementById(id); if (d && !d.open) { d.showModal(); var f = d.querySelector('input:not([type=hidden]):not([type=checkbox]):not([type=file])'); if (f) setTimeout(function () { f.focus(); }, 40); } }
      document.addEventListener('click', function (e) {
        var o = e.target.closest('[data-gl-open]'); if (o) { open(o.getAttribute('data-gl-open')); return; }
        var c = e.target.closest('[data-gl-close]'); if (c) { c.closest('dialog').close(); return; }
        if (e.target.matches('dialog.gl-dlg')) e.target.close();
      });
      if (root.dataset.glAutoopen) open(root.dataset.glAutoopen);
      // File picker shows the chosen file; dropping a file works too.
      document.querySelectorAll('[data-gl-drop]').forEach(function (drop) {
        var input = drop.querySelector('input'), label = drop.querySelector('[data-gl-file]');
        function show() { if (input.files.length) { label.textContent = input.files[0].name; drop.classList.add('has-file'); } }
        input.addEventListener('change', show);
        ['dragenter', 'dragover'].forEach(function (t) { drop.addEventListener(t, function (e) { e.preventDefault(); drop.classList.add('is-over'); }); });
        ['dragleave', 'drop'].forEach(function (t) { drop.addEventListener(t, function () { drop.classList.remove('is-over'); }); });
        drop.addEventListener('drop', function (e) { e.preventDefault(); if (e.dataTransfer.files.length) { input.files = e.dataTransfer.files; show(); } });
      });
      // Cancel asks once more; every save shows it is working and can't be sent twice.
      document.addEventListener('submit', function (e) {
        var form = e.target, ask = form.getAttribute('data-gl-confirm'), btn = e.submitter || form.querySelector('[type=submit]');
        if (ask && !form.dataset.sure) {
          e.preventDefault(); form.dataset.sure = '1'; btn.dataset.label = btn.textContent; btn.textContent = ask; btn.classList.add('is-sure');
          setTimeout(function () { if (btn.isConnected) { delete form.dataset.sure; btn.textContent = btn.dataset.label; btn.classList.remove('is-sure'); } }, 4000);
          return;
        }
        if (btn && form.closest('[data-gl], dialog.gl-dlg')) setTimeout(function () { btn.disabled = true; btn.setAttribute('aria-busy', 'true'); }, 0);
      });
      window.addEventListener('pageshow', function (e) { if (e.persisted) document.querySelectorAll('[aria-busy=true]').forEach(function (b) { b.disabled = false; b.removeAttribute('aria-busy'); }); });
    })();
  </script>
@endsection
