<?php

namespace App\Services;

use App\Models\Event\Booking;

class TicketUnitIntegrityService
{
    public function __construct(
        private readonly TicketIssuanceService $issuer,
    ) {
    }

    /**
     * Inspect every historical booking against its immutable admission-unit
     * contract. This is intentionally read-only: partial or contradictory
     * history must be reconciled from evidence, never guessed by a deploy.
     *
     * @return array{
     *   total_bookings: int,
     *   affected_bookings: int,
     *   issue_counts: array<string, int>,
     *   samples: array<int, array{id: int, booking_id: string, issues: array<int, string>}>
     * }
     */
    public function inspect(int $sampleLimit = 10): array
    {
        $report = [
            'total_bookings' => 0,
            'affected_bookings' => 0,
            'issue_counts' => [],
            'samples' => [],
        ];

        Booking::query()
            ->select([
                'id', 'booking_id', 'event_id', 'organizer_id', 'ticket_id',
                'variation', 'quantity', 'scan_status',
            ])
            ->with(['ticketUnits' => static fn ($query) => $query
                ->select([
                    'id', 'booking_id', 'event_id', 'organizer_id', 'ticket_id',
                    'sequence', 'variation_name',
                ])
                ->orderBy('sequence')])
            ->orderBy('id')
            ->chunkById(200, function ($bookings) use (&$report, $sampleLimit): void {
                foreach ($bookings as $booking) {
                    $report['total_bookings']++;
                    $issues = $this->issuer->integrityIssues($booking, $booking->ticketUnits);
                    if ($issues === []) {
                        continue;
                    }

                    $report['affected_bookings']++;
                    foreach ($issues as $issue) {
                        $report['issue_counts'][$issue] = ($report['issue_counts'][$issue] ?? 0) + 1;
                    }

                    if (count($report['samples']) < max(0, $sampleLimit)) {
                        $report['samples'][] = [
                            'id' => (int) $booking->id,
                            'booking_id' => (string) $booking->booking_id,
                            'issues' => $issues,
                        ];
                    }
                }
            }, 'id');

        ksort($report['issue_counts']);

        return $report;
    }
}
