<?php

namespace App\Services;

use App\Models\Event;
use App\Models\ScannerDevice;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;

class ScannerAdmissionPolicy
{
    public function assignedDevice(Request $request, int $eventId): ScannerDevice
    {
        $enrolled = $request->attributes->get('scanner_device');
        abort_unless($enrolled && (int) $enrolled->event_id === $eventId && $eventId > 0, 403, 'This gate is not assigned to the selected event.');
        return $enrolled;
    }

    public function allowsTime(Event $event, ?string $bookingDate, CarbonInterface $at): bool
    {
        if ((int) $event->status !== 1 || $event->cancelled_at || $event->lifecycle_status === 'cancelled') {
            return false;
        }
        $timezone = $event->timezone ?: config('app.timezone');
        if ($event->date_type === 'multiple') {
            return $bookingDate && $event->dates()->get()->contains(fn ($date) => $this->contains(
                $date->start_date, $date->start_time, $date->end_date, $date->end_time, $timezone, $at, $bookingDate
            ));
        }
        return $this->contains($event->start_date, $event->start_time, $event->end_date, $event->end_time, $timezone, $at);
    }

    private function contains($startDate, $startTime, $endDate, $endTime, string $timezone, CarbonInterface $at, ?string $bookingDate = null): bool
    {
        if (!$startDate || !$startTime || !$endDate || !$endTime) {
            return false;
        }
        try {
            $start = CarbonImmutable::parse("$startDate $startTime", $timezone);
            $end = CarbonImmutable::parse("$endDate $endTime", $timezone);
            if ($bookingDate && !$start->equalTo(CarbonImmutable::parse($bookingDate, $timezone))) {
                return false;
            }
        } catch (\InvalidArgumentException) {
            return false;
        }
        return $end->greaterThan($start) && $at->gte($start->subMinutes((int) config('tickets.admission_early_minutes', 60))) && $at->lt($end);
    }
}
