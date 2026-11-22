@extends('backend.layout')

@section('style')
  <link rel="stylesheet" href="{{ asset('assets/tica-ui/css/admin-ticketing.css') }}?v={{ tica_asset_version('assets/tica-ui/css/admin-ticketing.css') }}">
@endsection

@section('content')
  <div class="at-page">
    <header class="at-head">
      <div>
        <p>{{ __('Admin control center') }} <i class="fas fa-chevron-right" aria-hidden="true"></i> {{ __('Events and sales') }}</p>
        <h1>{{ __('Ticket management') }}</h1>
        <span>{{ __('Search every issued ticket, review reserved seats, export assignments and invalidate credentials safely.') }}</span>
      </div>
      @if($schemaReady)
        <div class="at-head-actions">
          <a class="at-btn secondary" href="{{ route('admin.advanced_tickets.export', request()->query()) }}"><i class="fal fa-download" aria-hidden="true"></i>{{ __('Export CSV') }}</a>
          <button class="at-btn primary" type="button" data-toggle-panel="at-issue"><i class="fal fa-ticket-alt" aria-hidden="true"></i>{{ __('Issue complimentary') }}</button>
        </div>
      @endif
    </header>

    @if(session('success'))<div class="at-alert success" role="status"><i class="fas fa-check-circle" aria-hidden="true"></i>{{ session('success') }}</div>@endif
    @if($errors->any())<div class="at-alert error" role="alert"><i class="fas fa-exclamation-circle" aria-hidden="true"></i><div><strong>{{ __('The ticket change was not completed.') }}</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div></div>@endif

    @if(!$schemaReady)
      <section class="at-empty"><i class="fal fa-database" aria-hidden="true"></i><h2>{{ __('Ticketing is temporarily unavailable') }}</h2><p>{{ __('Please try again later or contact support if this continues.') }}</p></section>
    @else
      <section class="at-stats" aria-label="{{ __('Ticket status summary') }}">
        <article><span>{{ __('Issued') }}</span><strong>{{ number_format($summary['issued']) }}</strong><small>{{ __('Valid, unused credentials') }}</small></article>
        <article><span>{{ __('Checked in') }}</span><strong>{{ number_format($summary['checkedIn']) }}</strong><small>{{ __('Already admitted') }}</small></article>
        <article><span>{{ __('Numbered') }}</span><strong>{{ number_format($summary['numbered']) }}</strong><small>{{ __('Permanent ticket numbers') }}</small></article>
        <article><span>{{ __('Reserved seats') }}</span><strong>{{ number_format($summary['seated']) }}</strong><small>{{ __('Seat assignments') }}</small></article>
        <article class="attention"><span>{{ __('Cancelled') }}</span><strong>{{ number_format($summary['cancelled']) }}</strong><small>{{ __('Revoked or refunded') }}</small></article>
      </section>

      <section class="at-panel at-issue" id="at-issue" hidden>
        <div class="at-panel-head"><div><h2>{{ __('Issue a complimentary ticket') }}</h2><p>{{ __('Uses inventory, assigns the next permanent ticket number and sends the normal ticket email.') }}</p></div><button type="button" class="at-close" data-toggle-panel="at-issue" aria-label="{{ __('Close') }}">&times;</button></div>
        <form method="post" action="{{ route('admin.advanced_tickets.issue') }}" class="at-form issue-form">
          @csrf
          <label><span>{{ __('Event') }}</span><select name="event_id" id="at-issue-event" required><option value="">{{ __('Choose event') }}</option>@foreach($events as $event)<option value="{{ $event->id }}" @selected(old('event_id')==$event->id)>{{ $event->title }}</option>@endforeach</select></label>
          <label><span>{{ __('Ticket type') }}</span><select name="ticket_id" id="at-issue-ticket" required><option value="">{{ __('Choose ticket type') }}</option>@foreach($ticketTypes as $type)<option value="{{ $type->id }}" data-event="{{ $type->event_id }}" data-reserved="{{ $type->seating_mode === 'reserved' ? '1' : '0' }}" @selected(old('ticket_id')==$type->id)>{{ $type->display_name }}{{ $type->seating_mode === 'reserved' ? ' · Reserved' : '' }}</option>@endforeach</select><small>{{ __('Reserved categories require one available seat.') }}</small></label>
          <label id="at-issue-seat-wrap" hidden><span>{{ __('Reserved seat') }}</span><select name="seat_id" id="at-issue-seat" data-old="{{ old('seat_id') }}"><option value="">{{ __('Choose an available seat') }}</option></select><small>{{ __('The seat is held and permanently assigned when the ticket is issued.') }}</small></label>
          <label><span>{{ __('Attendee name') }}</span><input name="name" value="{{ old('name') }}" required maxlength="200"></label>
          <label><span>{{ __('Email address') }}</span><input type="email" name="email" value="{{ old('email') }}" required maxlength="191"></label>
          <button class="at-btn primary" type="submit">{{ __('Issue and send ticket') }}</button>
        </form>
      </section>

      <section class="at-panel">
        <div class="at-panel-head"><div><h2>{{ __('Inventory and numbering') }}</h2><p>{{ __('Every ticket category across every event, including its independent stock and next sequence.') }}</p></div></div>
        <div class="at-table-wrap">
          <table class="at-table">
            <thead><tr><th>{{ __('Event and category') }}</th><th class="t-num">{{ __('Inventory') }}</th><th class="t-num">{{ __('Sold') }}</th><th class="t-num">{{ __('Available') }}</th><th>{{ __('Numbering') }}</th><th>{{ __('Admission') }}</th><th>{{ __('Sales') }}</th></tr></thead>
            <tbody>@forelse($categoryRows as $category)
              <tr><td data-label="{{ __('Event and category') }}"><strong>{{ $category['ticket'] }}</strong><small>{{ $category['event'] }}</small></td><td data-label="{{ __('Inventory') }}" class="t-num">{{ $category['inventory'] === null ? '—' : number_format($category['inventory']) }}</td><td data-label="{{ __('Sold') }}" class="t-num">{{ number_format($category['sold']) }}</td><td data-label="{{ __('Available') }}" class="t-num">{{ $category['available'] === null ? '—' : number_format($category['available']) }}</td><td data-label="{{ __('Numbering') }}">{{ $category['numbering'] }}</td><td data-label="{{ __('Admission') }}">{{ $category['seating'] }}</td><td data-label="{{ __('Sales') }}"><span class="at-status {{ $category['status'] === 'on_sale' ? 'positive' : 'info' }}">{{ $category['status'] === 'on_sale' ? __('On sale') : __(ucfirst(str_replace('_',' ',$category['status']))) }}</span></td></tr>
            @empty<tr><td colspan="7"><div class="at-no-results"><strong>{{ __('No ticket categories yet') }}</strong></div></td></tr>@endforelse</tbody>
          </table>
        </div>
      </section>

      <section class="at-panel">
        <form class="at-filters" method="get">
          <label class="search"><i class="fal fa-search" aria-hidden="true"></i><span class="sr-only">{{ __('Search') }}</span><input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('Ticket number, attendee, email or seat') }}"></label>
          <label><span class="sr-only">{{ __('Event') }}</span><select name="event"><option value="">{{ __('All events') }}</option>@foreach($events as $event)<option value="{{ $event->id }}" @selected(($filters['event']??null)==$event->id)>{{ $event->title }}</option>@endforeach</select></label>
          <label><span class="sr-only">{{ __('Ticket category') }}</span><select name="ticket"><option value="">{{ __('All ticket categories') }}</option>@foreach($ticketTypes as $type)<option value="{{ $type->id }}" @selected(($filters['ticket']??null)==$type->id)>{{ $type->display_name }}</option>@endforeach</select></label>
          <label><span class="sr-only">{{ __('Organizer') }}</span><select name="organizer"><option value="">{{ __('All organizers') }}</option>@foreach($organizers as $organizer)<option value="{{ $organizer->id }}" @selected(($filters['organizer']??null)==$organizer->id)>{{ $organizer->display_name }}</option>@endforeach</select></label>
          <label><span class="sr-only">{{ __('Status') }}</span><select name="status"><option value="">{{ __('Any status') }}</option>@foreach(['issued'=>'Issued','checked_in'=>'Checked in','revoked'=>'Revoked','refunded'=>'Refunded'] as $value=>$label)<option value="{{ $value }}" @selected(($filters['status']??'')===$value)>{{ __($label) }}</option>@endforeach</select></label>
          <button class="at-btn secondary" type="submit">{{ __('Apply') }}</button>
          @if(array_filter($filters))<a class="at-reset" href="{{ route('admin.advanced_tickets.index') }}">{{ __('Clear') }}</a>@endif
        </form>

        <div class="at-table-wrap">
          <table class="at-table">
            <thead><tr><th>{{ __('Ticket number') }}</th><th>{{ __('Attendee') }}</th><th>{{ __('Event and category') }}</th><th>{{ __('Seat') }}</th><th>{{ __('Organizer') }}</th><th>{{ __('Status') }}</th><th><span class="sr-only">{{ __('Actions') }}</span></th></tr></thead>
            <tbody>
              @forelse($units as $unit)
                @php
                  $status = $unit->checked_in_at ? 'Checked in' : ucfirst(str_replace('_',' ',$unit->status));
                  $seat = collect([$unit->section_label, $unit->row_label, $unit->seat_label])->filter()->implode(' · ');
                @endphp
                <tr>
                  <td data-label="{{ __('Ticket number') }}"><strong class="at-number">{{ $unit->ticket_number ?: '—' }}</strong><small>TICA-{{ str_pad($unit->booking_id,6,'0',STR_PAD_LEFT) }}-{{ $unit->sequence }}</small></td>
                  <td data-label="{{ __('Attendee') }}"><strong>{{ $unit->holder_name ?: '—' }}</strong><small>{{ $unit->holder_email }}</small></td>
                  <td data-label="{{ __('Event and category') }}"><strong>{{ $ticketNames[$unit->ticket_id] ?? $unit->variation_name ?? __('Ticket') }}</strong><small>{{ $eventNames[$unit->event_id] ?? __('Event #:id',['id'=>$unit->event_id]) }}</small></td>
                  <td data-label="{{ __('Seat') }}">{{ $seat ?: __('General admission') }}</td>
                  <td data-label="{{ __('Organizer') }}">{{ $organizerNames[$unit->organizer_id] ?? ($unit->organizer_id ? __('Organizer #:id',['id'=>$unit->organizer_id]) : __('TICA')) }}</td>
                  <td data-label="{{ __('Status') }}"><span class="at-status {{ in_array($unit->status,['revoked','refunded'])?'negative':($unit->checked_in_at?'info':'positive') }}">{{ __($status) }}</span></td>
                  <td class="actions">
                    <details><summary aria-label="{{ __('Actions for :number',['number'=>$unit->ticket_number ?: $unit->id]) }}"><i class="fal fa-ellipsis-h" aria-hidden="true"></i></summary><div>
                      <a href="{{ route('admin.event_booking.details',$unit->booking_id) }}">{{ __('Review order') }}</a>
                      @if($unit->status==='issued')
                        <form method="post" action="{{ route('admin.advanced_tickets.reissue',$unit) }}">@csrf<input type="hidden" name="reason" value="Reissued by TICA admin"><button type="submit">{{ __('Reissue QR credential') }}</button></form>
                        <form method="post" action="{{ route('admin.advanced_tickets.revoke',$unit) }}" onsubmit="return confirm('{{ __('Cancel this ticket and invalidate its QR code?') }}')">@csrf<input type="hidden" name="reason" value="Cancelled by TICA admin"><button class="danger" type="submit">{{ __('Cancel ticket') }}</button></form>
                      @endif
                    </div></details>
                  </td>
                </tr>
              @empty
                <tr><td colspan="7"><div class="at-no-results"><i class="fal fa-ticket-alt" aria-hidden="true"></i><strong>{{ __('No tickets match these filters') }}</strong><span>{{ __('Try a different ticket number, event, category, organizer or status.') }}</span></div></td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
        <div class="at-pagination">{{ $units->links() }}</div>
      </section>
    @endif
  </div>
@endsection

@section('script')
<script>
document.addEventListener('click', function (event) {
  const button = event.target.closest('[data-toggle-panel]');
  if (!button) return;
  const panel = document.getElementById(button.dataset.togglePanel);
  if (panel) { panel.hidden = !panel.hidden; if (!panel.hidden) panel.querySelector('select,input')?.focus(); }
});
const eventSelect = document.getElementById('at-issue-event');
const ticketSelect = document.getElementById('at-issue-ticket');
const seatWrap = document.getElementById('at-issue-seat-wrap');
const seatSelect = document.getElementById('at-issue-seat');
let seatRequest = 0;
async function loadIssueSeats() {
  if (!ticketSelect || !seatWrap || !seatSelect) return;
  const option = ticketSelect.selectedOptions[0];
  const reserved = option?.dataset.reserved === '1';
  seatWrap.hidden = !reserved;
  seatSelect.required = reserved;
  if (!reserved) { seatSelect.innerHTML = '<option value="">{{ __('Choose an available seat') }}</option>'; return; }
  const request = ++seatRequest;
  seatSelect.innerHTML = '<option value="">{{ __('Loading available seats…') }}</option>';
  try {
    const response = await fetch('/tica-api/events/' + encodeURIComponent(eventSelect.value) + '/seats', {headers:{Accept:'application/json'}, credentials:'same-origin'});
    const data = await response.json();
    if (!response.ok) throw new Error(data.message || '{{ __('Seats are unavailable.') }}');
    if (request !== seatRequest) return;
    const old = seatSelect.dataset.old;
    const seats = (data.seats || []).filter(seat => String(seat.ticket_id) === option.value && seat.status === 'available');
    seatSelect.innerHTML = '<option value="">{{ __('Choose an available seat') }}</option>' + seats.map(seat => {
      const label = [seat.section, seat.row ? '{{ __('Row') }} ' + seat.row : null, '{{ __('Seat') }} ' + seat.label].filter(Boolean).join(' · ');
      return '<option value="' + seat.id + '"' + (String(seat.id) === old ? ' selected' : '') + '>' + label.replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char])) + '</option>';
    }).join('');
  } catch (error) {
    if (request === seatRequest) seatSelect.innerHTML = '<option value="">' + error.message + '</option>';
  }
}
if (eventSelect && ticketSelect) eventSelect.addEventListener('change', function () {
  Array.from(ticketSelect.options).forEach(function (option) { if (option.value) option.hidden = option.dataset.event !== eventSelect.value; });
  if (ticketSelect.selectedOptions[0]?.hidden) ticketSelect.value = '';
  loadIssueSeats();
});
if (ticketSelect) ticketSelect.addEventListener('change', loadIssueSeats);
if (eventSelect && eventSelect.value) eventSelect.dispatchEvent(new Event('change'));
</script>
@endsection
