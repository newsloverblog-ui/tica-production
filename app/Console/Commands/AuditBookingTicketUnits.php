<?php

namespace App\Console\Commands;

use App\Services\TicketUnitIntegrityService;
use Illuminate\Console\Command;

class AuditBookingTicketUnits extends Command
{
    protected $signature = 'tickets:audit-booking-units
        {--json : Emit machine-readable JSON}
        {--sample=25 : Maximum affected booking references to display (0-100)}';

    protected $description = 'Read-only audit of booking admission-unit completeness and immutable identity';

    public function handle(TicketUnitIntegrityService $integrity): int
    {
        $sampleLimit = filter_var($this->option('sample'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 0, 'max_range' => 100],
        ]);
        if ($sampleLimit === false) {
            $this->error('--sample must be an integer from 0 through 100.');

            return self::INVALID;
        }

        $report = $integrity->inspect($sampleLimit);

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->info(sprintf(
                'Audited %d bookings; %d require action.',
                $report['total_bookings'],
                $report['affected_bookings']
            ));

            if ($report['issue_counts'] !== []) {
                $this->table(
                    ['Issue', 'Affected bookings'],
                    collect($report['issue_counts'])
                        ->map(static fn (int $count, string $issue): array => [$issue, $count])
                        ->values()
                        ->all()
                );
            }

            if ($report['samples'] !== []) {
                $this->table(
                    ['Database ID', 'Booking reference', 'Issues'],
                    array_map(static fn (array $sample): array => [
                        $sample['id'],
                        $sample['booking_id'],
                        implode(', ', $sample['issues']),
                    ], $report['samples'])
                );
            }
        }

        return $report['affected_bookings'] === 0 ? self::SUCCESS : self::FAILURE;
    }
}
