@component('mail::message')
# Your attendee allocation is ready

Hello {{ $sponsorName }},

The organizer of **{{ $eventTitle }}** has reserved {{ $capacity }} attendee {{ $capacity === 1 ? 'place' : 'places' }} for your organization.

Use the secure portal below to invite attendees. Each attendee receives a private link and completes their own registration and consent.

@component('mail::button', ['url' => $accessUrl])
Manage attendee allocation
@endcomponent

This private link expires on {{ $expiresAtLabel }}. Do not forward it outside the people authorized to manage your allocation.

Thanks,  
{{ config('app.name') }}
@endcomponent
