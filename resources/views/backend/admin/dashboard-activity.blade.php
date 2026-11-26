@if ($can('Transaction'))
  <section class="cd-card cd-table" aria-labelledby="cd-currency-h">
    <div class="cd-card-h">
      <h2 id="cd-currency-h">{{ __('Paid sales by currency') }}</h2>
      <span class="cd-pill">{{ now()->year }}</span>
    </div>
    @if ($o['currencies'])
      <div class="cd-scroll" tabindex="0" role="region" aria-labelledby="cd-currency-h">
        <table aria-labelledby="cd-currency-h">
          <thead><tr>
            <th scope="col">{{ __('Currency') }}</th>
            <th scope="col" class="cd-num">{{ __('Paid orders') }}</th>
            <th scope="col" class="cd-num">{{ __('Buyer payments') }}</th>
            @if ($can('Total Profit'))<th scope="col" class="cd-num">{{ __('Platform fees') }}</th>@endif
          </tr></thead>
          <tbody>
            @foreach ($o['currencies'] as $row)
              <tr>
                <th scope="row">{{ $row['code'] }}</th>
                <td class="cd-num">{{ number_format($row['orders']) }}</td>
                <td class="cd-num">{{ number_format($row['sales'], 2) }}</td>
                @if ($can('Total Profit'))<td class="cd-num">{{ number_format($row['fees'], 2) }}</td>@endif
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @else
      <p class="cd-empty">{{ __('No completed sales this year.') }}</p>
    @endif
  </section>
@endif
@if ($can('Event Bookings'))
  <section class="cd-card cd-table" aria-labelledby="cd-orders-h">
    <div class="cd-card-h">
      <h2 id="cd-orders-h">{{ __('Recent ticket orders') }}</h2>
      <a class="cd-btn sm" href="{{ route('admin.event.booking') }}">{{ __('See all orders') }}</a>
    </div>
    @if ($o['recent'])
      <div class="cd-scroll" tabindex="0" role="region" aria-labelledby="cd-orders-h">
        <table aria-labelledby="cd-orders-h">
          <thead><tr>
            <th scope="col">{{ __('Order') }}</th>
            <th scope="col">{{ __('Buyer') }}</th>
            <th scope="col">{{ __('Event') }}</th>
            <th scope="col" class="cd-num">{{ __('Tickets') }}</th>
            <th scope="col" class="cd-num">{{ __('Order total') }}</th>
            <th scope="col">{{ __('Payment status') }}</th>
            <th scope="col">{{ __('Placed') }}</th>
          </tr></thead>
          <tbody>
            @foreach ($o['recent'] as $row)
              <tr>
                <td><a class="cd-record" href="{{ route('admin.event_booking.details', ['id' => $row['id']]) }}">{{ $row['reference'] }}</a></td>
                <td>{{ $row['buyer'] ?: __('Guest') }}</td>
                <td>{{ $row['event'] }}</td>
                <td class="cd-num">{{ number_format($row['tickets']) }}</td>
                <td class="cd-num">{{ number_format($row['amount'], 2) }} {{ $row['currency'] }}</td>
                <td>{{ __(ucfirst(str_replace('_', ' ', $row['status']))) }}</td>
                <td>{{ $row['when'] ?? '—' }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @else
      <p class="cd-empty">{{ __('Ticket orders will appear here as they come in.') }}</p>
    @endif
  </section>
@endif
