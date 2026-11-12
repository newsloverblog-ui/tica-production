@component('mail::message')
# Complete your registration

Hello {{ $attendeeName }},

**{{ $sponsorName }}** has allocated you a place at **{{ $eventTitle }}**.

Use the secure link below to provide your own attendee information, review the privacy notice and consent to event administration. This allocation is not a paid ticket or proof of admission until the organizer confirms it.

@component('mail::button', ['url' => $registrationUrl])
Complete registration
@endcomponent

This private link expires on {{ $expiresAtLabel }} and is intended only for you. If you were not expecting it, ignore this message and contact the sponsor.

Thanks,  
{{ config('app.name') }}
@endcomponent
