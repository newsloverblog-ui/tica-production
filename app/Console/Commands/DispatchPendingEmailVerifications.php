<?php

namespace App\Console\Commands;

use App\Models\CustomerEmailVerificationDelivery;
use App\Models\OrganizerEmailVerificationDelivery;
use App\Services\CustomerEmailVerificationService;
use App\Services\IncidentAlertService;
use App\Services\OrganizerEmailVerificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class DispatchPendingEmailVerifications extends Command
{
    protected $signature = 'accounts:dispatch-verifications {--limit=100} {--stale-minutes=10}';
    protected $description = 'Recover pending organizer and customer email-verification queue handoffs';

    public function handle(
        OrganizerEmailVerificationService $organizers,
        CustomerEmailVerificationService $customers,
        IncidentAlertService $alerts
    ): int {
        foreach ([
            'organizer_email_verification_deliveries',
            'customer_email_verification_deliveries',
        ] as $table) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'dispatch_attempts')) {
                $this->error('The email verification dispatch migration is not applied.');
                return self::FAILURE;
            }
        }

        $limit = max(1, min(500, (int) $this->option('limit')));
        $staleMinutes = max(5, (int) $this->option('stale-minutes'));
        $attempted = 0;
        $failed = 0;
        $alerted = 0;
        $expired = 0;

        $contracts = [
            [OrganizerEmailVerificationDelivery::class, fn (int $id): bool => $organizers->dispatchDelivery($id), 'organizer'],
            [CustomerEmailVerificationDelivery::class, fn (int $id): bool => $customers->dispatchDelivery($id), 'customer'],
        ];

        foreach ($contracts as [$modelClass, $dispatch, $audience]) {
            $expired += $modelClass::query()
                ->where('status', 'pending')
                ->where('expires_at', '<=', now())
                ->update(['status' => 'suppressed', 'processing_at' => null, 'updated_at' => now()]);

            $deliveries = $modelClass::query()
                ->where('status', 'pending')
                ->where('expires_at', '>', now())
                ->orderBy('id')
                ->limit($limit)
                ->get();
            foreach ($deliveries as $delivery) {
                $attempted++;
                if (!$dispatch((int) $delivery->id)) {
                    $failed++;
                }
                $delivery->refresh();
                if ($delivery->status === 'pending'
                    && $delivery->created_at->lt(now()->subMinutes($staleMinutes))
                    && $delivery->alerted_at === null) {
                    $delivery->forceFill(['alerted_at' => now()])->save();
                    $alerts->alert(
                        $audience.'-email-verification-dispatch-pending:'.$delivery->id,
                        'A committed '.$audience.' email verification did not start on the queue.',
                        ['delivery_id' => $delivery->id, 'dispatch_attempts' => $delivery->dispatch_attempts],
                        'critical'
                    );
                    $alerted++;
                }
            }
        }

        $this->line(sprintf(
            'Email verification dispatch attempted: %d | failed: %d | alerted: %d | expired: %d',
            $attempted,
            $failed,
            $alerted,
            $expired
        ));

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
