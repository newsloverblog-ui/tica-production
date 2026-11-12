@component('mail::message')
# A ticket is waiting for you

Hello {{ $recipientName }},

A Tica customer has invited you to take ownership of an admission for **{{ $eventTitle }}**.

Sign in with this email address, or create and verify a Tica account with it, before accepting. The existing ticket remains with its current holder until you accept, and its old QR code will be invalidated at that moment.

@component('mail::button', ['url' => $invitationUrl])
Review ticket transfer
@endcomponent

This single-use invitation expires on {{ $expiresAt }}. If you were not expecting it, you can safely ignore this email.

Thanks,<br>
{{ config('app.name', 'Tica') }}
@endcomponent
