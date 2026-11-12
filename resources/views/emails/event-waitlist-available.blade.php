@component('mail::message')
# Tickets are available

Hello {{ $recipientName }},

**{{ $ticketLabel }}** tickets for **{{ $eventTitle }}** are available again.

This notice does not reserve a ticket. Availability can change while other customers complete checkout, so purchase as soon as you are ready.

@component('mail::button', ['url' => $eventUrl])
View tickets
@endcomponent

Your notification window ends {{ $expiresAtLabel }}. If tickets sell out again, you may rejoin the waiting list from the event page.

If you no longer want availability emails for this ticket, [leave this waiting list]({{ $unsubscribeUrl }}).

Thanks,  
{{ config('app.name') }}
@endcomponent
