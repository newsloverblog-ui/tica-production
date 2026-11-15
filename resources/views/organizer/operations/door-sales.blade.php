@extends('organizer.layout')

@section('page-title', __('Door sales'))
@section('style') @include('organizer.operations._styles') @endsection

@section('content')
@php
  $eventTitle = fn($event) => optional($event->information)->title ?: __('Untitled event');
  $eventData = $events->map(fn($event) => [
    'id' => $event->id,
    'name' => $eventTitle($event),
    'currency' => $event->currency ?: 'NGN',
    'tickets' => $event->tickets->filter(fn($ticket) => $ticket->pricing_type !== 'variation' && $ticket->seating_mode !== 'reserved')->map(fn($ticket) => [
      'id' => $ticket->id, 'name' => $ticket->title ?: 'Ticket', 'price' => (float)($ticket->f_price ?: $ticket->price ?: 0),
      'available' => $ticket->ticket_available_type === 'limited' ? (int)$ticket->ticket_available : null,
    ])->values(),
  ])->values();
@endphp
<div class="op-wrap">
  <header class="op-head"><div><span class="op-kicker">Box office</span><h1>Door sales</h1><p>Record walk-ins, issue real TICA tickets and optionally check guests in immediately. In-person money stays separate from your online payout balance.</p></div></header>
  @if(session('success'))<div class="op-alert" role="status">{{ session('success') }}</div>@endif
  @if($errors->any())<div class="op-alert op-alert--error" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

  <div class="op-grid">
    <section class="op-card" aria-labelledby="op-sale-h">
      <div class="op-card__head"><div><h2 id="op-sale-h">Record a sale</h2><p>Ticket stock updates as soon as the sale is recorded.</p></div><span class="op-pill">Secure entry</span></div>
      <form class="op-body" method="POST" action="{{ route('organizer.operations.door-sales.store') }}" id="door-sale-form">@csrf
        <div class="op-fields">
          <div class="op-field"><label for="door-event">Event</label><select class="op-select" id="door-event" name="event_id" required></select></div>
          <div class="op-field"><label for="door-ticket">Ticket</label><select class="op-select" id="door-ticket" name="ticket_id" required></select><small id="door-stock"></small></div>
          <div class="op-field"><label for="door-name">Attendee name</label><input class="op-input" id="door-name" name="name" maxlength="150" required value="{{ old('name') }}" autocomplete="name"></div>
          <div class="op-field"><label for="door-qty">Quantity</label><input class="op-input" id="door-qty" name="quantity" type="number" min="1" max="20" value="{{ old('quantity', 1) }}" required></div>
          <div class="op-field"><label for="door-email">Email <small>(optional)</small></label><input class="op-input" id="door-email" name="email" type="email" maxlength="191" value="{{ old('email') }}" autocomplete="email"><small>We email the tickets when provided.</small></div>
          <div class="op-field"><label for="door-phone">Phone <small>(optional)</small></label><input class="op-input" id="door-phone" name="phone" maxlength="40" value="{{ old('phone') }}" autocomplete="tel"></div>
          <fieldset class="op-field op-field--wide"><legend class="op-label">Payment received via</legend><div class="op-methods">
            @foreach(['cash'=>'Cash','pos'=>'POS','bank_transfer'=>'Bank transfer','complimentary'=>'Complimentary'] as $value=>$label)<label class="op-method"><input type="radio" name="payment_method" value="{{ $value }}" @checked(old('payment_method','cash')===$value)><span>{{ $label }}</span></label>@endforeach
          </div></fieldset>
          <div class="op-field op-field--wide" id="door-amount-wrap"><label for="door-amount">Amount collected</label><input class="op-input" id="door-amount" name="amount" type="number" min="0" step="0.01" value="{{ old('amount') }}" required><small>Enter what was actually received. This is reported as box-office revenue.</small></div>
          <label class="op-check op-field--wide"><input type="checkbox" name="check_in" value="1" @checked(old('check_in'))><span><b>Check in now</b><small>Use this when the attendee is entering immediately. Their ticket cannot be scanned a second time.</small></span></label>
        </div>
        <div class="op-actions"><button class="op-btn op-btn--primary" type="submit">Record sale and issue ticket</button></div>
      </form>
    </section>
    <aside class="op-card op-receipt" aria-label="Sale summary">
      <div class="op-receipt__top"><span>Sale summary</span><strong id="door-total">₦0</strong></div>
      <dl><div><dt>Event</dt><dd id="door-summary-event">Choose an event</dd></div><div><dt>Ticket</dt><dd id="door-summary-ticket">—</dd></div><div><dt>Quantity</dt><dd id="door-summary-qty">1</dd></div><div><dt>Expected total</dt><dd id="door-summary-expected">₦0</dd></div></dl>
      <p class="op-note">Door payments are recorded for reporting and ticket fulfilment. They never increase the money available for online withdrawal.</p>
    </aside>
  </div>

  <details class="op-card op-disclosure op-section"><summary><span><b>Import complimentary attendees</b><span>Upload CSV or Excel and issue one ticket per row.</span></span><a class="op-btn op-btn--secondary" href="{{ route('organizer.operations.door-sales.template') }}" onclick="event.stopPropagation()">Download template</a></summary>
    <form class="op-body" method="POST" action="{{ route('organizer.operations.door-sales.import') }}" enctype="multipart/form-data">@csrf
      <div class="op-fields"><div class="op-field"><label for="import-event">Event</label><select class="op-select" id="import-event" name="event_id" required></select></div><div class="op-field"><label for="import-ticket">Ticket</label><select class="op-select" id="import-ticket" name="ticket_id" required></select></div><div class="op-field op-field--wide"><label for="sales-file">CSV or Excel file</label><input class="op-file" id="sales-file" type="file" name="sales_file" accept=".csv,.xlsx,.xls" required></div><label class="op-check op-field--wide"><input type="checkbox" name="check_in" value="1"><span><b>Check in everyone in this file</b><small>Only use this for people already at the entrance.</small></span></label></div>
      <div class="op-actions"><button class="op-btn op-btn--primary" type="submit">Import and issue tickets</button></div>
    </form>
  </details>

  <section class="op-card op-section" aria-labelledby="door-history-h"><div class="op-card__head"><div><h2 id="door-history-h">Door sales history</h2><p>A clear trail of cash, POS, transfers and complimentary entry.</p></div><span class="op-pill">{{ $sales->total() }} records</span></div>
    @if($sales->isEmpty())<div class="op-empty">No door sales yet.</div>@else<div class="op-table-wrap"><table class="op-table"><thead><tr><th>Order</th><th>Attendee</th><th>Event</th><th>Method</th><th>Tickets</th><th>Amount</th><th>Entry</th><th>Recorded</th></tr></thead><tbody>@foreach($sales as $sale)<tr><td data-label="Order"><strong>{{ $sale->order_reference }}</strong></td><td data-label="Attendee">{{ trim($sale->fname.' '.$sale->lname) }}</td><td data-label="Event">{{ optional($sale->event)->title ?: 'Event' }}</td><td data-label="Method">{{ Str::after($sale->paymentMethod, 'Door ') }}</td><td data-label="Tickets">{{ $sale->quantity }}</td><td data-label="Amount">{{ $sale->currencyText ?: 'NGN' }} {{ number_format((float)$sale->price,2) }}</td><td data-label="Entry">{{ (int)$sale->scan_status===1?'Checked in':'Ticket issued' }}</td><td data-label="Recorded">{{ $sale->created_at->format('d M Y, H:i') }}</td></tr>@endforeach</tbody></table></div><div class="op-pager">{{ $sales->links() }}</div>@endif
  </section>
</div>
<script>
(function(){var events=@json($eventData),event=document.getElementById('door-event'),ticket=document.getElementById('door-ticket'),qty=document.getElementById('door-qty'),amount=document.getElementById('door-amount'),stock=document.getElementById('door-stock'),symbol={NGN:'₦',USD:'$',GBP:'£',EUR:'€',GHS:'GH₵'};
function money(n,c){return(symbol[c]||c+' ')+Number(n||0).toLocaleString('en-NG',{minimumFractionDigits:0,maximumFractionDigits:2})}function current(sel){return events.find(function(e){return String(e.id)===sel.value})}function fillEvents(sel){sel.innerHTML=events.length?events.map(function(e){return'<option value="'+e.id+'">'+e.name.replace(/</g,'&lt;')+'</option>'}).join(''):'<option value="">No events available</option>'}function fillTickets(es,ts){var e=current(es),items=e?e.tickets:[];ts.innerHTML=items.length?items.map(function(t){return'<option value="'+t.id+'" data-price="'+t.price+'" data-stock="'+(t.available===null?'':t.available)+'">'+t.name.replace(/</g,'&lt;')+'</option>'}).join(''):'<option value="">No general-admission tickets</option>'}function draw(){var e=current(event),o=ticket.selectedOptions[0],n=Number(qty.value||1),p=Number(o&&o.dataset.price||0),expected=p*n,c=e?e.currency:'NGN';document.getElementById('door-summary-event').textContent=e?e.name:'Choose an event';document.getElementById('door-summary-ticket').textContent=o&&o.value?o.textContent:'—';document.getElementById('door-summary-qty').textContent=n;document.getElementById('door-summary-expected').textContent=money(expected,c);document.getElementById('door-total').textContent=money(amount.value||expected,c);stock.textContent=o&&o.dataset.stock!==''?o.dataset.stock+' remaining':'Unlimited inventory';if(!amount.dataset.edited)amount.value=expected.toFixed(2)}fillEvents(event);fillEvents(document.getElementById('import-event'));fillTickets(event,ticket);fillTickets(document.getElementById('import-event'),document.getElementById('import-ticket'));event.onchange=function(){fillTickets(event,ticket);amount.dataset.edited='';draw()};ticket.onchange=function(){amount.dataset.edited='';draw()};qty.oninput=function(){amount.dataset.edited='';draw()};amount.oninput=function(){amount.dataset.edited='1';draw()};document.getElementById('import-event').onchange=function(){fillTickets(this,document.getElementById('import-ticket'))};document.querySelectorAll('[name=payment_method]').forEach(function(r){r.onchange=function(){var free=this.value==='complimentary',w=document.getElementById('door-amount-wrap');w.hidden=free;amount.required=!free;if(free){amount.value='0';amount.dataset.edited='1'}else{amount.dataset.edited='';draw()}}});draw()})();
</script>
@endsection
