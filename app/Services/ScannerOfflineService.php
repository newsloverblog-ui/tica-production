<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Event\BookingTicket;
use App\Models\ScannerDevice;
use App\Models\ScannerOfflineManifest;
use App\Models\ScannerOfflineManifestItem;
use App\Models\ScannerOfflineSyncItem;
use App\Models\ScannerStaff;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ScannerOfflineService
{
    public function events(int $organizerId): array
    {
        return Event::query()
            ->with('information')
            ->where('organizer_id', $organizerId)
            ->where('status', 1)
            ->orderByRaw('CASE WHEN start_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('start_date')
            ->orderBy('id')
            ->get()
            ->map(function (Event $event) {
                return [
                    'id' => (int) $event->id,
                    'title' => optional($event->information)->title ?: 'Event #' . $event->id,
                    'start_date' => $event->start_date,
                ];
            })
            ->values()
            ->all();
    }

    public function createManifest(
        int $organizerId,
        int $eventId,
        ScannerDevice $device,
        ?ScannerStaff $staff = null
    ): array {
        $event = Event::query()
            ->with('information')
            ->whereKey($eventId)
            ->where('organizer_id', $organizerId)
            ->where('status', 1)
            ->firstOrFail();

        if ((int) $device->organizer_id !== $organizerId || $device->status !== 'active') {
            throw ValidationException::withMessages([
                'device' => 'The enrolled scanner is not active for this organizer.',
            ]);
        }

        $max = (int) config('tickets.offline_manifest_max_tickets', 25000);
        $tickets = BookingTicket::query()
            ->where('organizer_id', $organizerId)
            ->where('event_id', $eventId)
            ->where('status', 'issued')
            ->whereHas('booking', function ($query) {
                $query->whereIn('paymentStatus', ['completed', 'free', '1'])
                    ->where(function ($refund) {
                        $refund->whereNull('refund_status')->orWhere('refund_status', '!=', 'refunded');
                    });
            })
            ->orderBy('id')
            ->limit($max + 1)
            ->get(['id', 'public_id', 'event_id']);

        if ($tickets->count() > $max) {
            throw ValidationException::withMessages([
                'event_id' => "This event has more than {$max} eligible admissions. Contact support before enabling offline scanning.",
            ]);
        }

        $items = $tickets->map(function (BookingTicket $ticket) {
            return [
                'booking_ticket_id' => (int) $ticket->id,
                'payload_hash' => hash('sha256', signedTicketUnitPayload($ticket->public_id, $ticket->event_id)),
            ];
        })->values();
        $hashes = $items->pluck('payload_hash')->sort()->values();
        $snapshotHash = hash('sha256', $hashes->implode("\n"));
        $now = now();

        $manifest = DB::transaction(function () use (
            $organizerId, $eventId, $device, $staff, $items, $snapshotHash, $now
        ) {
            $manifest = ScannerOfflineManifest::create([
                'public_id' => (string) Str::uuid(),
                'organizer_id' => $organizerId,
                'event_id' => $eventId,
                'scanner_device_id' => $device->id,
                'scanner_staff_id' => optional($staff)->id,
                'status' => 'active',
                'snapshot_hash' => $snapshotHash,
                'ticket_count' => $items->count(),
                'generated_at' => $now,
                'expires_at' => $now->copy()->addHours((int) config('tickets.offline_manifest_hours', 12)),
            ]);

            foreach ($items->chunk(1000) as $chunk) {
                $createdAt = now();
                ScannerOfflineManifestItem::insert($chunk->map(function (array $item) use ($manifest, $createdAt) {
                    return $item + [
                        'scanner_offline_manifest_id' => $manifest->id,
                        'created_at' => $createdAt,
                        'updated_at' => $createdAt,
                    ];
                })->all());
            }

            return $manifest;
        });

        AuditLogger::record(
            'scanner_offline.manifest_created',
            $manifest,
            [],
            ['status' => 'active', 'ticket_count' => $manifest->ticket_count],
            [
                'event_id' => $eventId,
                'scanner_device_id' => $device->id,
                'scanner_device_public_id' => $device->public_id,
                'scanner_staff_id' => optional($staff)->id,
                'expires_at' => $manifest->expires_at->toIso8601String(),
                'snapshot_hash' => $snapshotHash,
            ]
        );

        return [
            'manifest' => $this->manifestPayload($manifest, optional($event->information)->title),
            'admissions' => $hashes->all(),
        ];
    }

    /** The callback must invoke the authoritative, row-locked online check-in path. */
    public function sync(
        int $organizerId,
        ScannerDevice $device,
        ?ScannerStaff $staff,
        string $manifestPublicId,
        array $scans,
        callable $checkIn
    ): array {
        $manifest = ScannerOfflineManifest::query()
            ->where('public_id', $manifestPublicId)
            ->where('organizer_id', $organizerId)
            ->where('scanner_device_id', $device->id)
            ->firstOrFail();

        if (!in_array($manifest->status, ['active', 'expired'], true) || $manifest->revoked_at) {
            throw ValidationException::withMessages(['manifest_id' => 'This offline manifest is no longer active.']);
        }
        if (now()->greaterThan($manifest->expires_at->copy()->addHours((int) config('tickets.offline_sync_grace_hours', 24)))) {
            throw ValidationException::withMessages(['manifest_id' => 'The synchronization grace period has ended.']);
        }

        $results = [];
        foreach ($scans as $scan) {
            $results[] = $this->syncOne($manifest, $organizerId, $device, $staff, $scan, $checkIn);
        }

        $manifest->forceFill(['last_synced_at' => now()])->save();
        $counts = collect($results)->countBy('result')->all();
        AuditLogger::record(
            'scanner_offline.batch_synced',
            $manifest,
            [],
            ['last_synced_at' => $manifest->last_synced_at],
            [
                'event_id' => $manifest->event_id,
                'scanner_device_id' => $device->id,
                'scanner_staff_id' => optional($staff)->id,
                'submitted' => count($scans),
                'results' => $counts,
            ]
        );

        return ['manifest_id' => $manifest->public_id, 'results' => $results, 'summary' => $counts];
    }

    private function syncOne(
        ScannerOfflineManifest $manifest,
        int $organizerId,
        ScannerDevice $device,
        ?ScannerStaff $staff,
        array $scan,
        callable $checkIn
    ): array {
        $payloadHash = hash('sha256', $scan['payload']);
        $scannedAt = Carbon::parse($scan['scanned_at']);
        $manifestItem = ScannerOfflineManifestItem::query()
            ->where('scanner_offline_manifest_id', $manifest->id)
            ->where('payload_hash', $payloadHash)
            ->first();
        try {
            return DB::transaction(function () use (
                $manifest, $organizerId, $device, $staff, $scan, $payloadHash,
                $scannedAt, $manifestItem, $checkIn
            ) {
                $existing = ScannerOfflineSyncItem::query()
                    ->where('scanner_device_id', $device->id)
                    ->where('client_scan_id', $scan['client_scan_id'])
                    ->lockForUpdate()
                    ->first();
                if ($existing && $existing->result !== 'processing') {
                    return $this->receiptPayload($existing, true);
                }
                if ($existing && $existing->updated_at && $existing->updated_at->greaterThan(now()->subMinutes(2))) {
                    return $this->receiptPayload($existing, true, true);
                }
                if ($existing) {
                    $existing->delete();
                }

                $receipt = ScannerOfflineSyncItem::create([
                    'client_scan_id' => $scan['client_scan_id'],
                    'scanner_offline_manifest_id' => $manifest->id,
                    'organizer_id' => $organizerId,
                    'scanner_device_id' => $device->id,
                    'scanner_staff_id' => optional($staff)->id,
                    'booking_ticket_id' => optional($manifestItem)->booking_ticket_id,
                    'payload_hash' => $payloadHash,
                    'result' => 'processing',
                    'client_scanned_at' => $scannedAt,
                    'received_at' => now(),
                ]);

                if ($scannedAt->lessThan($manifest->generated_at) || $scannedAt->greaterThan($manifest->expires_at)) {
                    return $this->reject($receipt, 'The scan occurred outside this manifest’s validity window.');
                }
                if (!$manifestItem) {
                    return $this->reject($receipt, 'This ticket was not eligible in the downloaded event manifest.');
                }

                $response = $checkIn($scan['payload']);
                $status = $response->getStatusCode();
                $body = $response instanceof JsonResponse
                    ? $response->getData(true)
                    : (json_decode($response->getContent(), true) ?: ['message' => 'Check-in response unavailable.']);
                $result = $status >= 200 && $status < 300
                    ? 'accepted'
                    : ($status === 409
                        ? (!empty($body['multi_gate_conflict']) ? 'conflict' : 'duplicate')
                        : 'rejected');
                $receipt->update(['result' => $result, 'http_status' => $status, 'response' => $body]);

                return $this->receiptPayload($receipt->fresh());
            });
        } catch (\Throwable $exception) {
            report($exception);
            return [
                'client_scan_id' => $scan['client_scan_id'],
                'result' => 'retry',
                'http_status' => 503,
                'response' => ['message' => 'Temporary synchronization failure. Keep this scan queued and retry.'],
                'replayed' => false,
            ];
        }
    }

    private function reject(ScannerOfflineSyncItem $receipt, string $message): array
    {
        $receipt->update([
            'result' => 'rejected',
            'http_status' => 422,
            'response' => ['alert_type' => 'error', 'message' => $message],
        ]);

        return $this->receiptPayload($receipt->fresh());
    }

    private function receiptPayload(ScannerOfflineSyncItem $receipt, bool $replayed = false, bool $processing = false): array
    {
        return [
            'client_scan_id' => $receipt->client_scan_id,
            'result' => $processing ? 'retry' : $receipt->result,
            'http_status' => $processing ? 202 : $receipt->http_status,
            'response' => $processing
                ? ['message' => 'This scan is already being synchronized. Retry shortly.']
                : ($receipt->response ?: []),
            'replayed' => $replayed,
        ];
    }

    private function manifestPayload(ScannerOfflineManifest $manifest, ?string $title = null): array
    {
        return [
            'id' => $manifest->public_id,
            'event_id' => (int) $manifest->event_id,
            'event_title' => $title ?: 'Event #' . $manifest->event_id,
            'ticket_count' => (int) $manifest->ticket_count,
            'snapshot_hash' => $manifest->snapshot_hash,
            'generated_at' => $manifest->generated_at->toIso8601String(),
            'expires_at' => $manifest->expires_at->toIso8601String(),
        ];
    }
}
