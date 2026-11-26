@php
  $bookingReportUrl = route('admin.event_booking.report', [
    'from_date' => today()->startOfMonth()->toDateString(),
    'to_date' => today()->toDateString(),
  ]);
  $reports = array_filter([
    $can('Event Bookings') ? [__('Booking report'), __('Filter orders by date and payment status.'), $bookingReportUrl] : null,
    $can('Event Bookings') ? [__('Booking exports'), __('Review downloads and scheduled reports.'), route('admin.data_exports.index')] : null,
    $can('Lifetime Earning') ? [__('Monthly revenue'), __('Review revenue by month and year.'), route('admin.monthly_earning')] : null,
    $can('Total Profit') ? [__('Monthly earnings'), __('Review platform earnings by month and year.'), route('admin.monthly_profit')] : null,
    $can('Transaction') ? [__('Checkout analytics'), __('See how visitors move from event views to purchases.'), route('admin.funnel_analytics.index')] : null,
    $can('Transaction') ? [__('Payment reconciliation'), __('Compare payment records with gateway activity.'), route('admin.payment_reconciliation.index')] : null,
    $can('Transaction') ? [__('Gateway settlements'), __('Review transfers and settlement records.'), route('admin.settlement_reconciliation.index')] : null,
    $can('Withdraw Method') && $can('Transaction') ? [__('Payout exports'), __('Download organiser payout reports.'), route('admin.withdraw_exports.index')] : null,
  ]);
@endphp

@if ($reports)
  <section class="cd-card" aria-labelledby="cd-reports-h">
    <div class="cd-card-h"><h2 id="cd-reports-h">{{ __('Reports') }}</h2></div>
    <ul class="cd-report-links">
      @foreach ($reports as [$label, $description, $href])
        <li>
          <a href="{{ $href }}">
            <span><strong>{{ $label }}</strong><small>{{ $description }}</small></span>
            <i class="fal fa-chevron-right" aria-hidden="true"></i>
          </a>
        </li>
      @endforeach
    </ul>
  </section>
@endif
