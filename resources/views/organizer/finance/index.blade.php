@extends('organizer.layout')

@section('content')
  @php
    $symbol = optional($currency)->base_currency_symbol ?: '₦';
    $money = fn ($value) => $symbol . number_format((float) $value, 2);
  @endphp
  <div class="page-header">
    <h4 class="page-title">{{ __('Finance statement') }}</h4>
    <ul class="breadcrumbs"><li class="nav-home"><a href="{{ route('organizer.dashboard') }}"><i class="flaticon-home"></i></a></li><li class="separator"><i class="flaticon-right-arrow"></i></li><li class="nav-item"><a href="#">{{ __('Finance') }}</a></li></ul>
  </div>

  <div class="card mb-4"><div class="card-body">
    <form method="get" class="row align-items-end">
      <div class="col-md-4"><label>{{ __('From') }}</label><input type="date" name="from" class="form-control" value="{{ $from->format('Y-m-d') }}" required></div>
      <div class="col-md-4"><label>{{ __('To') }}</label><input type="date" name="to" class="form-control" value="{{ $to->format('Y-m-d') }}" required></div>
      <div class="col-md-4 mt-3 mt-md-0"><button class="btn btn-primary" type="submit">{{ __('Apply range') }}</button></div>
    </form>
  </div></div>

  <div class="row">
    @foreach ([
      ['Gross sales', $statement['gross_sales'], 'fa-shopping-cart'],
      ['Platform fees', $statement['platform_fees'], 'fa-receipt'],
      ['Refunds', $statement['refunds'], 'fa-undo'],
      ['Net sales', $statement['net_sales'], 'fa-chart-line'],
      ['Available balance', $statement['available_balance'], 'fa-wallet'],
      ['Pending payouts', $statement['pending_payouts'], 'fa-clock'],
      ['Paid out', $statement['paid_out'], 'fa-check-circle'],
      ['Payout fees', $statement['payout_fees'], 'fa-percentage'],
    ] as [$label, $value, $icon])
      <div class="col-sm-6 col-xl-3 mb-4"><div class="card h-100"><div class="card-body">
        <div class="d-flex justify-content-between"><small class="text-muted text-uppercase">{{ __($label) }}</small><i class="fal {{ $icon }} text-primary"></i></div>
        <h4 class="mt-2 mb-0">{{ $money($value) }}</h4>
      </div></div></div>
    @endforeach
  </div>

  @php
    $payoutBadge = [
      'ready' => 'success', 'scheduled' => 'info', 'processing' => 'primary',
      'none' => 'secondary', 'manual_review' => 'warning',
      'review_required' => 'danger', 'action_required' => 'warning',
    ][$nextPayout['status']] ?? 'secondary';
  @endphp
  <div class="card mb-4 border-left border-{{ $payoutBadge }}">
    <div class="card-body d-md-flex justify-content-between align-items-center">
      <div class="d-flex align-items-start">
        <span class="rounded-circle bg-light text-primary d-inline-flex align-items-center justify-content-center mr-3" style="width:46px;height:46px;flex:0 0 46px;">
          <i class="fal fa-calendar-check"></i>
        </span>
        <div>
          <small class="text-muted text-uppercase">{{ __('Next payout') }}</small>
          <h5 class="mb-1">
            @if($nextPayout['scheduled_at'])
              {{ \Carbon\Carbon::parse($nextPayout['scheduled_at'])->timezone(config('payouts.eligibility_policy.timezone', 'Africa/Lagos'))->format('D, j M Y \a\t g:i A') }}
            @else
              {{ __($nextPayout['label']) }}
            @endif
          </h5>
          <p class="text-muted mb-0">{{ __($nextPayout['message']) }}</p>
          @if($nextPayout['reference'])<small class="text-muted">{{ __('Request') }}: {{ $nextPayout['reference'] }}</small>@endif
        </div>
      </div>
      <span class="badge badge-{{ $payoutBadge }} px-3 py-2 mt-3 mt-md-0">{{ __($nextPayout['label']) }}</span>
    </div>
  </div>

  <div class="alert alert-light border">
    <strong>{{ __('Gateway fees:') }}</strong> {{ __('Not allocated to organizer transactions yet. They are reconciled at the Paystack settlement level and are intentionally excluded rather than estimated.') }}
  </div>

  <div class="card"><div class="card-header"><div class="row align-items-center">
    <div class="col-md-5"><div class="card-title">{{ __('Ledger activity') }}</div><small class="text-muted">{{ $statement['transaction_count'] }} {{ __('records in this period') }}</small></div>
    <div class="col-md-7 text-md-right mt-3 mt-md-0">
      @foreach (['csv' => 'CSV', 'xlsx' => 'Excel', 'pdf' => 'PDF'] as $format => $label)
        <a class="btn btn-sm btn-outline-primary" href="{{ route('organizer.finance.export', ['from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d'), 'file_type' => $format]) }}"><i class="fas fa-download mr-1"></i>{{ $label }}</a>
      @endforeach
      <a class="btn btn-sm btn-link" href="{{ route('organizer.data_exports.index') }}">{{ __('Export history') }}</a>
    </div>
  </div></div>
  @if ($statement['transaction_count'] > config('exports.immediate_finance_rows', 10000))
    <div class="alert alert-info rounded-0 mb-0">
      {{ __('CSV and Excel statements of this size are generated privately in the background. PDF is available for smaller ranges only.') }}
    </div>
  @endif
  <div class="card-body p-0">
    <div class="table-responsive"><table class="table table-striped mb-0"><thead><tr>
      <th>{{ __('Date') }}</th><th>{{ __('Reference') }}</th><th>{{ __('Type') }}</th><th>{{ __('Status') }}</th><th class="text-right">{{ __('Gross') }}</th><th class="text-right">{{ __('Fee') }}</th><th class="text-right">{{ __('Net') }}</th>
    </tr></thead><tbody>
      @forelse ($transactions as $row)
        <tr><td>{{ $row['occurred_at'] ? \Carbon\Carbon::parse($row['occurred_at'])->format('Y-m-d H:i') : '-' }}</td><td>{{ $row['reference'] }}</td><td>{{ __(ucwords(str_replace('_', ' ', $row['type']))) }}</td><td><span class="badge badge-{{ $row['status'] === 'completed' ? 'success' : ($row['status'] === 'rejected' ? 'danger' : 'warning') }}">{{ __(ucfirst($row['status'])) }}</span></td><td class="text-right">{{ $money($row['gross_amount']) }}</td><td class="text-right">{{ $money($row['platform_fee']) }}</td><td class="text-right font-weight-bold">{{ $money($row['net_amount']) }}</td></tr>
      @empty
        <tr><td colspan="7" class="text-center py-5 text-muted">{{ __('No finance activity in this period.') }}</td></tr>
      @endforelse
    </tbody></table></div>
  </div><div class="card-footer">{{ $transactions->links() }}</div></div>
@endsection
