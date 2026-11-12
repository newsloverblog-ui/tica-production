<?php

namespace App\Console\Commands;

use App\Models\ApplicationMailDelivery;
use App\Services\ApplicationMailService;
use App\Services\IncidentAlertService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class DispatchPendingApplicationMail extends Command
{
    protected $signature = 'mail:dispatch-pending {--limit=100} {--stale-minutes=10}';
    protected $description = 'Recover pending application-mail queue handoffs';

    public function handle(ApplicationMailService $mail, IncidentAlertService $alerts): int
    {
        if (!Schema::hasTable('application_mail_deliveries')
            || !Schema::hasColumn('application_mail_deliveries', 'dispatch_attempts')) {
            $this->error('The application-mail dispatch migration is not applied.');
            return self::FAILURE;
        }

        $limit = max(1, min(500, (int) $this->option('limit')));
        $staleMinutes = max(5, (int) $this->option('stale-minutes'));
        $attempted = 0;
        $failed = 0;
        $alerted = 0;

        $deliveries = ApplicationMailDelivery::query()
            ->where('status', 'pending')
            ->whereNull('processing_at')
            ->orderBy('id')
            ->limit($limit)
            ->get();
        foreach ($deliveries as $delivery) {
            $attempted++;
            if (!$mail->dispatchDelivery((int) $delivery->id)) {
                $failed++;
            }
            $delivery->refresh();
            if ($delivery->status === 'pending'
                && $delivery->created_at->lt(now()->subMinutes($staleMinutes))
                && $delivery->alerted_at === null) {
                $delivery->forceFill(['alerted_at' => now()])->save();
                $alerts->alert(
                    'application-mail-dispatch-pending:'.$delivery->id,
                    'A committed application-mail delivery did not start on the queue.',
                    [
                        'delivery_id' => $delivery->id,
                        'kind' => $delivery->kind,
                        'source_type' => $delivery->source_type,
                        'source_id' => $delivery->source_id,
                        'dispatch_attempts' => $delivery->dispatch_attempts,
                    ],
                    'critical'
                );
                $alerted++;
            }
        }

        $this->line(sprintf(
            'Application mail dispatch attempted: %d | failed: %d | alerted: %d',
            $attempted,
            $failed,
            $alerted
        ));

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
