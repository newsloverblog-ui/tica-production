<?php

namespace App\Services;

use App\Models\DataExportSchedule;
use Carbon\CarbonImmutable;
use DateTimeZone;
use DomainException;
use Illuminate\Support\Facades\DB;

class DataExportScheduleService
{
    public const FREQUENCIES = ['daily', 'weekly', 'monthly'];

    public function createBookingSchedule(
        string $actorType,
        int $actorId,
        ?int $organizerId,
        int $languageId,
        array $filters,
        string $format,
        string $frequency,
        string $timezone,
        string $startsAt,
        int $dateWindowDays
    ): DataExportSchedule {
        if (!in_array($actorType, ['admin', 'organizer'], true) || $actorId < 1) {
            throw new DomainException('The schedule owner is invalid.');
        }
        if ($actorType === 'organizer' && $organizerId !== $actorId) {
            throw new DomainException('Organizer schedules must be scoped to their own account.');
        }
        if (!in_array($format, DataExportService::FORMATS, true)) {
            throw new DomainException('The selected export format is not supported.');
        }
        if (!in_array($frequency, self::FREQUENCIES, true)) {
            throw new DomainException('The selected export frequency is invalid.');
        }
        if ($dateWindowDays < 1 || $dateWindowDays > 365) {
            throw new DomainException('The rolling export window must be between 1 and 365 days.');
        }
        try {
            new DateTimeZone($timezone);
            $nextRun = CarbonImmutable::parse($startsAt, $timezone)
                ->setTimezone((string) config('app.timezone', 'UTC'));
        } catch (\Throwable) {
            throw new DomainException('The schedule start time or timezone is invalid.');
        }
        if ($nextRun->lessThan(now()->subMinute()) || $nextRun->greaterThan(now()->addYear())) {
            throw new DomainException('The first run must be within the next year.');
        }

        $schedule = DataExportSchedule::create([
            'requested_by_type' => $actorType,
            'requested_by_id' => $actorId,
            'organizer_id' => $organizerId,
            'export_type' => 'bookings',
            'format' => $format,
            'filters' => $this->sanitizeFilters($filters),
            'language_id' => $languageId,
            'frequency' => $frequency,
            'timezone' => $timezone,
            'date_window_days' => $dateWindowDays,
            'active' => true,
            'next_run_at' => $nextRun,
        ]);

        AuditLogger::record('data_export_schedule.created', $schedule, [], [
            'export_type' => 'bookings',
            'format' => $format,
            'frequency' => $frequency,
            'timezone' => $timezone,
            'date_window_days' => $dateWindowDays,
            'next_run_at' => $nextRun->toIso8601String(),
        ]);

        return $schedule;
    }

    public function runDue(int $limit = 100): array
    {
        $ids = DataExportSchedule::query()
            ->where('active', true)
            ->where('next_run_at', '<=', now())
            ->orderBy('next_run_at')
            ->limit(max(1, min($limit, 500)))
            ->pluck('id');
        $result = ['queued' => 0, 'failed' => 0];

        foreach ($ids as $id) {
            try {
                $ran = DB::transaction(function () use ($id): bool {
                    $schedule = DataExportSchedule::query()->lockForUpdate()->find($id);
                    if (!$schedule || !$schedule->active || $schedule->next_run_at->isFuture()) {
                        return false;
                    }

                    $localNow = CarbonImmutable::now($schedule->timezone);
                    $filters = (array) $schedule->filters;
                    $filters['to_date'] = $localNow->toDateString();
                    $filters['from_date'] = $localNow
                        ->subDays(max(1, $schedule->date_window_days) - 1)
                        ->toDateString();

                    $export = app(DataExportService::class)->queueBookings(
                        $schedule->requested_by_type,
                        (int) $schedule->requested_by_id,
                        $schedule->organizer_id ? (int) $schedule->organizer_id : null,
                        (int) $schedule->language_id,
                        $filters,
                        $schedule->format,
                        (int) $schedule->id
                    );
                    $schedule->forceFill([
                        'last_run_at' => now(),
                        'last_export_id' => $export->id,
                        'next_run_at' => $this->nextOccurrence($schedule),
                        'consecutive_failures' => 0,
                        'last_error' => null,
                    ])->save();
                    AuditLogger::record('data_export_schedule.ran', $schedule, [], [
                        'export_id' => $export->id,
                        'next_run_at' => $schedule->next_run_at->toIso8601String(),
                    ]);

                    return true;
                }, 3);
                if ($ran) {
                    $result['queued']++;
                }
            } catch (\Throwable $exception) {
                $result['failed']++;
                $this->recordFailure((int) $id, $exception);
                report($exception);
            }
        }

        return $result;
    }

    public function setActive(DataExportSchedule $schedule, bool $active): void
    {
        $before = ['active' => $schedule->active];
        $schedule->forceFill(['active' => $active])->save();
        AuditLogger::record('data_export_schedule.status_changed', $schedule, $before, ['active' => $active]);
    }

    private function recordFailure(int $id, \Throwable $exception): void
    {
        DB::transaction(function () use ($id, $exception): void {
            $schedule = DataExportSchedule::query()->lockForUpdate()->find($id);
            if (!$schedule || !$schedule->active) {
                return;
            }
            $schedule->forceFill([
                'last_run_at' => now(),
                'next_run_at' => $this->nextOccurrence($schedule),
                'consecutive_failures' => min(65535, $schedule->consecutive_failures + 1),
                'last_error' => mb_substr($exception->getMessage(), 0, 1000),
            ])->save();
            AuditLogger::record('data_export_schedule.failed', $schedule, [], [
                'consecutive_failures' => $schedule->consecutive_failures,
                'next_run_at' => $schedule->next_run_at->toIso8601String(),
            ]);
        });
    }

    private function nextOccurrence(DataExportSchedule $schedule): CarbonImmutable
    {
        $next = CarbonImmutable::instance($schedule->next_run_at)->setTimezone($schedule->timezone);
        $now = CarbonImmutable::now($schedule->timezone);
        do {
            $next = match ($schedule->frequency) {
                'daily' => $next->addDay(),
                'weekly' => $next->addWeek(),
                'monthly' => $next->addMonthNoOverflow(),
                default => throw new DomainException('The export schedule frequency is invalid.'),
            };
        } while ($next->lessThanOrEqualTo($now));

        return $next->setTimezone((string) config('app.timezone', 'UTC'));
    }

    private function sanitizeFilters(array $filters): array
    {
        $clean = [];
        foreach (['booking_id', 'event_title', 'status', 'payment_status', 'payment_method'] as $key) {
            $value = trim(strip_tags((string) ($filters[$key] ?? '')));
            if ($value !== '') {
                $clean[$key] = mb_substr($value, 0, $key === 'event_title' ? 191 : 100);
            }
        }

        return $clean;
    }
}
