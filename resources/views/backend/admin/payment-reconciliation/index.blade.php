@extends('backend.layout')

@section('content')
  <div class="page-header">
    <h4 class="page-title">{{ __('Payment Reconciliation') }}</h4>
    <ul class="breadcrumbs">
      <li class="nav-home"><a href="{{ route('admin.dashboard') }}"><i class="flaticon-home"></i></a></li>
      <li class="separator"><i class="flaticon-right-arrow"></i></li>
      <li class="nav-item"><a href="{{ route('admin.transcation') }}">{{ __('Transactions') }}</a></li>
      <li class="separator"><i class="flaticon-right-arrow"></i></li>
      <li class="nav-item"><a href="#">{{ __('Reconciliation') }}</a></li>
    </ul>
  </div>

  <div class="card">
    <div class="card-header">
      <div class="row align-items-center">
        <div class="col-lg-4">
          <div class="card-title d-inline-block">{{ __('Payment reconciliation runs') }}</div>
          <a href="{{ route('admin.settlement_reconciliation.index') }}" class="btn btn-sm btn-secondary ml-2">{{ __('Bank settlements') }}</a>
          <a href="{{ route('admin.reconciliation_exports.index') }}" class="btn btn-sm btn-outline-secondary ml-2">{{ __('Export history') }}</a>
        </div>
        <div class="col-lg-8">
          <form method="get" class="form-inline justify-content-lg-end">
            <select name="gateway" class="form-control mr-2 mb-2">
              <option value="">{{ __('All gateways') }}</option>
              @foreach (['paystack' => 'Paystack', 'flutterwave' => 'Flutterwave'] as $value => $label)
                <option value="{{ $value }}" @selected(request('gateway') === $value)>{{ __($label) }}</option>
              @endforeach
            </select>
            <input type="date" name="date" value="{{ request('date') }}" class="form-control mr-2 mb-2">
            <select name="status" class="form-control mr-2 mb-2">
              <option value="">{{ __('All statuses') }}</option>
              @foreach (['matched', 'mismatched', 'failed', 'running'] as $status)
                <option value="{{ $status }}" @selected(request('status') === $status)>{{ __(ucfirst($status)) }}</option>
              @endforeach
            </select>
            <button class="btn btn-primary mb-2" type="submit">{{ __('Filter') }}</button>
          </form>
        </div>
      </div>
    </div>
    <div class="card-body">
      <div class="alert alert-info">
        {{ __('Runs compare successful Paystack and Flutterwave charges with local payment intents, bookings and sale-ledger identities. Resolve every mismatch before finance close.') }}
      </div>
      <form method="post" action="{{ route('admin.payment_reconciliation.export') }}" class="form-inline justify-content-end mb-3">
        @csrf
        <input type="hidden" name="gateway" value="{{ request('gateway') }}">
        <input type="hidden" name="date" value="{{ request('date') }}">
        <input type="hidden" name="status" value="{{ request('status') }}">
        <label class="mr-2" for="payment-run-export-format">{{ __('Export current results') }}</label>
        <select id="payment-run-export-format" name="format" class="form-control mr-2" required>
          <option value="csv">CSV</option><option value="xlsx">XLSX</option>
        </select>
        <button class="btn btn-primary" type="submit">{{ __('Queue private export') }}</button>
      </form>
      <div class="table-responsive">
        <table class="table table-striped mt-3">
          <thead><tr>
            <th>{{ __('Date') }}</th><th>{{ __('Status') }}</th><th>{{ __('Gateway') }}</th>
            <th>{{ __('Gateway rows') }}</th><th>{{ __('Local rows') }}</th>
            <th>{{ __('Matched') }}</th><th>{{ __('Mismatched') }}</th><th>{{ __('Completed') }}</th><th></th>
          </tr></thead>
          <tbody>
            @forelse ($runs as $run)
              <tr>
                <td>{{ $run->reconciliation_date->format('Y-m-d') }}</td>
                <td><span class="badge badge-{{ $run->status === 'matched' ? 'success' : ($run->status === 'failed' ? 'danger' : 'warning') }}">{{ __(ucfirst($run->status)) }}</span></td>
                <td>{{ ucfirst($run->gateway) }}</td><td>{{ $run->gateway_count }}</td><td>{{ $run->local_count }}</td>
                <td>{{ $run->matched_count }}</td><td class="{{ $run->mismatch_count ? 'text-danger font-weight-bold' : '' }}">{{ $run->mismatch_count }}</td>
                <td>{{ optional($run->completed_at)->format('Y-m-d H:i:s') ?: '—' }}</td>
                <td><a class="btn btn-sm btn-secondary" href="{{ route('admin.payment_reconciliation.show', $run) }}">{{ __('Review') }}</a></td>
              </tr>
              @if ($run->last_error)
                <tr><td colspan="9" class="text-danger"><strong>{{ __('Error:') }}</strong> {{ $run->last_error }}</td></tr>
              @endif
            @empty
              <tr><td colspan="9" class="text-center">{{ __('No reconciliation runs found.') }}</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
      {{ $runs->links() }}
    </div>
  </div>
@endsection
