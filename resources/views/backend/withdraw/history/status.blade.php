@php
  [$label, $tone] = match (true) {
    $review && $review['approved'] => [__('Approved'), 'info'],
    (int) $item->status === \App\Models\Withdraw::STATUS_HELD => [__('On hold'), 'warning'],
    (int) $item->status === \App\Models\Withdraw::STATUS_PROCESSING => [__('Processing'), 'info'],
    (int) $item->status === \App\Models\Withdraw::STATUS_PAID => [__('Paid'), 'success'],
    (int) $item->status === \App\Models\Withdraw::STATUS_REJECTED => [__('Rejected'), 'danger'],
    default => [__('Pending review'), 'warning'],
  };
@endphp
<span class="badge badge-{{ $tone }}">{{ $label }}</span>
@if ($review && $review['approved'])
  <small class="d-block">{{ __('Awaiting release') }}</small>
@elseif ($review && $review['blocker'])
  <small class="d-block">{{ __('Approval blocked') }}</small>
@endif
