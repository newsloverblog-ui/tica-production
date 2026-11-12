@extends('backend.layout')

@section('content')
  <div class="page-header">
    <h4 class="page-title">{{ __('Webhook operations') }}</h4>
    <ul class="breadcrumbs">
      <li class="nav-home"><a href="{{ route('admin.dashboard') }}"><i class="flaticon-home"></i></a></li>
      <li class="separator"><i class="flaticon-right-arrow"></i></li>
      <li class="nav-item"><a href="{{ route('admin.operational_incidents.index') }}">{{ __('Operational incidents') }}</a></li>
      <li class="separator"><i class="flaticon-right-arrow"></i></li>
      <li class="nav-item"><a href="#">{{ __('Webhooks') }}</a></li>
    </ul>
  </div>

  <div class="card mb-4">
    <div class="card-header">
      <div class="row align-items-center">
        <div class="col-lg-4">
          <div class="card-title">{{ __('Gateway delivery ledger') }}</div>
          <small class="text-muted">{{ __('Raw webhook payloads and customer data are not retained.') }}</small>
          <div class="mt-2"><a href="{{ route('admin.operations_exports.index') }}" class="btn btn-sm btn-outline-secondary">{{ __('Export history') }}</a></div>
        </div>
        <div class="col-lg-8">
          <form method="get" class="form-inline justify-content-lg-end">
            <input type="date" name="from" value="{{ $from->toDateString() }}" class="form-control mr-2 mb-2">
            <input type="date" name="to" value="{{ $to->toDateString() }}" class="form-control mr-2 mb-2">
            <select name="gateway" class="form-control mr-2 mb-2">
              <option value="">{{ __('All gateways') }}</option>
              @foreach (['paystack', 'flutterwave'] as $gateway)
                <option value="{{ $gateway }}" @selected(request('gateway') === $gateway)>{{ ucfirst($gateway) }}</option>
              @endforeach
            </select>
            <select name="outcome" class="form-control mr-2 mb-2">
              <option value="">{{ __('All outcomes') }}</option>
              @foreach (['processed', 'duplicate', 'ignored', 'rejected', 'failed', 'received'] as $outcome)
                <option value="{{ $outcome }}" @selected(request('outcome') === $outcome)>{{ __(ucfirst($outcome)) }}</option>
              @endforeach
            </select>
            <button class="btn btn-primary mb-2">{{ __('Apply') }}</button>
          </form>
        </div>
      </div>
    </div>
  </div>

  <div class="row">
    @foreach (['processed', 'duplicate', 'ignored', 'rejected', 'failed', 'received'] as $outcome)
      <div class="col-6 col-lg-2 mb-4"><div class="card h-100"><div class="card-body">
        <small class="text-muted text-uppercase">{{ __($outcome) }}</small>
        <h3 class="mb-0">{{ number_format($counts[$outcome] ?? 0) }}</h3>
      </div></div></div>
    @endforeach
  </div>

  <div class="card">
    <div class="card-body">
      <form method="post" action="{{ route('admin.webhook_operations.export') }}" class="form-inline justify-content-end mb-3">
        @csrf<input type="hidden" name="from" value="{{ $from->toDateString() }}"><input type="hidden" name="to" value="{{ $to->toDateString() }}"><input type="hidden" name="gateway" value="{{ request('gateway') }}"><input type="hidden" name="outcome" value="{{ request('outcome') }}">
        <label class="mr-2" for="webhook-export-format">{{ __('Export current results') }}</label><select id="webhook-export-format" name="format" class="form-control mr-2" required><option value="csv">CSV</option><option value="xlsx">XLSX</option></select><button class="btn btn-primary" type="submit">{{ __('Queue private export') }}</button>
      </form>
      <div class="table-responsive">
      <table class="table table-striped mb-0">
        <thead><tr><th>{{ __('Received') }}</th><th>{{ __('Gateway') }}</th><th>{{ __('Event') }}</th><th>{{ __('Outcome') }}</th><th>{{ __('Signature') }}</th><th>{{ __('HTTP') }}</th><th>{{ __('Duration') }}</th><th>{{ __('Request ID') }}</th></tr></thead>
        <tbody>
        @forelse ($receipts as $receipt)
          <tr>
            <td>{{ $receipt->received_at->format('Y-m-d H:i:s') }}</td>
            <td>{{ ucfirst($receipt->gateway) }}</td>
            <td>{{ $receipt->event_type ?: '—' }}</td>
            <td><span class="badge badge-{{ in_array($receipt->outcome, ['processed', 'duplicate', 'ignored']) ? 'success' : ($receipt->outcome === 'received' ? 'warning' : 'danger') }}">{{ strtoupper($receipt->outcome) }}</span></td>
            <td>
              @if ($receipt->signature_valid === true)<span class="text-success">{{ __('Valid') }}</span>
              @elseif ($receipt->signature_valid === false)<span class="text-danger">{{ __('Invalid') }}</span>
              @else<span class="text-muted">{{ __('Unknown') }}</span>@endif
            </td>
            <td>{{ $receipt->http_status ?: '—' }}</td>
            <td>{{ $receipt->processing_ms !== null ? number_format($receipt->processing_ms) . ' ms' : '—' }}</td>
            <td><small>{{ $receipt->request_id ?: '—' }}</small></td>
          </tr>
        @empty
          <tr><td colspan="8" class="text-center py-4">{{ __('No webhook deliveries in this period.') }}</td></tr>
        @endforelse
        </tbody>
      </table>
    </div></div>
    @if ($receipts->hasPages())<div class="card-footer">{{ $receipts->links() }}</div>@endif
  </div>
@endsection
