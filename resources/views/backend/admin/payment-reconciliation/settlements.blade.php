@extends('backend.layout')

@section('content')
  <div class="page-header">
    <h4 class="page-title">{{ __('Bank Settlement Reconciliation') }}</h4>
    <ul class="breadcrumbs">
      <li class="nav-home"><a href="{{ route('admin.dashboard') }}"><i class="flaticon-home"></i></a></li>
      <li class="separator"><i class="flaticon-right-arrow"></i></li>
      <li class="nav-item"><a href="{{ route('admin.payment_reconciliation.index') }}">{{ __('Payment Reconciliation') }}</a></li>
      <li class="separator"><i class="flaticon-right-arrow"></i></li>
      <li class="nav-item"><a href="#">{{ __('Settlements') }}</a></li>
    </ul>
  </div>

  <div class="card">
    <div class="card-header"><div class="row align-items-center">
      <div class="col-lg-4"><div class="card-title d-inline-block">{{ __('Paystack bank settlements') }}</div><a href="{{ route('admin.reconciliation_exports.index') }}" class="btn btn-sm btn-outline-secondary ml-2">{{ __('Export history') }}</a></div>
      <div class="col-lg-8"><form method="get" class="form-inline justify-content-lg-end">
        <input type="date" name="date" value="{{ request('date') }}" class="form-control mr-2 mb-2">
        <select name="status" class="form-control mr-2 mb-2">
          <option value="">{{ __('All results') }}</option>
          @foreach (['matched', 'mismatched', 'failed', 'pending'] as $status)
            <option value="{{ $status }}" @selected(request('status') === $status)>{{ __(ucfirst($status)) }}</option>
          @endforeach
        </select>
        <button class="btn btn-primary mb-2">{{ __('Filter') }}</button>
      </form></div>
    </div></div>
    <div class="card-body">
      <div class="alert alert-info">{{ __('Settlement totals are stored in major currency units. Review mismatches before confirming a bank payout in finance records.') }}</div>
      <form method="post" action="{{ route('admin.settlement_reconciliation.export') }}" class="form-inline justify-content-end mb-3">
        @csrf
        <input type="hidden" name="date" value="{{ request('date') }}">
        <input type="hidden" name="status" value="{{ request('status') }}">
        <label class="mr-2" for="settlement-export-format">{{ __('Export current results') }}</label>
        <select id="settlement-export-format" name="format" class="form-control mr-2" required>
          <option value="csv">CSV</option><option value="xlsx">XLSX</option>
        </select>
        <button class="btn btn-primary" type="submit">{{ __('Queue private export') }}</button>
      </form>
      <div class="table-responsive"><table class="table table-striped">
        <thead><tr><th>{{ __('Settlement') }}</th><th>{{ __('Date') }}</th><th>{{ __('Gateway status') }}</th><th>{{ __('Result') }}</th><th>{{ __('Processed') }}</th><th>{{ __('Fees') }}</th><th>{{ __('Effective') }}</th><th>{{ __('Transactions') }}</th><th>{{ __('Mismatches') }}</th><th></th></tr></thead>
        <tbody>
          @forelse ($settlements as $settlement)
            <tr>
              <td><code>{{ $settlement->gateway_settlement_id }}</code></td>
              <td>{{ optional($settlement->settlement_date)->format('Y-m-d') ?: '—' }}</td>
              <td>{{ ucfirst($settlement->status) }}</td>
              <td><span class="badge badge-{{ $settlement->reconciliation_status === 'matched' ? 'success' : ($settlement->reconciliation_status === 'failed' ? 'danger' : 'warning') }}">{{ __(ucfirst($settlement->reconciliation_status)) }}</span></td>
              <td>{{ $settlement->currency }} {{ number_format((float) $settlement->total_processed, 2) }}</td>
              <td>{{ $settlement->currency }} {{ number_format((float) $settlement->total_fees, 2) }}</td>
              <td>{{ $settlement->currency }} {{ number_format((float) $settlement->effective_amount, 2) }}</td>
              <td>{{ $settlement->transaction_count }}</td>
              <td class="{{ $settlement->mismatch_count ? 'text-danger font-weight-bold' : '' }}">{{ $settlement->mismatch_count }}</td>
              <td><a class="btn btn-sm btn-secondary" href="{{ route('admin.settlement_reconciliation.show', $settlement) }}">{{ __('Review') }}</a></td>
            </tr>
            @if ($settlement->last_error)<tr><td colspan="10" class="text-danger">{{ $settlement->last_error }}</td></tr>@endif
          @empty
            <tr><td colspan="10" class="text-center">{{ __('No settlements found.') }}</td></tr>
          @endforelse
        </tbody>
      </table></div>
      {{ $settlements->links() }}
    </div>
  </div>
@endsection
