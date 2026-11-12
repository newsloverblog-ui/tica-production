@component('mail::message')
# Event cancelled

Hello {{ $customerName }},

We are sorry to let you know that **{{ $eventTitle }}** has been cancelled.

{{ $customerMessage ?: $reason }}

**Booking reference:** {{ $bookingReference }}

{{ $refundMessage }}

Please keep this email for your records. If you need help, reply to this message and include your booking reference.

Thanks,  
{{ config('app.name') }}
@endcomponent
