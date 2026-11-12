@component('mail::message')
# {{ $statusLabel }}

Hello {{ $registrantName }},

{{ $statusMessage }}

**Event:** {{ $eventTitle }}  
**Registration reference:** {{ $registrationReference }}

@if ($reviewNote)
**Organizer note:**  
{{ $reviewNote }}
@endif

@if ($answerSummary !== [])
## Submitted details

@foreach ($answerSummary as $section)
### {{ $section['heading'] }}

@foreach ($section['answers'] as $answer)
**{{ $answer['label'] }}:** {{ $answer['value'] }}  
@endforeach

@endforeach
@if ($omittedAnswerCount > 0)
{{ $omittedAnswerCount }} additional answers were omitted from this email to keep it deliverable. The organizer retains the complete registration record.
@endif
@endif

Please keep this email and your registration reference for your records. If you need help, reply to this message and include the reference.

Thanks,  
{{ config('app.name') }}
@endcomponent
