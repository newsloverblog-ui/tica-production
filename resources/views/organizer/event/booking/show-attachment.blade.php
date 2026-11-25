@if (filled($booking->attachmentFile))
<div class="modal fade" id="attachmentModal-{{ $booking->id }}" tabindex="-1" role="dialog" aria-labelledby="attachmentTitle-{{ $booking->id }}" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="attachmentTitle-{{ $booking->id }}">
          {{ __('Attachment') }}
        </h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>

      <div class="modal-body">
        <img src="{{ route('organizer.event_booking.attachment', $booking->id) }}" alt="{{ __('Booking attachment') }}" loading="lazy" class="img-fluid">
      </div>

      <div class="modal-footer"></div>
    </div>
  </div>
</div>
@endif
