<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Customer;
use App\Models\Organizer;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OperationalArchiveService
{
    public function archiveCustomers(array $customerIds): int
    {
        $ids = $this->normalizeIds($customerIds, 'customers');

        return DB::transaction(function () use ($ids) {
            $customers = Customer::query()
                ->whereIn('id', $ids)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($customers->count() !== count($ids)) {
                throw (new ModelNotFoundException())->setModel(Customer::class, $ids);
            }

            $archived = 0;
            foreach ($ids as $id) {
                /** @var Customer $customer */
                $customer = $customers->get($id);
                if ((int) $customer->status === 0) {
                    continue;
                }

                $before = ['status' => (int) $customer->status];
                $customer->status = 0;
                $customer->save();
                $archived++;

                AuditLogger::record('customer.archived', $customer, $before, ['status' => 0], [
                    'booking_and_order_history_preserved' => true,
                ]);
            }

            return $archived;
        }, 3);
    }

    public function archiveEvents(array $eventIds, ?int $organizerId = null): int
    {
        $ids = $this->normalizeIds($eventIds, 'events');

        return DB::transaction(function () use ($ids, $organizerId) {
            $events = Event::query()
                ->whereIn('id', $ids)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($events->count() !== count($ids)) {
                throw (new ModelNotFoundException())->setModel(Event::class, $ids);
            }

            $archived = 0;
            foreach ($ids as $id) {
                /** @var Event $event */
                $event = $events->get($id);
                if ($organizerId !== null && (int) $event->organizer_id !== $organizerId) {
                    throw ValidationException::withMessages([
                        'events' => ['One or more selected events do not belong to this organizer.'],
                    ]);
                }

                if ((int) $event->status === 0) {
                    continue;
                }

                $before = ['status' => (int) $event->status];
                $event->status = 0;
                $event->save();
                $archived++;

                AuditLogger::record('event.archived', $event, $before, ['status' => 0], [
                    'records_preserved' => true,
                ]);
            }

            return $archived;
        }, 3);
    }

    public function archiveOrganizers(array $organizerIds): int
    {
        $ids = $this->normalizeIds($organizerIds, 'organizers');

        return DB::transaction(function () use ($ids) {
            $organizers = Organizer::query()
                ->whereIn('id', $ids)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($organizers->count() !== count($ids)) {
                throw (new ModelNotFoundException())->setModel(Organizer::class, $ids);
            }

            $archived = 0;
            foreach ($ids as $id) {
                /** @var Organizer $organizer */
                $organizer = $organizers->get($id);
                $activeEvents = Event::query()
                    ->where('organizer_id', $organizer->id)
                    ->where('status', '!=', 0)
                    ->lockForUpdate()
                    ->get();

                foreach ($activeEvents as $event) {
                    $event->status = 0;
                    $event->save();
                }

                if ((int) $organizer->status === 0 && $activeEvents->isEmpty()) {
                    continue;
                }

                $before = ['status' => (int) $organizer->status];
                $organizer->status = 0;
                $organizer->save();
                $archived++;
                $scannerSessionsInvalidated = app(ScannerStaffService::class)->invalidateOrganizerSessions($organizer->id);

                AuditLogger::record('organizer.archived', $organizer, $before, ['status' => 0], [
                    'events_archived' => $activeEvents->count(),
                    'financial_records_preserved' => true,
                    'scanner_sessions_invalidated' => $scannerSessionsInvalidated,
                ]);
            }

            return $archived;
        }, 3);
    }

    private function normalizeIds(array $ids, string $field): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn ($id) => $id > 0)));
        if ($ids === []) {
            throw ValidationException::withMessages([$field => ['Select at least one record.']]);
        }

        return $ids;
    }
}
