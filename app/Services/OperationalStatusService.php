<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Event;
use App\Models\Organizer;
use App\Support\LifecycleMailCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class OperationalStatusService
{
    public function setCustomerEmailVerification(int $customerId, int $verified): bool
    {
        $this->validateStatus($verified);

        return DB::transaction(function () use ($customerId, $verified) {
            $customer = Customer::whereKey($customerId)->lockForUpdate()->firstOrFail();
            $wasVerified = $customer->email_verified_at !== null;
            $isVerified = $verified === 1;
            if ($wasVerified === $isVerified) {
                return false;
            }

            $customer->email_verified_at = $isVerified ? now() : null;
            $customer->save();
            AuditLogger::record(
                'customer.email_verification_changed',
                $customer,
                ['verified' => $wasVerified],
                ['verified' => $isVerified]
            );

            return true;
        }, 3);
    }

    public function setOrganizerEmailVerification(int $organizerId, int $verified): bool
    {
        $this->validateStatus($verified);

        return DB::transaction(function () use ($organizerId, $verified) {
            $organizer = Organizer::whereKey($organizerId)->lockForUpdate()->firstOrFail();
            $wasVerified = $organizer->email_verified_at !== null;
            $isVerified = $verified === 1;
            if ($wasVerified === $isVerified) {
                return false;
            }

            $organizer->email_verified_at = $isVerified ? now() : null;
            $organizer->save();
            AuditLogger::record(
                'organizer.email_verification_changed',
                $organizer,
                ['verified' => $wasVerified],
                ['verified' => $isVerified]
            );

            return true;
        }, 3);
    }

    public function setCustomerStatus(int $customerId, int $status): bool
    {
        $this->validateStatus($status);

        return DB::transaction(function () use ($customerId, $status) {
            $customer = Customer::whereKey($customerId)->lockForUpdate()->firstOrFail();
            if ((int) $customer->status === $status) {
                return false;
            }

            $before = (int) $customer->status;
            $customer->status = $status;
            $customer->save();
            AuditLogger::record('customer.status_changed', $customer, ['status' => $before], ['status' => $status]);

            return true;
        }, 3);
    }

    public function setOrganizerStatus(int $organizerId, int $status): bool
    {
        $this->validateStatus($status);

        return DB::transaction(function () use ($organizerId, $status) {
            $organizer = Organizer::whereKey($organizerId)->lockForUpdate()->firstOrFail();
            $activeEvents = collect();
            if ($status === 0) {
                $activeEvents = Event::query()
                    ->where('organizer_id', $organizer->id)
                    ->where('status', '!=', 0)
                    ->lockForUpdate()
                    ->get();
                foreach ($activeEvents as $event) {
                    $event->status = 0;
                    $event->save();
                }
            }

            if ((int) $organizer->status === $status && $activeEvents->isEmpty()) {
                return false;
            }

            $before = (int) $organizer->status;
            $organizer->status = $status;
            $organizer->save();
            $scannerSessionsInvalidated = $status === 0
                ? app(ScannerStaffService::class)->invalidateOrganizerSessions($organizer->id)
                : 0;
            AuditLogger::record('organizer.status_changed', $organizer, ['status' => $before], ['status' => $status], [
                'events_deactivated' => $activeEvents->count(),
                'scanner_sessions_invalidated' => $scannerSessionsInvalidated,
            ]);

            return true;
        }, 3);
    }

    public function setEventStatus(int $eventId, int $status, ?int $organizerId = null): bool
    {
        $this->validateStatus($status);

        return DB::transaction(function () use ($eventId, $status, $organizerId) {
            $event = Event::whereKey($eventId)->lockForUpdate()->firstOrFail();
            if ($organizerId !== null && (int) $event->organizer_id !== $organizerId) {
                throw ValidationException::withMessages([
                    'status' => ['This event does not belong to the authenticated organizer.'],
                ]);
            }
            if ($status === 1 && ($event->lifecycle_status ?: 'scheduled') !== 'scheduled') {
                throw ValidationException::withMessages([
                    'status' => ['A cancellation-pending or cancelled event cannot be reactivated.'],
                ]);
            }
            if ((int) $event->status === $status) {
                return false;
            }

            $before = (int) $event->status;
            $event->status = $status;
            $event->save();
            AuditLogger::record('event.status_changed', $event, ['status' => $before], ['status' => $status]);

            if ($status === 1 && Schema::hasTable('application_mail_deliveries')) {
                $eventId = (int) $event->id;
                DB::afterCommit(function () use ($eventId): void {
                    $published = Event::with(['organizer', 'information'])->find($eventId);
                    if (!$published || !$published->organizer) return;

                    $title = (string) ($published->information->title ?? 'Your event');
                    $slug = (string) ($published->information->slug ?? 'event');
                    app(LifecycleMailService::class)->queueSafely(
                        LifecycleMailCatalog::ORGANIZER_EVENT_PUBLISHED,
                        $published->organizer->email,
                        [
                            'event_name' => $title,
                            'event_url' => route('event.details', [$slug, $published->id]),
                            'share_url' => route('organizer.event_management.edit_event', $published->id),
                        ],
                        $published
                    );
                });
            }

            return true;
        }, 3);
    }

    public function setEventFeatured(int $eventId, string $featured, ?int $organizerId = null): bool
    {
        if (!in_array($featured, ['yes', 'no'], true)) {
            throw ValidationException::withMessages([
                'is_featured' => ['Featured status must be yes or no.'],
            ]);
        }

        return DB::transaction(function () use ($eventId, $featured, $organizerId) {
            $event = Event::whereKey($eventId)->lockForUpdate()->firstOrFail();
            if ($organizerId !== null && (int) $event->organizer_id !== $organizerId) {
                throw ValidationException::withMessages([
                    'is_featured' => ['This event does not belong to the authenticated organizer.'],
                ]);
            }
            if ((string) $event->is_featured === $featured) {
                return false;
            }

            $before = (string) $event->is_featured;
            $event->is_featured = $featured;
            $event->save();
            AuditLogger::record(
                'event.featured_status_changed',
                $event,
                ['is_featured' => $before],
                ['is_featured' => $featured]
            );

            return true;
        }, 3);
    }

    private function validateStatus(int $status): void
    {
        if (!in_array($status, [0, 1], true)) {
            throw ValidationException::withMessages(['status' => ['Status must be active or inactive.']]);
        }
    }
}
