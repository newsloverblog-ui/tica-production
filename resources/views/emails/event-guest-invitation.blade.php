@component('mail::message')
# You’re invited

Hello {{ $guestName }},

An organizer has invited you to **{{ $eventTitle }}** for a party of {{ $partySize }}.

Use the secure link below to accept or decline. Accepting records your RSVP and allows Tica to send essential updates about this event. Marketing messages are optional and require a separate choice.

@component('mail::button', ['url' => $rsvpUrl])
Respond to invitation
@endcomponent

This private link expires on {{ $expiresAtLabel }}. If you were not expecting this invitation, you can decline it or ignore the message. Your place is not a paid ticket and cannot be transferred.

Thanks,  
{{ config('app.name') }}
@endcomponent
