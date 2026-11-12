<?php

namespace App\Services;

use App\Models\Event\Booking;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Throwable;

class OfflineLedgerBackfillService
{
    private const ISSUE_LIMIT = 200;

    public function run(bool $apply = false): array
    {
        $result = [
            'mode' => $apply ? 'apply' : 'dry-run',
            'scanned' => 0,
            'would_update' => 0,
            'updated' => 0,
            'already_linked' => 0,
            'missing' => 0,
            'conflicts' => 0,
            'errors' => 0,
            'issues' => [],
            'issues_truncated' => 0,
        ];

        Booking::query()
            ->where('gatewayType', 'offline')
            ->whereIn('paymentStatus', ['completed', '1'])
            ->orderBy('id')
            ->chunkById(200, function ($bookings) use ($apply, &$result): void {
                foreach ($bookings as $booking) {
                    $result['scanned']++;
                    try {
                        $outcome = $apply
                            ? DB::transaction(function () use ($booking): array {
                                $locked = Booking::query()->lockForUpdate()->findOrFail($booking->id);
                                return $this->inspect($locked, true);
                            }, 3)
                            : $this->inspect($booking, false);
                    } catch (Throwable $exception) {
                        report($exception);
                        app(IncidentAlertService::class)->alert(
                            'offline-ledger-backfill:'.$booking->id,
                            'Offline sale ledger backfill failed for a booking.',
                            [
                                'booking_id' => $booking->id,
                                'exception_type' => $exception::class,
                                'exception_code' => $exception->getCode(),
                            ]
                        );
                        $outcome = ['status' => 'error', 'reason' => 'write_failed'];
                    }

                    $this->mergeOutcome($result, $booking, $outcome);
                }
            });

        $result['healthy'] = $result['missing'] === 0
            && $result['conflicts'] === 0
            && $result['errors'] === 0;

        return $result;
    }

    private function inspect(Booking $booking, bool $apply): array
    {
        $reference = 'booking:'.$booking->id;
        $identityQuery = Transaction::query()
            ->where('gateway', 'offline')
            ->where('gateway_reference', $reference);
        if ($apply) {
            $identityQuery->lockForUpdate();
        }
        $identity = $identityQuery->first();
        if ($identity) {
            return $this->matchesBooking($identity, $booking)
                ? ['status' => 'already_linked', 'transaction_id' => $identity->id]
                : ['status' => 'conflict', 'reason' => 'identity_points_to_another_sale', 'transaction_id' => $identity->id];
        }

        $candidateQuery = Transaction::query()
            ->where('booking_id', $booking->id)
            ->where('transcation_type', 1);
        if ($apply) {
            $candidateQuery->lockForUpdate();
        }
        $candidates = $candidateQuery->get();

        if ($candidates->isEmpty()) {
            return ['status' => 'missing', 'reason' => 'sale_ledger_row_not_found'];
        }
        if ($candidates->count() !== 1) {
            return ['status' => 'conflict', 'reason' => 'multiple_sale_ledger_rows'];
        }

        /** @var Transaction $transaction */
        $transaction = $candidates->first();
        if ($transaction->gateway !== null || $transaction->gateway_reference !== null) {
            return ['status' => 'conflict', 'reason' => 'partial_or_conflicting_identity', 'transaction_id' => $transaction->id];
        }
        if (!$this->matchesBooking($transaction, $booking)) {
            return ['status' => 'conflict', 'reason' => 'sale_values_do_not_reconcile', 'transaction_id' => $transaction->id];
        }

        if (!$apply) {
            return ['status' => 'would_update', 'transaction_id' => $transaction->id];
        }

        $before = ['gateway' => null, 'gateway_reference' => null];
        $transaction->forceFill([
            'gateway' => 'offline',
            'gateway_reference' => $reference,
        ])->save();

        AuditLogger::record('transaction.offline_identity_backfilled', $transaction, $before, [
            'gateway' => 'offline',
            'gateway_reference' => $reference,
        ], [
            'booking_id' => $booking->id,
            'booking_reference' => $booking->booking_id,
        ]);

        return ['status' => 'updated', 'transaction_id' => $transaction->id];
    }

    private function matchesBooking(Transaction $transaction, Booking $booking): bool
    {
        $paid = in_array(strtolower((string) $transaction->payment_status), [
            '1', 'completed', 'paid', 'success',
        ], true);
        $sameMethod = trim((string) $transaction->payment_method) === ''
            || trim((string) $booking->paymentMethod) === ''
            || trim((string) $transaction->payment_method) === trim((string) $booking->paymentMethod);

        return (string) $transaction->booking_id === (string) $booking->id
            && (int) $transaction->transcation_type === 1
            && $paid
            && abs((float) $transaction->grand_total - (float) $booking->price) < 0.005
            && abs((float) $transaction->tax - (float) $booking->tax) < 0.005
            && abs((float) $transaction->commission - (float) $booking->commission) < 0.005
            && (string) ($transaction->customer_id ?? '') === (string) ($booking->customer_id ?? '')
            && (string) ($transaction->organizer_id ?? '') === (string) ($booking->organizer_id ?? '')
            && $sameMethod;
    }

    private function mergeOutcome(array &$result, Booking $booking, array $outcome): void
    {
        $status = (string) ($outcome['status'] ?? 'error');
        $counter = match ($status) {
            'would_update' => 'would_update',
            'updated' => 'updated',
            'already_linked' => 'already_linked',
            'missing' => 'missing',
            'conflict' => 'conflicts',
            default => 'errors',
        };
        $result[$counter]++;

        if (!in_array($status, ['missing', 'conflict', 'error'], true)) {
            return;
        }

        $issue = [
            'booking_id' => $booking->id,
            'booking_reference' => $booking->booking_id,
            'status' => $status,
            'reason' => $outcome['reason'] ?? 'unknown',
            'transaction_id' => $outcome['transaction_id'] ?? null,
        ];
        if (count($result['issues']) < self::ISSUE_LIMIT) {
            $result['issues'][] = $issue;
        } else {
            $result['issues_truncated']++;
        }
    }
}
