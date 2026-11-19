@extends('backend.layout')

@section('content')
  <div class="page-header"><h4 class="page-title">{{ __('Duplicate accounts') }}</h4><ul class="breadcrumbs"><li class="nav-home"><a href="{{ route('admin.dashboard') }}"><i class="flaticon-home"></i></a></li><li class="separator"><i class="flaticon-right-arrow"></i></li><li class="nav-item"><a href="{{ route('admin.organizer_management.registered_organizer') }}">{{ __('Organizers') }}</a></li><li class="separator"><i class="flaticon-right-arrow"></i></li><li class="nav-item"><a href="#">{{ __('Duplicate accounts') }}</a></li></ul></div>

  <div class="card">
    <div class="card-header">
      <div class="card-title">{{ __('Emails with both a customer and an organizer account') }}</div>
      <small class="text-muted">{{ __('These were created before TICA allowed only one account type per email. Nothing changes until you choose. Every choice is recorded in the audit log.') }}</small>
    </div>
    <div class="card-body">
      @forelse ($rows as $row)
        @php($c = $row['customer'])
        @php($o = $row['organizer'])
        <section class="border rounded p-3 mb-3" aria-labelledby="dup-{{ $c['id'] }}-{{ $o['id'] }}">
          <h3 class="h6 mb-3" id="dup-{{ $c['id'] }}-{{ $o['id'] }}">{{ $row['email'] }}</h3>
          <div class="row">
            <div class="col-md-6 mb-3">
              <div class="bg-light rounded p-3 h-100">
                <strong>{{ __('Customer') }} #{{ $c['id'] }}</strong> @unless ($c['active'])<span class="badge badge-secondary">{{ __('Inactive') }}</span>@endunless
                <dl class="row small mb-0 mt-2">
                  <dt class="col-6">{{ __('Created') }}</dt><dd class="col-6">{{ $c['created_at'] ? \Illuminate\Support\Carbon::parse($c['created_at'])->format('j M Y') : '—' }}</dd>
                  <dt class="col-6">{{ __('Logs in with') }}</dt><dd class="col-6">{{ $c['login'] }}</dd>
                  <dt class="col-6">{{ __('Orders') }}</dt><dd class="col-6">{{ number_format($c['orders']) }}</dd>
                  <dt class="col-6">{{ __('Tickets') }}</dt><dd class="col-6">{{ number_format($c['tickets']) }}</dd>
                </dl>
              </div>
            </div>
            <div class="col-md-6 mb-3">
              <div class="bg-light rounded p-3 h-100">
                <strong>{{ __('Organizer') }} #{{ $o['id'] }}</strong> @unless ($o['active'])<span class="badge badge-secondary">{{ __('Inactive') }}</span>@endunless
                <dl class="row small mb-0 mt-2">
                  <dt class="col-6">{{ __('Created') }}</dt><dd class="col-6">{{ $o['created_at'] ? \Illuminate\Support\Carbon::parse($o['created_at'])->format('j M Y') : '—' }}</dd>
                  <dt class="col-6">{{ __('Logs in with') }}</dt><dd class="col-6">{{ $o['login'] }}</dd>
                  <dt class="col-6">{{ __('Events') }}</dt><dd class="col-6">{{ number_format($o['events']) }}</dd>
                  <dt class="col-6">{{ __('Balance') }}</dt><dd class="col-6">{{ number_format($o['balance'], 2) }}</dd>
                  <dt class="col-6">{{ __('Payouts') }}</dt><dd class="col-6">{{ number_format($o['payouts']) }}</dd>
                </dl>
              </div>
            </div>
          </div>
          <form method="post" action="{{ route('admin.duplicate_accounts.resolve') }}" class="d-flex flex-wrap align-items-end" style="gap:12px">
            @csrf
            <input type="hidden" name="customer_id" value="{{ $c['id'] }}">
            <input type="hidden" name="organizer_id" value="{{ $o['id'] }}">
            <div>
              <label class="small mb-1" for="decision-{{ $c['id'] }}-{{ $o['id'] }}">{{ __('What should happen?') }}</label>
              <select class="form-control form-control-sm" id="decision-{{ $c['id'] }}-{{ $o['id'] }}" name="decision" required>
                <option value="keep_organizer">{{ __('Keep as organizer (move customer history to it)') }}</option>
                <option value="keep_customer" @disabled(!$o['can_retire'])>{{ __('Keep as customer') }}{{ $o['can_retire'] ? '' : ' — ' . __('not allowed: organizer has events, balance or payouts') }}</option>
                <option value="leave">{{ __('Leave as is') }}</option>
              </select>
            </div>
            <div class="form-check mb-1">
              <input class="form-check-input" type="checkbox" name="confirm" value="1" id="confirm-{{ $c['id'] }}-{{ $o['id'] }}" required>
              <label class="form-check-label small" for="confirm-{{ $c['id'] }}-{{ $o['id'] }}">{{ __('I have checked both accounts') }}</label>
            </div>
            <button class="btn btn-sm btn-primary" type="submit">{{ __('Apply') }}</button>
          </form>
        </section>
      @empty
        <p class="text-center mb-0">{{ __('No duplicate accounts. Every email belongs to one account type.') }}</p>
      @endforelse
    </div>
  </div>
@endsection
