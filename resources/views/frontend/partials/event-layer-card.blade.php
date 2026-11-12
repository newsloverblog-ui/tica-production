@php
  $eventThumbnailPath = 'assets/admin/img/event/thumbnail/' . $event->thumbnail;
  $eventThumbnail = !empty($event->thumbnail)
      ? asset($eventThumbnailPath)
      : asset('assets/front/images/home/city-pulse-hero.png');

  if ($event->date_type == 'multiple') {
      $latestDate = eventLatestDates($event->id);
      $eventDateValue = optional($latestDate)->start_date;
  } else {
      $eventDateValue = $event->start_date;
  }

  $eventDate = $eventDateValue
      ? \Carbon\Carbon::parse($eventDateValue)->timezone($websiteInfo->timezone)
      : null;
  $eventTime = $event->start_time
      ? \Carbon\Carbon::parse($event->start_time)->timezone($websiteInfo->timezone)
      : null;

  $eventStart = null;
  if ($eventDateValue) {
      $eventStart = \Carbon\Carbon::parse(
          $eventDateValue . ' ' . ($event->start_time ?: '00:00:00'),
          $websiteInfo->timezone
      );
  }

  $eventTickets = $ticketInventory ?? collect();
  $ticketRemaining = 0;
  $hasLimitedInventory = false;
  $hasUnlimitedInventory = false;

  foreach ($eventTickets as $ticket) {
      if ($ticket->pricing_type === 'variation') {
          foreach ((json_decode($ticket->variations, true) ?: []) as $variation) {
              if (($variation['ticket_available_type'] ?? null) === 'limited') {
                  $hasLimitedInventory = true;
                  $ticketRemaining += max(0, (int) ($variation['ticket_available'] ?? 0));
              } else {
                  $hasUnlimitedInventory = true;
              }
          }
      } elseif ($ticket->ticket_available_type === 'limited') {
          $hasLimitedInventory = true;
          $ticketRemaining += max(0, (int) $ticket->ticket_available);
      } else {
          $hasUnlimitedInventory = true;
      }
  }

  $showTicketInventory = $hasLimitedInventory && !$hasUnlimitedInventory;
@endphp

<article class="layer-event-card {{ $variant === 'dark' ? 'layer-event-card--dark' : 'layer-event-card--light' }}">
  <a class="layer-event-poster" href="{{ route('event.details', [$event->slug, $event->id]) }}">
    <img src="{{ $eventThumbnail }}" alt="{{ $event->title }}">
    @if ($eventDate)
      <span class="layer-event-date">{{ $eventDate->translatedFormat('d M Y') }}</span>
    @endif
  </a>
  <div class="layer-event-copy">
    <h3>
      <a href="{{ route('event.details', [$event->slug, $event->id]) }}">{{ $event->title }}</a>
    </h3>
    <p class="layer-event-time">
      {{ $eventDate ? $eventDate->translatedFormat('D, d M Y') : __('Date coming soon') }}
      @if ($eventTime)
        <span aria-hidden="true">•</span> {{ $eventTime->translatedFormat('h:i A') }}
      @endif
    </p>
    <p class="layer-event-location">
      <i class="fas fa-map-marker-alt" aria-hidden="true"></i>
      <span>{{ $event->event_type === 'online' ? __('Online') : collect([$event->city, $event->country])->filter()->implode(', ') }}</span>
    </p>
    <div class="layer-event-action-row">
      <p class="layer-event-urgency {{ $showTicketInventory && $ticketRemaining <= 10 ? 'is-low-stock' : '' }}">
        @if ($showTicketInventory)
          <i class="fas fa-ticket-alt" aria-hidden="true"></i>
          <span>{{ $ticketRemaining === 1 ? __('1 ticket left') : __(':count tickets left', ['count' => $ticketRemaining]) }}</span>
        @elseif ($eventStart)
          <i class="far fa-clock" aria-hidden="true"></i>
          <span data-event-countdown data-event-start="{{ $eventStart->toIso8601String() }}">{{ __('Starts soon') }}</span>
        @else
          <i class="fas fa-ticket-alt" aria-hidden="true"></i>
          <span>{{ __('Tickets available') }}</span>
        @endif
      </p>
      <a class="layer-event-ticket-button" href="{{ route('event.details', [$event->slug, $event->id]) }}"
        aria-label="{{ __('Get tickets for :event', ['event' => $event->title]) }}">
        {{ __('Get ticket') }} <i class="fas fa-arrow-right" aria-hidden="true"></i>
      </a>
    </div>
  </div>
</article>
