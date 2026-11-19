@extends('organizer.layout')

@section('content')
  <div class="t-page-head">
    <div>
      <h1>{{ __('My tickets') }}</h1>
      <p>{{ __('Tickets and orders you bought before your account became an organizer account.') }}</p>
    </div>
  </div>

  <div class="t-card">
    @if ($bookings->isEmpty())
      <div class="t-empty"><p>{{ __('You have no past ticket orders.') }}</p></div>
    @else
      <div class="t-table-wrap">
        <table class="t-table">
          <caption class="sr-only">{{ __('Your past ticket orders') }}</caption>
          <thead><tr><th scope="col">{{ __('Event') }}</th><th scope="col">{{ __('Order') }}</th><th scope="col">{{ __('Tickets') }}</th><th scope="col">{{ __('Placed') }}</th><th scope="col">{{ __('Status') }}</th><th scope="col"><span class="sr-only">{{ __('Ticket link') }}</span></th></tr></thead>
          <tbody>
            @foreach ($bookings as $booking)
              @php($paid = in_array($booking->paymentStatus, ['completed', 'free'], true))
              @php($link = $ticketUrl($booking))
              <tr>
                <td>{{ $booking->event?->title ?: __('Event #:id', ['id' => $booking->event_id]) }}</td>
                <td>{{ $booking->order_reference }}</td>
                <td>{{ number_format((int) $booking->quantity) }}</td>
                <td>{{ $booking->created_at?->format('j M Y') }}</td>
                <td><span class="t-badge {{ $paid ? 't-badge--green' : 't-badge--orange' }}">{{ $paid ? __('Confirmed') : ucfirst((string) $booking->paymentStatus) }}</span></td>
                <td class="text-right">@if ($link)<a class="t-btn t-btn--secondary t-btn--sm" href="{{ $link }}" target="_blank" rel="noopener">{{ __('Open tickets') }}</a>@endif</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      {{ $bookings->links() }}
    @endif
  </div>
@endsection
