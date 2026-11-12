@component('mail::message')
# {{ $audience === 'finance' ? 'Payout requires attention' : ($status === 'queued' ? 'Payout initiated' : ($status === 'success' ? 'Payout completed' : 'Payout update')) }}

@if ($audience === 'organizer')
@if ($status === 'queued')
Your payout request has passed finance review and is being submitted to the payment provider. We will send another update when the provider confirms the final outcome.
@elseif ($status === 'success')
Your payout has been confirmed by the payment provider.
@else
Your payout could not be completed and has been placed on hold. Tica finance will review it before any further action.
@endif
@else
A controlled organizer payout is in **{{ str_replace('_', ' ', $status) }}** state and requires finance review.
@endif

| Detail | Value |
| :-- | :-- |
| Payout reference | {{ $reference }} |
| Withdrawal | {{ $withdrawalId }} |
| Amount | {{ $currency }} {{ $amount }} |
| Status | {{ str_replace('_', ' ', ucfirst($status)) }} |

@if ($audience === 'finance' && $error)
**Recorded issue:** {{ $error }}
@endif

@if ($audience === 'organizer')
If you need help, contact Tica support and include the payout reference above.
@else
Review the payout in the finance withdrawal queue. Do not submit a new transfer until the existing reference has been verified with Paystack.
@endif

Thanks,<br>
{{ config('app.name', 'Tica') }}
@endcomponent
