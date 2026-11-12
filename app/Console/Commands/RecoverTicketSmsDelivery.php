<?php

namespace App\Console\Commands;

use App\Jobs\SendEventBookingSms;
use App\Models\BookingDelivery;
use App\Services\AuditLogger;
use App\Services\TicketSmsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

final class RecoverTicketSmsDelivery extends Command
{
    protected $signature = 'tickets:recover-sms-delivery
        {delivery : Failed booking-delivery row ID}
        {--accepted-reference= : Provider message ID proving the original request was accepted}
        {--confirm-not-accepted : Queue one replacement only after confirming the provider did not accept the request}
        {--evidence= : Required non-secret provider or incident evidence reference}
        {--json : Emit machine-readable JSON}';

    protected $description = 'Safely reconcile or retry one failed ticket SMS after provider-side verification';

    public function handle(TicketSmsService $sms): int
    {
        $deliveryId = filter_var($this->argument('delivery'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        $acceptedReference = trim((string) $this->option('accepted-reference'));
        $confirmNotAccepted = (bool) $this->option('confirm-not-accepted');
        $evidence = trim((string) $this->option('evidence'));

        if ($deliveryId === false) {
            return $this->reject('Delivery must be a positive integer.');
        }
        if (($acceptedReference !== '') === $confirmNotAccepted) {
            return $this->reject('Choose exactly one recovery action: --accepted-reference or --confirm-not-accepted.');
        }
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._:\/-]{5,190}$/', $evidence)) {
            return $this->reject('A 6–191 character non-secret --evidence reference is required.');
        }
        if ($acceptedReference !== '' && !preg_match('/^[A-Za-z0-9][A-Za-z0-9._:\/-]{2,190}$/', $acceptedReference)) {
            return $this->reject('The provider message reference is invalid.');
        }
        if ($confirmNotAccepted && !$sms->enabled()) {
            return $this->reject('Ticket SMS delivery must be enabled before a replacement can be queued.');
        }

        try {
            $result = DB::transaction(function () use (
                $deliveryId,
                $acceptedReference,
                $confirmNotAccepted,
                $evidence
            ): array {
                $delivery = BookingDelivery::query()->lockForUpdate()->find($deliveryId);
                if (!$delivery || $delivery->kind !== 'event_booking_sms' || $delivery->channel !== 'sms') {
                    throw new RuntimeException('The selected row is not a ticket SMS delivery.');
                }
                if ($delivery->status !== 'failed') {
                    throw new RuntimeException('Only a failed ticket SMS delivery can be recovered.');
                }

                $before = [
                    'status' => $delivery->status,
                    'attempts' => (int) $delivery->attempts,
                    'provider_reference' => $delivery->provider_reference,
                ];

                if ($acceptedReference !== '') {
                    $referenceInUse = BookingDelivery::query()
                        ->where('kind', 'event_booking_sms')
                        ->where('provider_reference', $acceptedReference)
                        ->where('id', '!=', $delivery->id)
                        ->exists();
                    if ($referenceInUse) {
                        throw new RuntimeException('That provider message reference is already assigned to another delivery.');
                    }

                    $delivery->forceFill([
                        'status' => 'sent',
                        'provider_reference' => $acceptedReference,
                        'sent_at' => now(),
                        'last_error' => null,
                        'processing_at' => null,
                    ])->save();
                    $action = 'ticket.sms_delivery_reconciled';
                } else {
                    $delivery->forceFill([
                        'status' => 'pending',
                        'provider_reference' => null,
                        'last_error' => null,
                        'processing_at' => null,
                        'alerted_at' => null,
                    ])->save();
                    SendEventBookingSms::dispatch((int) $delivery->booking_id)->afterCommit();
                    $action = 'ticket.sms_delivery_requeued';
                }

                AuditLogger::record($action, $delivery, $before, [
                    'status' => $delivery->status,
                    'attempts' => (int) $delivery->attempts,
                    'provider_reference' => $delivery->provider_reference,
                ], [
                    'evidence_reference' => $evidence,
                    'provider_non_acceptance_confirmed' => $confirmNotAccepted,
                ]);

                return [
                    'delivery_id' => (int) $delivery->id,
                    'booking_id' => (int) $delivery->booking_id,
                    'status' => $delivery->status,
                    'action' => $action,
                ];
            }, 3);
        } catch (Throwable $exception) {
            return $this->reject($exception->getMessage());
        }

        if ($this->option('json')) {
            $this->line((string) json_encode($result, JSON_UNESCAPED_SLASHES));
        } else {
            $this->info($result['status'] === 'sent'
                ? 'SMS delivery reconciled as provider-accepted.'
                : 'One replacement SMS delivery was queued.');
        }

        return self::SUCCESS;
    }

    private function reject(string $message): int
    {
        if ($this->option('json')) {
            $this->line((string) json_encode(['error' => $message], JSON_UNESCAPED_SLASHES));
        } else {
            $this->error($message);
        }

        return self::FAILURE;
    }
}
