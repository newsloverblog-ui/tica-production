@extends('organizer.layout')

@section('content')
  <div class="tica-workspace-hero">
    <div><p class="tica-eyebrow">{{ __('Invitations') }}</p><h1>{{ __('Guest list & RSVPs') }}</h1><p class="tica-lede">{{ __('Invite guests, track responses and prepare a clean check-in list without spreadsheets.') }}</p></div>
    <div class="tica-quick-actions"><a class="btn btn-outline-primary" href="{{ route('organizer.event_management.event') }}">{{ __('All events') }}</a></div>
  </div>

  <div class="row">
    <div class="col-lg-12">
      <div class="card mb-4">
        <div class="card-header"><div class="card-title">{{ __('Choose an event') }}</div></div>
        <div class="card-body">
          <form method="GET" action="{{ route('organizer.event.guest-list.index') }}" class="form-inline">
            <select name="event_id" class="form-control mr-2" required>
              @forelse ($events as $eventOption)
                <option value="{{ $eventOption->id }}" @selected($event && $event->id === $eventOption->id)>
                  {{ $eventOption->information?->title ?: 'Event #' . $eventOption->id }}
                </option>
              @empty
                <option value="">{{ __('No events available') }}</option>
              @endforelse
            </select>
            <button class="btn btn-primary" type="submit">{{ __('Open guest list') }}</button>
          </form>
        </div>
      </div>
    </div>
  </div>

  @if ($event)
    <div class="row">
      <div class="col-lg-4">
        <div class="card">
          <div class="card-header"><div class="card-title">{{ __('Invite a guest') }}</div></div>
          <div class="card-body">
            <p class="text-muted">{{ __('Invitations are private, expire automatically, and require the guest to accept before check-in.') }}</p>
            <form method="POST" action="{{ route('organizer.event.guest-list.store') }}">
              @csrf
              <input type="hidden" name="event_id" value="{{ $event->id }}">
              <div class="form-group">
                <label for="guest-name">{{ __('Guest name') }} *</label>
                <input id="guest-name" class="form-control" name="name" value="{{ old('name') }}" maxlength="150" required>
                @error('name')<small class="text-danger">{{ $message }}</small>@enderror
              </div>
              <div class="form-group">
                <label for="guest-email">{{ __('Email address') }} *</label>
                <input id="guest-email" class="form-control" type="email" name="email" value="{{ old('email') }}" maxlength="191" required>
                @error('email')<small class="text-danger">{{ $message }}</small>@enderror
              </div>
              <div class="form-group">
                <label for="party-size">{{ __('Party size') }} *</label>
                <input id="party-size" class="form-control" type="number" name="party_size" value="{{ old('party_size', 1) }}" min="1" max="20" required>
                @error('party_size')<small class="text-danger">{{ $message }}</small>@enderror
              </div>
              <div class="form-check mb-3">
                <input id="source-attested" class="form-check-input" type="checkbox" name="source_attested" value="1" required @checked(old('source_attested'))>
                <label class="form-check-label" for="source-attested">
                  {{ __('I confirm I have a legitimate relationship with this guest and permission to contact them about this specific event.') }}
                </label>
                @error('source_attested')<small class="text-danger d-block">{{ $message }}</small>@enderror
              </div>
              <button class="btn btn-primary btn-block" type="submit"><i class="fas fa-paper-plane mr-1"></i>{{ __('Send RSVP invitation') }}</button>
            </form>
          </div>
        </div>
      </div>

      <div class="col-lg-8">
        <div class="row mb-4">
          <div class="col-md-6 mb-3 mb-md-0">
            <div class="card h-100">
              <div class="card-header"><div class="card-title">{{ __('Bulk guest import') }}</div></div>
              <div class="card-body">
                <p class="text-muted">{{ __('Use the current CSV template. Every row is validated independently and successful source files are deleted after processing.') }}</p>
                <a class="btn btn-outline-secondary btn-sm mb-3" href="{{ route('organizer.event.guest-list.import-template') }}">
                  <i class="fas fa-download mr-1"></i>{{ __('Download CSV template') }}
                </a>
                <form method="POST" enctype="multipart/form-data" action="{{ route('organizer.event.guest-list.import') }}">
                  @csrf
                  <input type="hidden" name="event_id" value="{{ $event->id }}">
                  <div class="form-group">
                    <label for="guest-file">{{ __('Completed CSV') }} *</label>
                    <input id="guest-file" class="form-control-file" type="file" name="guest_file" accept=".csv,text/csv" required>
                    @error('guest_file')<small class="text-danger d-block">{{ $message }}</small>@enderror
                  </div>
                  <div class="form-check mb-3">
                    <input id="bulk-source-attested" class="form-check-input" type="checkbox" name="source_attested" value="1" required>
                    <label class="form-check-label" for="bulk-source-attested">{{ __('I am permitted to contact every guest in this file about this event.') }}</label>
                  </div>
                  <button class="btn btn-primary" type="submit">{{ __('Queue private import') }}</button>
                </form>
              </div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="card h-100">
              <div class="card-header"><div class="card-title">{{ __('Private guest-list export') }}</div></div>
              <div class="card-body">
                <p class="text-muted">{{ __('Exports are generated privately, expire automatically and exclude anonymized contacts.') }}</p>
                <form method="POST" action="{{ route('organizer.event.guest-list.export') }}">
                  @csrf
                  <input type="hidden" name="event_id" value="{{ $event->id }}">
                  <div class="form-row">
                    <div class="form-group col-7">
                      <label for="guest-export-status">{{ __('RSVP status') }}</label>
                      <select id="guest-export-status" class="form-control" name="status">
                        <option value="">{{ __('All statuses') }}</option>
                        @foreach (['pending', 'accepted', 'declined', 'cancelled', 'expired'] as $status)
                          <option value="{{ $status }}">{{ ucfirst($status) }}</option>
                        @endforeach
                      </select>
                    </div>
                    <div class="form-group col-5">
                      <label for="guest-export-format">{{ __('Format') }}</label>
                      <select id="guest-export-format" class="form-control" name="format" required>
                        <option value="csv">CSV</option><option value="xlsx">XLSX</option>
                      </select>
                    </div>
                  </div>
                  <div class="form-check mb-3">
                    <input id="guest-export-privacy" class="form-check-input" type="checkbox" name="privacy_confirmed" value="1" required>
                    <label class="form-check-label" for="guest-export-privacy">{{ __('I will use this file only for RSVP and operations for this event.') }}</label>
                  </div>
                  @error('export')<small class="text-danger d-block mb-2">{{ $message }}</small>@enderror
                  <button class="btn btn-outline-primary" type="submit">{{ __('Queue private export') }}</button>
                </form>
              </div>
            </div>
          </div>
        </div>

        @if ($imports->isNotEmpty())
          <div class="card mb-4">
            <div class="card-header"><div class="card-title">{{ __('Recent import results') }}</div></div>
            <div class="card-body table-responsive">
              <table class="table table-sm">
                <thead><tr><th>{{ __('Queued') }}</th><th>{{ __('Status') }}</th><th>{{ __('Rows') }}</th><th>{{ __('Rejected row details') }}</th></tr></thead>
                <tbody>
                  @foreach ($imports as $import)
                    <tr>
                      <td>{{ $import->created_at->format('d M Y, H:i') }}</td>
                      <td><span class="badge badge-{{ $import->status === 'completed' ? 'success' : ($import->status === 'failed' ? 'danger' : 'warning') }}">{{ str_replace('_', ' ', ucfirst($import->status)) }}</span></td>
                      <td>{{ $import->accepted_rows }} {{ __('accepted') }} / {{ $import->rejected_rows }} {{ __('rejected') }}</td>
                      <td>
                        @forelse ($import->rows->take(20) as $row)
                          <div><strong>#{{ $row->row_number }}</strong> — {{ $row->error_message }}</div>
                        @empty
                          <span class="text-muted">{{ __('None') }}</span>
                        @endforelse
                        @if ($import->rows->count() > 20)<small class="text-muted">{{ __('Additional rejected rows omitted from this summary.') }}</small>@endif
                      </td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
          </div>
        @endif

        <div class="card">
          <div class="card-header d-flex justify-content-between align-items-center">
            <div class="card-title mb-0">{{ $event->information?->title ?: 'Event #' . $event->id }}</div>
            @if ($invitations)
              <span class="badge badge-info">{{ $invitations->total() }} {{ __('invited') }}</span>
            @endif
          </div>
          <div class="card-body">
            <div class="alert alert-light border">
              <strong>{{ __('Privacy note:') }}</strong>
              {{ __('Guest contact details are encrypted and are for RSVP fulfilment only. Optional marketing consent is collected separately from attendance consent.') }}
            </div>
            <div class="table-responsive">
              <table class="table table-striped">
                <thead><tr><th>{{ __('Guest') }}</th><th>{{ __('Party') }}</th><th>{{ __('RSVP') }}</th><th>{{ __('Delivery') }}</th><th>{{ __('Consent') }}</th><th>{{ __('Actions') }}</th></tr></thead>
                <tbody>
                  @forelse ($invitations as $invitation)
                    <tr>
                      <td><strong>{{ $invitation->name }}</strong><br><small>{{ $invitation->email }}</small></td>
                      <td>{{ $invitation->party_size }}</td>
                      <td>
                        <span class="badge badge-{{ $invitation->status === 'accepted' ? 'success' : ($invitation->status === 'declined' || $invitation->status === 'cancelled' ? 'danger' : 'warning') }}">{{ ucfirst($invitation->status) }}</span>
                        @if ($invitation->checked_in_at)<br><small class="text-success">{{ __('Checked in') }} {{ $invitation->checked_in_at->format('d M, H:i') }}</small>@endif
                      </td>
                      <td><span class="badge badge-secondary">{{ ucfirst($invitation->delivery_status) }}</span></td>
                      <td>
                        <small>{{ $invitation->rsvp_consent_at ? __('RSVP recorded') : __('Not recorded') }}</small>
                        @if ($invitation->marketing_consent_at)<br><small class="text-success">{{ __('Marketing opt-in') }}</small>@endif
                      </td>
                      <td class="text-nowrap">
                        @if ($invitation->status === 'accepted' && !$invitation->checked_in_at)
                          <form class="d-inline" method="POST" action="{{ route('organizer.event.guest-list.check-in', $invitation) }}">
                            @csrf<button class="btn btn-success btn-sm" type="submit">{{ __('Check in') }}</button>
                          </form>
                        @endif
                        @if (!$invitation->checked_in_at && $invitation->status !== 'cancelled')
                          <form class="d-inline" method="POST" action="{{ route('organizer.event.guest-list.cancel', $invitation) }}">
                            @csrf<button class="btn btn-outline-danger btn-sm" type="submit">{{ __('Cancel') }}</button>
                          </form>
                        @endif
                      </td>
                    </tr>
                  @empty
                    <tr><td colspan="6" class="text-center py-5">{{ __('No guest invitations yet.') }}</td></tr>
                  @endforelse
                </tbody>
              </table>
            </div>
            @if ($invitations){{ $invitations->links() }}@endif
          </div>
        </div>
      </div>
    </div>
  @endif
@endsection
