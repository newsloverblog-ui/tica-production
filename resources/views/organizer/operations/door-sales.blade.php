@extends('organizer.layout')

@section('page-title', __('Door sales'))
@section('style') @include('organizer.operations._styles')<link rel="stylesheet" href="{{ asset('assets/tica-ui/css/door-sales.css') }}"> @endsection

@section('content')
@php
  $eventTitle = fn($event) => optional($event->information)->title ?: __('Untitled event');
  $eventData = $events->map(fn($event) => [
    'id' => $event->id,
    'name' => $eventTitle($event),
    'currency' => $event->currency ?: 'NGN',
    'tickets' => $event->tickets->filter(fn($ticket) => $ticket->pricing_type !== 'variation' && $ticket->seating_mode !== 'reserved')->map(fn($ticket) => [
      'id' => $ticket->id,
      'name' => $ticket->title ?: 'Ticket',
      'price' => (float) ($ticket->f_price ?: $ticket->price ?: 0),
      'available' => $ticket->ticket_available_type === 'limited' ? (int) $ticket->ticket_available : null,
    ])->values(),
  ])->values();
  $oldDoorEvent = (string) old('event_id', $eventData->first()['id'] ?? '');
  $oldDoorTicket = (string) old('ticket_id', '');
  $oldPayment = (string) old('payment_method', 'cash');
@endphp

<div class="op-wrap door-workspace">
  <header class="op-head door-head">
    <div>
      <h1>Sell at the door</h1>
      <p>Issue a valid TICA ticket, update inventory and admit a walk-in from one screen. Money collected here remains separate from your online payout balance.</p>
    </div>
    <nav class="door-head__links" aria-label="Door sales shortcuts">
      <a class="op-btn op-btn--secondary" href="#bulk-import">Import a guest list</a>
      <a class="op-btn op-btn--quiet" href="#door-history">Sales history</a>
    </nav>
  </header>

  @if(session('success'))<div class="op-alert" role="status">{{ session('success') }}</div>@endif
  @if($errors->any())<div class="op-alert op-alert--error" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

  <section class="door-shift" aria-label="Today's door activity">
    <div class="door-shift__label"><span>Today at the door</span><small>{{ now()->format('D, d M') }}</small></div>
    <div><strong>{{ number_format($summary['sales']) }}</strong><span>Orders recorded</span></div>
    <div><strong>{{ number_format($summary['tickets']) }}</strong><span>Tickets issued</span></div>
    <div><strong>{{ number_format($summary['checked_in']) }}</strong><span>Checked in</span></div>
    <div><strong>{{ number_format($summary['complimentary']) }}</strong><span>Guest tickets</span></div>
  </section>

  @if($events->isEmpty())
    <div class="op-alert op-alert--notice" role="status"><b>No event is ready for door sales.</b> Create or publish an event with a general-admission ticket first.</div>
  @endif

  <div class="door-console">
    <section class="op-card door-sale-panel" aria-labelledby="door-sale-heading">
      <div class="door-sale-panel__head">
        <div><h2 id="door-sale-heading">New door order</h2><p>Complete the three steps below. Ticket stock changes only after you record the sale.</p></div>
        <span class="op-pill op-pill--live"><i aria-hidden="true"></i>Inventory connected</span>
      </div>

      <form method="POST" action="{{ route('organizer.operations.door-sales.store') }}" id="door-sale-form">
        @csrf
        <section class="door-step" aria-labelledby="door-step-ticket">
          <div class="door-step__head"><span>1</span><div><h3 id="door-step-ticket">Choose the ticket</h3><p>Select the event, ticket type and quantity.</p></div></div>
          <div class="op-fields door-ticket-fields">
            <div class="op-field"><label for="door-event">Event</label><select class="op-select" id="door-event" name="event_id" required></select></div>
            <div class="op-field"><label for="door-ticket">Ticket type</label><select class="op-select" id="door-ticket" name="ticket_id" required></select><small id="door-stock" aria-live="polite"></small></div>
            <div class="op-field door-quantity"><label for="door-qty">Quantity</label><input class="op-input" id="door-qty" name="quantity" type="number" min="1" max="20" value="{{ old('quantity', 1) }}" inputmode="numeric" required></div>
          </div>
        </section>

        <section class="door-step" aria-labelledby="door-step-attendee">
          <div class="door-step__head"><span>2</span><div><h3 id="door-step-attendee">Add the attendee</h3><p>An email is optional; add one when they want the ticket in their inbox.</p></div></div>
          <div class="op-fields door-attendee-fields">
            <div class="op-field"><label for="door-name">Full name</label><input class="op-input" id="door-name" name="name" maxlength="150" required value="{{ old('name') }}" autocomplete="name" placeholder="Attendee name"></div>
            <div class="op-field"><label for="door-email">Email <small>(optional)</small></label><input class="op-input" id="door-email" name="email" type="email" maxlength="191" value="{{ old('email') }}" autocomplete="email" placeholder="name@example.com"></div>
            <div class="op-field"><label for="door-phone">Phone <small>(optional)</small></label><input class="op-input" id="door-phone" name="phone" maxlength="40" value="{{ old('phone') }}" autocomplete="tel" placeholder="+234"></div>
          </div>
        </section>

        <section class="door-step" aria-labelledby="door-step-payment">
          <div class="door-step__head"><span>3</span><div><h3 id="door-step-payment">Record payment</h3><p>Choose how payment was received at the venue.</p></div></div>
          <fieldset class="op-field"><legend class="op-label">Payment method</legend><div class="op-methods door-methods">
            @foreach(['cash'=>'Cash','pos'=>'POS','bank_transfer'=>'Bank transfer','complimentary'=>'Guest ticket'] as $value=>$label)
              <label class="op-method"><input type="radio" name="payment_method" value="{{ $value }}" @checked($oldPayment === $value)><span>{{ $label }}</span></label>
            @endforeach
          </div></fieldset>
          <div class="op-field door-amount" id="door-amount-wrap"><label for="door-amount">Amount collected</label><div class="door-money-input"><span id="door-currency">₦</span><input class="op-input" id="door-amount" name="amount" type="number" min="0" step="0.01" value="{{ old('amount') }}" inputmode="decimal" required></div><small>Change this only when the amount collected differs from the ticket total.</small></div>
        </section>

        <div class="door-sale-footer">
          <label class="op-check door-checkin"><input type="checkbox" name="check_in" value="1" @checked(old('check_in'))><span><b>Admit now</b><small>Marks every ticket in this order as checked in. They cannot be scanned again.</small></span></label>
          <button class="op-btn op-btn--primary door-submit" type="submit" @disabled($events->isEmpty())>Record sale and issue ticket</button>
        </div>
      </form>
    </section>

    <aside class="door-receipt" aria-label="Live order summary">
      <div class="door-receipt__accent" aria-hidden="true"></div>
      <div class="door-receipt__brand"><span>TICA door ticket</span><small>Live order summary</small></div>
      <div class="door-receipt__total"><small>Amount to record</small><strong id="door-total">₦0</strong></div>
      <div class="door-receipt__cut" aria-hidden="true"></div>
      <dl>
        <div><dt>Event</dt><dd id="door-summary-event">Choose an event</dd></div>
        <div><dt>Ticket</dt><dd id="door-summary-ticket">—</dd></div>
        <div><dt>Quantity</dt><dd id="door-summary-qty">1</dd></div>
        <div><dt>Ticket total</dt><dd id="door-summary-expected">₦0</dd></div>
        <div><dt>Payment</dt><dd id="door-summary-method">Cash</dd></div>
      </dl>
      <div class="door-receipt__ready"><span aria-hidden="true">✓</span><p><b>Ready to issue</b><small>The order reference and tickets are created together.</small></p></div>
      <p class="op-note">Online payout totals are not changed by door payments.</p>
    </aside>
  </div>

  <details class="op-card op-disclosure op-section door-import" id="bulk-import">
    <summary><span><b>Import complimentary attendees</b><span>Issue one guest ticket per CSV or Excel row.</span></span><span class="op-disclosure__action">Open importer</span></summary>
    <div class="door-import__intro"><p>Use the template so names, email addresses and phone numbers map correctly.</p><a class="op-btn op-btn--secondary" href="{{ route('organizer.operations.door-sales.template') }}">Download template</a></div>
    <form class="op-body" method="POST" action="{{ route('organizer.operations.door-sales.import') }}" enctype="multipart/form-data">@csrf
      <div class="op-fields"><div class="op-field"><label for="import-event">Event</label><select class="op-select" id="import-event" name="event_id" required></select></div><div class="op-field"><label for="import-ticket">Ticket type</label><select class="op-select" id="import-ticket" name="ticket_id" required></select></div><div class="op-field op-field--wide"><label for="sales-file">Guest list file</label><input class="op-file" id="sales-file" type="file" name="sales_file" accept=".csv,.xlsx,.xls" required><small>CSV, XLS or XLSX. Up to 200 attendees.</small></div><label class="op-check op-field--wide"><input type="checkbox" name="check_in" value="1"><span><b>Admit everyone in this file</b><small>Only use this for guests who are already at the entrance.</small></span></label></div>
      <div class="op-actions"><button class="op-btn op-btn--primary" type="submit" @disabled($events->isEmpty())>Import and issue tickets</button></div>
    </form>
  </details>

  <section class="op-card op-section" id="door-history" aria-labelledby="door-history-heading">
    <div class="op-card__head"><div><h2 id="door-history-heading">Sales history</h2><p>Cash, POS, transfers and guest tickets recorded at the venue.</p></div><span class="op-pill">{{ $sales->total() }} {{ Str::plural('record', $sales->total()) }}</span></div>
    @if($sales->isEmpty())
      <div class="op-empty"><b>No door orders yet</b><span>Your first recorded sale will appear here with its ticket and entry status.</span></div>
    @else
      <div class="op-table-wrap"><table class="op-table"><thead><tr><th>Order</th><th>Attendee</th><th>Event</th><th>Payment</th><th>Tickets</th><th>Amount</th><th>Entry</th><th>Recorded</th></tr></thead><tbody>@foreach($sales as $sale)<tr><td data-label="Order"><strong>{{ $sale->order_reference }}</strong></td><td data-label="Attendee">{{ trim($sale->fname.' '.$sale->lname) }}</td><td data-label="Event">{{ optional($sale->event)->title ?: 'Event' }}</td><td data-label="Payment">{{ Str::after($sale->paymentMethod, 'Door ') }}</td><td data-label="Tickets">{{ $sale->quantity }}</td><td data-label="Amount">{{ $sale->currencyText ?: 'NGN' }} {{ number_format((float)$sale->price,2) }}</td><td data-label="Entry"><span class="door-entry {{ (int)$sale->scan_status===1?'is-in':'is-issued' }}">{{ (int)$sale->scan_status===1?'Checked in':'Ticket issued' }}</span></td><td data-label="Recorded">{{ $sale->created_at->format('d M Y, H:i') }}</td></tr>@endforeach</tbody></table></div><div class="op-pager">{{ $sales->links() }}</div>
    @endif
  </section>
</div>

<script>
(function(){
  var events=@json($eventData), event=document.getElementById('door-event'), ticket=document.getElementById('door-ticket'), qty=document.getElementById('door-qty'), amount=document.getElementById('door-amount'), amountWrap=document.getElementById('door-amount-wrap'), stock=document.getElementById('door-stock'), currency=document.getElementById('door-currency'), symbols={NGN:'₦',USD:'$',GBP:'£',EUR:'€',GHS:'GH₵'}, oldEvent=@json($oldDoorEvent), oldTicket=@json($oldDoorTicket);
  function money(n,c){return (symbols[c]||c+' ')+Number(n||0).toLocaleString('en-NG',{minimumFractionDigits:0,maximumFractionDigits:2})}
  function current(select){return events.find(function(item){return String(item.id)===select.value})}
  function option(select,value,label,selected){var item=document.createElement('option');item.value=value;item.textContent=label;if(selected)item.selected=true;select.appendChild(item);return item}
  function fillEvents(select,selected){select.innerHTML='';if(!events.length){option(select,'','No events available',true);select.disabled=true;return}events.forEach(function(item,index){option(select,item.id,item.name,String(item.id)===String(selected)||(!selected&&index===0))})}
  function fillTickets(eventSelect,ticketSelect,selected){var selectedEvent=current(eventSelect),items=selectedEvent?selectedEvent.tickets:[];ticketSelect.innerHTML='';ticketSelect.disabled=!items.length;if(!items.length){option(ticketSelect,'','No general-admission tickets',true);return}items.forEach(function(item,index){var node=option(ticketSelect,item.id,item.name,String(item.id)===String(selected)||(!selected&&index===0));node.dataset.price=item.price;node.dataset.stock=item.available===null?'':item.available})}
  function selectedMethod(){return document.querySelector('[name=payment_method]:checked')}
  function paymentLabel(value){return {cash:'Cash',pos:'POS',bank_transfer:'Bank transfer',complimentary:'Guest ticket'}[value]||'—'}
  function draw(){var selectedEvent=current(event),selectedTicket=ticket.selectedOptions[0],count=Math.max(1,Number(qty.value||1)),price=Number(selectedTicket&&selectedTicket.dataset.price||0),expected=price*count,code=selectedEvent?selectedEvent.currency:'NGN',method=selectedMethod(),free=method&&method.value==='complimentary';document.getElementById('door-summary-event').textContent=selectedEvent?selectedEvent.name:'Choose an event';document.getElementById('door-summary-ticket').textContent=selectedTicket&&selectedTicket.value?selectedTicket.textContent:'—';document.getElementById('door-summary-qty').textContent=count;document.getElementById('door-summary-expected').textContent=money(expected,code);document.getElementById('door-summary-method').textContent=paymentLabel(method&&method.value);currency.textContent=symbols[code]||code;stock.textContent=selectedTicket&&selectedTicket.value?(selectedTicket.dataset.stock!==''?selectedTicket.dataset.stock+' remaining':'No inventory limit'):'';amountWrap.hidden=!!free;amount.required=!free;if(free){amount.value='0'}else if(!amount.dataset.edited){amount.value=expected.toFixed(2)}document.getElementById('door-total').textContent=money(free?0:(amount.value||expected),code)}
  fillEvents(event,oldEvent);fillTickets(event,ticket,oldTicket);fillEvents(document.getElementById('import-event'),oldEvent);fillTickets(document.getElementById('import-event'),document.getElementById('import-ticket'),'');
  event.addEventListener('change',function(){fillTickets(event,ticket,'');amount.dataset.edited='';draw()});ticket.addEventListener('change',function(){amount.dataset.edited='';draw()});qty.addEventListener('input',function(){amount.dataset.edited='';draw()});amount.addEventListener('input',function(){amount.dataset.edited='1';draw()});document.getElementById('import-event').addEventListener('change',function(){fillTickets(this,document.getElementById('import-ticket'),'')});document.querySelectorAll('[name=payment_method]').forEach(function(radio){radio.addEventListener('change',function(){amount.dataset.edited='';draw()})});draw();
})();
</script>
@endsection
