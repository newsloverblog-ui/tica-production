@php
  $bookingReportUrl = route('admin.event_booking.report', [
    'from_date' => today()->startOfMonth()->toDateString(),
    'to_date' => today()->toDateString(),
  ]);
  $reportGroups = array_filter(array_map('array_filter', [
    __('Bookings and events') => [
      $can('Event Bookings') ? [__('Booking report'), __('Filter orders by date and payment status.'), $bookingReportUrl] : null,
      $can('Event Bookings') ? [__('Booking exports'), __('Review downloads and scheduled reports.'), route('admin.data_exports.index')] : null,
      $can('Transaction') ? [__('Checkout analytics'), __('See how visits turn into purchases.'), route('admin.funnel_analytics.index')] : null,
      $can('Event Management') && $can('Transaction') ? [__('Cancellation exports'), __('Download cancellation and refund case data.'), route('admin.event_cancellation_exports.index')] : null,
    ],
    __('Finance') => [
      $can('Lifetime Earning') ? [__('Monthly revenue'), __('Review revenue by month and year.'), route('admin.monthly_earning')] : null,
      $can('Total Profit') ? [__('Monthly earnings'), __('Review TICA earnings by month and year.'), route('admin.monthly_profit')] : null,
      $can('Transaction') ? [__('Transaction exports'), __('Download transaction history.'), route('admin.transaction_exports.index')] : null,
      $can('Transaction') ? [__('Payment reconciliation'), __('Compare payments with gateway records.'), route('admin.payment_reconciliation.index')] : null,
      $can('Transaction') ? [__('Gateway settlements'), __('Review gateway settlement records.'), route('admin.settlement_reconciliation.index')] : null,
      $can('Transaction') ? [__('Reconciliation exports'), __('Download payment and settlement reports.'), route('admin.reconciliation_exports.index')] : null,
      $can('Withdraw Method') && $can('Transaction') ? [__('Payout exports'), __('Download organiser payout reports.'), route('admin.withdraw_exports.index')] : null,
    ],
    __('Operations and people') => [
      $can('Transaction') ? [__('Operations exports'), __('Download incident and webhook reports.'), route('admin.operations_exports.index')] : null,
      $can('Organizer Mangement', 'Customer Management') ? [__('Directory exports'), __('Review your customer and organiser downloads.'), route('admin.directory_exports.index')] : null,
    ],
  ]));
@endphp

@if ($reportGroups)
  <section class="cd-card" aria-label="{{ __('Available reports') }}">
    <div class="cd-report-groups">
      @foreach ($reportGroups as $group => $reports)
        <div>
          <h3>{{ $group }}</h3>
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
        </div>
      @endforeach
    </div>
  </section>
@endif
