{{-- Scanner panels, shared by the organiser dashboard page and the gate staff workspace. --}}
<div class="row">
  <div class="col-12 mt-4 mb-4">
    <div id="scanner-device" class="card mx-auto scanner-device-card scanner-panel">
      <div class="card-body">
        <div class="d-sm-flex justify-content-between align-items-start">
          <div>
            <p class="scanner-eyebrow mb-1">{{ __('Gate security') }}</p>
            <h1 class="h4 mb-1">{{ __('Scanner device') }}</h1>
            <p id="scanner-device-status" class="text-muted mb-3" role="status" aria-live="polite">{{ __('Checking enrollment…') }}</p>
          </div>
          <span id="scanner-device-badge" class="badge badge-secondary px-3 py-2">{{ __('Not enrolled') }}</span>
        </div>
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
  @if(Auth::guard('organizer')->check())
  <div class="col-12 mb-4">
    <div id="scanner-staff" class="card mx-auto scanner-panel">
      <div class="card-body">
        <p class="scanner-eyebrow mb-1">{{ __('Access control') }}</p>
        <h2 class="h5">{{ __('Gate staff accounts') }}</h2>
        <p class="text-muted">{{ __('Create individual check-in accounts instead of sharing your organizer password.') }}</p>
        <form id="scanner-staff-form" class="form-row">
          <div class="col-md-6 mb-2"><label for="staff-name">{{ __('Name') }}</label><input id="staff-name" name="name" class="form-control" required minlength="2" maxlength="100"></div>
          <div class="col-md-6 mb-2"><label for="staff-email">{{ __('Email') }}</label><input id="staff-email" name="email" type="email" class="form-control" required></div>
          <div class="col-md-4 mb-2"><label for="staff-role">{{ __('Role') }}</label><select id="staff-role" name="role" class="form-control"><option value="check_in">{{ __('Check-in staff') }}</option><option value="supervisor">{{ __('Gate supervisor') }}</option></select></div>
          <div class="col-md-4 mb-2"><label for="staff-password">{{ __('Temporary password') }}</label><input id="staff-password" name="password" type="password" class="form-control" required minlength="8"></div>
          <div class="col-md-4 mb-2"><label for="staff-password-confirmation">{{ __('Confirm password') }}</label><input id="staff-password-confirmation" name="password_confirmation" type="password" class="form-control" required minlength="8"></div>
          <div class="col-12"><button type="submit" class="btn btn-primary">{{ __('Create gate account') }}</button></div>
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
        <div class="d-sm-flex justify-content-between align-items-start">
          <div>
            <p class="scanner-eyebrow mb-1">{{ __('Resilient check-in') }}</p>
            <h2 class="h5 mb-1">{{ __('Offline event access') }}</h2>
            <p class="text-muted">{{ __('Prepare a short-lived event manifest before doors open. Offline scans are provisional until this device reconnects and the server confirms them.') }}</p>
          </div>
          <span id="offline-network-badge" class="badge badge-success px-3 py-2">{{ __('Online') }}</span>
        </div>
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
        <div class="d-flex justify-content-between align-items-center mt-3 pt-3 border-top">
          <div><strong id="offline-pending-count">0</strong> {{ __('provisional scan(s) waiting') }}</div>
          <button id="offline-sync" class="btn btn-primary btn-sm" type="button" disabled>{{ __('Synchronize now') }}</button>
        </div>
        <div id="offline-sync-status" class="small mt-2" role="status" aria-live="polite"></div>
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
    <div class="card mx-auto" style="max-width: 760px;">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <p class="scanner-eyebrow mb-1">{{ __('Gate operations') }}</p>
            <h2 class="h5 mb-1">{{ __('Multi-gate conflicts') }} <span id="scanner-conflict-count" class="badge badge-danger d-none">0</span></h2>
            <p class="text-muted">{{ __('Review tickets presented at a second gate after their first admission.') }}</p>
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
    <div class="card mx-auto" style="max-width: 760px;">
      <div class="card-body">
        <p class="scanner-eyebrow mb-1">{{ __('Operational evidence') }}</p>
        <h2 class="h5 mb-1">{{ __('Private check-in ledger') }}</h2>
        <p class="text-muted">{{ __('Export accepted scans and duplicate attempts with gate, device and staff attribution. Customer contact details are excluded.') }}</p>
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
