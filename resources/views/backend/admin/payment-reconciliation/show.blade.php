@extends('backend.layout')

@section('content')
  <div class="page-header">
    <h4 class="page-title">{{ __('Reconciliation details') }}</h4>
    <ul class="breadcrumbs">
      <li class="nav-home"><a href="{{ route('admin.dashboard') }}"><i class="flaticon-home"></i></a></li>
      <li class="separator"><i class="flaticon-right-arrow"></i></li>
      <li class="nav-item"><a href="{{ route('admin.payment_reconciliation.index') }}">{{ __('Payment Reconciliation') }}</a></li>
    </ul>
  </div>

  <div class="row mb-3">
    @foreach ([__('Gateway transactions') => $run->gateway_count, __('Local intents') => $run->local_count, __('Matched') => $run->matched_count, __('Mismatched') => $run->mismatch_count] as $label => $value)
      <div class="col-6 col-lg-3 mb-3"><div class="card"><div class="card-body"><small class="text-muted">{{ $label }}</small><h3 class="mb-0">{{ $value }}</h3></div></div></div>
    @endforeach
  </div>

  <div class="card">
    <div class="card-header">
      <div class="row align-items-center">
        <div class="col-lg-5"><div class="card-title">{{ $run->reconciliation_date->format('Y-m-d') }} · {{ $run->public_id }}</div></div>
        <div class="col-lg-7"><form method="get" class="form-inline justify-content-lg-end">
          <select name="result" class="form-control mr-2 mb-2">
            <option value="">{{ __('All results') }}</option>
            @foreach (['matched', 'gateway_only', 'paid_without_booking', 'local_status_not_completed', 'amount_mismatch', 'currency_mismatch', 'local_only'] as $result)
              <option value="{{ $result }}" @selected(request('result') === $result)>{{ __(ucwords(str_replace('_', ' ', $result))) }}</option>
            @endforeach
          </select>
          <button class="btn btn-primary mb-2">{{ __('Filter') }}</button>
        </form></div>
      </div>
    </div>
    <div class="card-body">
      <form method="post" action="{{ route('admin.payment_reconciliation.entries.export',$run) }}" class="form-inline justify-content-end mb-3">
        @csrf<input type="hidden" name="result" value="{{ request('result') }}"><label for="run-entry-export-format" class="mr-2">{{ __('Export current detail rows') }}</label><select id="run-entry-export-format" name="format" class="form-control mr-2" required><option value="csv">CSV</option><option value="xlsx">XLSX</option></select><button class="btn btn-primary" type="submit">{{ __('Queue private export') }}</button>
      </form>
      <div class="table-responsive"><table class="table table-striped">
        <thead><tr><th>{{ __('Reference') }}</th><th>{{ __('Result') }}</th><th>{{ __('Expected') }}</th><th>{{ __('Gateway') }}</th><th>{{ __('Local status') }}</th><th>{{ __('Gateway status') }}</th><th>{{ __('Issues') }}</th></tr></thead>
        <tbody>
          @forelse ($entries as $entry)
            <tr>
              <td><code>{{ $entry->reference }}</code></td>
              <td><span class="badge badge-{{ $entry->result === 'matched' ? 'success' : 'danger' }}">{{ __(ucwords(str_replace('_', ' ', $entry->result))) }}</span></td>
              <td>{{ $entry->expected_currency ?: '—' }} {{ $entry->expected_amount ?? '—' }}</td>
              <td>{{ $entry->gateway_currency ?: '—' }} {{ $entry->gateway_amount ?? '—' }}</td>
              <td>{{ $entry->local_status ?: '—' }}</td><td>{{ $entry->gateway_status ?: '—' }}</td>
              <td>{{ implode(', ', array_map(fn ($issue) => str_replace('_', ' ', $issue), $entry->details['issues'] ?? [])) ?: '—' }}</td>
            </tr>
          @empty
            <tr><td colspan="7" class="text-center">{{ __('No reconciliation entries found.') }}</td></tr>
          @endforelse
        </tbody>
      </table></div>
      {{ $entries->links() }}
    </div>
  </div>
@endsection
