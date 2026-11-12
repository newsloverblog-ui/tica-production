<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Event\Booking;
use App\Support\LifecycleMailCatalog;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Throwable;

class EventLifecycleNotificationService
{
    public function start(Event $event): ?CarbonInterface
    {
        try {
            if (($event->date_type ?: 'single') === 'multiple') {
                $value = $event->dates()->orderBy('start_date_time')->value('start_date_time');
                return $value ? Carbon::parse($value) : null;
            }

            return $event->start_date
                ? Carbon::parse(trim($event->start_date . ' ' . ($event->start_time ?: '00:00:00')))
                : null;
        } catch (Throwable) {
            return null;
        }
    }

    public function notifyDateChanged(Event $event, ?CarbonInterface $oldStart): int
    {
        $event->refresh()->loadMissing('information');
        $newStart = $this->start($event);
        if (!$oldStart || !$newStart || $oldStart->equalTo($newStart)) return 0;

        $eventName = $event->information->title ?? 'Your event';
        $slug = $event->information->slug ?? 'event';
        $dedupe = $oldStart->toIso8601String() . '>' . $newStart->toIso8601String();
        $queued = 0;

        Booking::query()
            ->where('event_id', $event->id)
            ->whereIn('paymentStatus', ['completed', 'free', '1'])
            ->whereNotNull('email')
            ->orderBy('id')
            ->chunkById(100, function ($bookings) use ($event, $eventName, $slug, $oldStart, $newStart, $dedupe, &$queued): void {
                foreach ($bookings as $booking) {
                    $delivery = app(LifecycleMailService::class)->queueSafely(
                        LifecycleMailCatalog::BUYER_EVENT_DATE_CHANGED,
                        $booking->email,
                        [
                            'event_name' => $eventName,
                            'old_date' => $oldStart->format('D, j M Y · g:i A'),
                            'new_date' => $newStart->format('D, j M Y · g:i A'),
                            'refund_deadline' => $newStart->copy()->subDay()->format('D, j M Y · g:i A'),
                            'event_url' => route('event.details', [$slug, $event->id]),
                            'refund_url' => url('/customer/booking/details/' . $booking->id),
                        ],
                        $booking,
                        [],
                        $dedupe
                    );
                    if ($delivery) $queued++;
                }
            });

        return $queued;
    }
}
