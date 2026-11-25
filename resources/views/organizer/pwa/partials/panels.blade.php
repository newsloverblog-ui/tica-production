{{-- Scanner panels, shared by the organiser dashboard page and the gate staff workspace. --}}
<div class="row">
  <div class="col-12 mt-4 mb-4">
    <div id="scanner-device" class="card mx-auto scanner-device-card scanner-panel">
      <div class="card-body">
        <div class="scanner-panel-head">
          <span class="scanner-panel-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="6" y="2.5" width="12" height="19" rx="2.5"/><path d="M10 5h4M11 18.5h2"/></svg></span>
          <div class="scanner-panel-copy">
            <h1 class="h4 mb-1">{{ __('This device') }}</h1>
            <p class="text-muted mb-0">{{ __('Approve this browser for secure ticket check-in.') }}</p>
          </div>
          <span id="scanner-device-badge" class="badge badge-secondary px-3 py-2">{{ __('Not enrolled') }}</span>
        </div>
        <p id="scanner-device-status" class="scanner-status-line" role="status" aria-live="polite">{{ __('Checking enrollment…') }}</p>
        <form id="scanner-enroll-form" class="form-row d-none">
          <div class="col-sm-9 mb-2">
            <label for="scanner-device-name">{{ __('Name this gate or device') }}</label>
            <input id="scanner-device-name" class="form-control" name="name" minlength="2" maxlength="100" required
              placeholder="{{ __('Example: Main entrance iPad') }}">
          </div>
          <div class="col-sm-3 mb-2 d-flex align-items-end">
            <button class="btn btn-primary btn-block" type="submit">{{ __('Enroll') }}</button>
          </div>
        </form>
        <button id="scanner-manage-toggle" type="button" class="btn btn-link px-0 d-none">{{ __('Manage enrolled scanners') }}</button>
        <div id="scanner-device-list" class="d-none mt-2"></div>
      </div>
    </div>
  </div>
  @if(Auth::guard('organizer')->check() && \App\Support\TeamPermissions::isOwner())
  <div class="col-12 mb-4">
    <div id="scanner-staff" class="card mx-auto scanner-panel">
      <div class="card-body">
        <div class="scanner-panel-head">
          <span class="scanner-panel-icon scanner-panel-icon--orange" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0M16 8.5h5M18.5 6v5"/></svg></span>
          <div class="scanner-panel-copy">
            <h2 class="h5 mb-1">{{ __('Gate staff access') }}</h2>
            <p class="text-muted mb-0">{{ __('Give each person their own login. Never share your organizer password.') }}</p>
          </div>
        </div>
        <form id="scanner-staff-form" class="form-row">
          <div class="col-md-6 mb-2"><label for="staff-name">{{ __('Name') }}</label><input id="staff-name" name="name" class="form-control" required minlength="2" maxlength="100"></div>
          <div class="col-md-6 mb-2"><label for="staff-email">{{ __('Email') }}</label><input id="staff-email" name="email" type="email" class="form-control" required></div>
          <div class="col-md-4 mb-2"><label for="staff-role">{{ __('Role') }}</label><select id="staff-role" name="role" class="form-control"><option value="check_in">{{ __('Check-in staff') }}</option><option value="supervisor">{{ __('Gate supervisor') }}</option></select></div>
          <div class="col-md-4 mb-2"><label for="staff-password">{{ __('Temporary password') }}</label><input id="staff-password" name="password" type="password" class="form-control" required minlength="8"></div>
          <div class="col-md-4 mb-2"><label for="staff-password-confirmation">{{ __('Confirm password') }}</label><input id="staff-password-confirmation" name="password_confirmation" type="password" class="form-control" required minlength="8"></div>
          <div class="col-12 scanner-form-action"><button type="submit" class="btn btn-primary">{{ __('Create gate account') }}</button></div>
        </form>
        <div id="scanner-staff-status" class="mt-3" role="status" aria-live="polite"></div>
        <div id="scanner-staff-list" class="mt-2"></div>
      </div>
    </div>
  </div>
  @endif
  <div id="scanner-workspace" class="col-12 d-none">
    <div class="row">
  <div id="scanner-camera" class="col-12 mb-5 scanner-camera-wrap">
    <div id="reader" class="mx-auto"></div>
  </div>
  <div class="col-lg-12">
    <div class="alert" id="alert">

    </div>
  </div>
  <div class="col-12 mt-4 mb-5">
    <div id="scanner-offline" class="card mx-auto scanner-panel">
      <div class="card-body">
        <div class="scanner-panel-head">
          <span class="scanner-panel-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 15.5a5 5 0 0 1 1.3-9.8A7 7 0 0 1 18.8 8 4 4 0 0 1 19 16H7"/><path d="M8 19h8M12 15v6"/></svg></span>
          <div class="scanner-panel-copy">
            <h2 class="h5 mb-1">{{ config('tickets.offline_enabled') ? __('Offline readiness') : __('Live attendance') }}</h2>
            @if (config('tickets.offline_enabled'))
              <p class="text-muted mb-0">{{ __('Offline scans are provisional. Wait for online confirmation if this event requires a single admission across all gates.') }}</p>
            @endif
          </div>
          <span id="offline-network-badge" class="badge badge-success px-3 py-2">{{ __('Online') }}</span>
        </div>
        @if (config('tickets.offline_enabled'))
        <div class="form-row align-items-end">
          <div class="col-sm-8 mb-2">
            <label for="offline-event-select">{{ __('Event') }}</label>
            <select id="offline-event-select" class="form-control"><option value="">{{ __('Loading events…') }}</option></select>
          </div>
          <div class="col-sm-4 mb-2">
            <button id="offline-prepare" class="btn btn-outline-primary btn-block" type="button">{{ __('Prepare offline') }}</button>
          </div>
        </div>
        <div id="offline-manifest-status" class="small text-muted mt-2" role="status" aria-live="polite">{{ __('No offline event prepared on this browser.') }}</div>
        @endif
        <div id="scanner-live-metrics" class="scanner-live-metrics d-none mt-4 pt-3 border-top" aria-live="polite">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <div><p class="scanner-eyebrow mb-1">{{ __('Live attendance') }}</p><h3 id="scanner-metrics-event" class="h6 mb-0"></h3></div>
            <button id="scanner-metrics-refresh" type="button" class="btn btn-sm btn-outline-secondary">{{ __('Refresh') }}</button>
          </div>
          <div class="scanner-metric-grid">
            <div class="scanner-metric"><span id="scanner-metrics-scanned">0</span><small>{{ __('Scanned') }}</small></div>
            <div class="scanner-metric"><span id="scanner-metrics-remaining">0</span><small>{{ __('Remaining') }}</small></div>
            <div class="scanner-metric"><span id="scanner-metrics-total">0</span><small>{{ __('Issued') }}</small></div>
          </div>
          <div class="mt-3"><strong class="small">{{ __('Admissions by gate') }}</strong><div id="scanner-metrics-gates" class="mt-1"></div></div>
          <small id="scanner-metrics-updated" class="text-muted d-block mt-2"></small>
        </div>
        @if (config('tickets.offline_enabled'))
        <div class="scanner-sync-row mt-3 pt-3 border-top">
          <div><strong id="offline-pending-count">0</strong> {{ __('provisional scan(s) waiting') }}</div>
          <button id="offline-sync" class="btn btn-primary btn-sm" type="button" disabled>{{ __('Synchronize now') }}</button>
        </div>
        <div id="offline-sync-status" class="small mt-2" role="status" aria-live="polite"></div>
        @endif
      </div>
    </div>
  </div>
  <div class="col-12 mt-4 mb-5">
    <div class="card mx-auto" style="max-width: 760px;">
      <div class="card-body">
        <h2 class="h5">{{ __('Cannot scan the QR?') }}</h2>
        <p class="text-muted">{{ __('Search your own events by attendee name, exact email, booking ID, or ticket ID. Manual entry uses the same duplicate and payment checks as QR scanning.') }}</p>
        <form id="ticket-lookup-form" class="form-row">
          <div class="col-sm-9 mb-2">
            <label class="sr-only" for="ticket-lookup-query">{{ __('Ticket search') }}</label>
            <input id="ticket-lookup-query" class="form-control" name="query" minlength="3" maxlength="100" required
              placeholder="{{ __('Name, email, booking ID, or ticket ID') }}">
          </div>
          <div class="col-sm-3 mb-2">
            <button class="btn btn-primary btn-block" type="submit">{{ __('Find ticket') }}</button>
          </div>
        </form>
        <div id="ticket-lookup-status" class="mt-2" role="status" aria-live="polite"></div>
        <div id="ticket-lookup-results" class="mt-3"></div>
      </div>
    </div>
  </div>
  <div id="scanner-conflicts-card" class="col-12 mb-5">
    <div class="card mx-auto scanner-panel scanner-support-panel">
      <div class="card-body">
        <div class="scanner-panel-head">
          <span class="scanner-panel-icon scanner-panel-icon--orange" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 3 2.8 19h18.4z"/><path d="M12 9v4M12 16h.01"/></svg></span>
          <div class="scanner-panel-copy">
            <h2 class="h5 mb-1">{{ __('Gate conflicts') }} <span id="scanner-conflict-count" class="badge badge-danger d-none">0</span></h2>
            <p class="text-muted mb-0">{{ __('Review tickets presented at another gate after their first admission.') }}</p>
          </div>
          <button id="scanner-conflicts-refresh" type="button" class="btn btn-sm btn-outline-secondary">{{ __('Refresh') }}</button>
        </div>
        <div id="scanner-conflict-status" role="status" aria-live="polite"></div>
        <div id="scanner-conflict-list"></div>
      </div>
    </div>
  </div>
  @if(Auth::guard('organizer')->check() || optional(Auth::guard('scanner')->user())->role === 'supervisor')
  <div class="col-12 mb-5">
    <div class="card mx-auto scanner-panel scanner-support-panel">
      <div class="card-body">
        <div class="scanner-panel-head">
          <span class="scanner-panel-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M6 3h9l3 3v15H6z"/><path d="M15 3v4h4M9 12h6M9 16h6"/></svg></span>
          <div class="scanner-panel-copy">
            <h2 class="h5 mb-1">{{ __('Private check-in ledger') }}</h2>
            <p class="text-muted mb-0">{{ __('Export accepted scans and duplicate attempts by gate, device, and staff member.') }}</p>
          </div>
        </div>
        <div class="form-row align-items-end">
          <div class="col-sm-8 mb-2"><label for="scanner-ledger-format">{{ __('Format') }}</label><select id="scanner-ledger-format" class="form-control"><option value="csv">CSV</option><option value="xlsx">XLSX</option></select></div>
          <div class="col-sm-4 mb-2"><button id="scanner-ledger-create" type="button" class="btn btn-primary btn-block">{{ __('Queue ledger') }}</button></div>
        </div>
        <small class="text-muted d-block">{{ __('The event selected under Offline event access is used for this export.') }}</small>
        <div id="scanner-ledger-status" class="mt-2" role="status" aria-live="polite"></div>
        <div id="scanner-ledger-list" class="mt-3"></div>
      </div>
    </div>
  </div>
  @endif
    </div>
  </div>
</div>
