<?php

namespace App\Services;

use App\Models\PaymentIntent;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

class GatewayLedgerBackfillService
{
    private const ISSUE_LIMIT = 200;

    public function run(bool $apply = false, array $gateways = []): array
    {
        $gateways = $this->normalizeGateways($gateways);
        $result = [
            'mode' => $apply ? 'apply' : 'dry-run',
            'gateways' => $gateways,
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

        PaymentIntent::query()
            ->whereIn('gateway', $gateways)
            ->where('status', 'completed')
            ->whereNotNull('booking_id')
            ->orderBy('id')
            ->chunkById(200, function ($intents) use ($apply, &$result) {
                foreach ($intents as $intent) {
                    $result['scanned']++;
                    try {
                        $outcome = $apply
                            ? DB::transaction(function () use ($intent) {
                                $locked = PaymentIntent::whereKey($intent->id)->lockForUpdate()->firstOrFail();
                                return $this->inspect($locked, true);
                            }, 3)
                            : $this->inspect($intent, false);
                    } catch (Throwable $exception) {
                        report($exception);
                        app(IncidentAlertService::class)->alert(
                            'gateway-ledger-backfill:' . $intent->id,
                            'Gateway ledger backfill failed for an intent.', [
                            'payment_intent_id' => $intent->id,
                            'gateway' => $intent->gateway,
                            'reference' => $intent->reference,
                            'exception_type' => $exception::class,
                            'exception_code' => $exception->getCode(),
                        ]);
                        $outcome = ['status' => 'error', 'reason' => 'write_failed'];
                    }

                    $this->mergeOutcome($result, $intent, $outcome);
                }
            });

        $result['healthy'] = $result['missing'] === 0
            && $result['conflicts'] === 0
            && $result['errors'] === 0;

        return $result;
    }

    private function inspect(PaymentIntent $intent, bool $apply): array
    {
        $gateway = strtolower(trim((string) $intent->gateway));
        $reference = trim((string) $intent->reference);
        if ($gateway === '' || $reference === '') {
            return ['status' => 'conflict', 'reason' => 'invalid_gateway_identity'];
        }

        $identityQuery = Transaction::query()
            ->where('gateway', $gateway)
            ->where('gateway_reference', $reference);
        if ($apply) {
            $identityQuery->lockForUpdate();
        }
        $identity = $identityQuery->first();
        if ($identity) {
            return $this->matchesIntent($identity, $intent)
                ? ['status' => 'already_linked', 'transaction_id' => $identity->id]
                : ['status' => 'conflict', 'reason' => 'identity_points_to_another_sale', 'transaction_id' => $identity->id];
        }

        $candidateQuery = Transaction::query()
            ->where('booking_id', $intent->booking_id)
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
        if ((string) $transaction->transcation_id !== $reference) {
            return ['status' => 'conflict', 'reason' => 'legacy_reference_mismatch', 'transaction_id' => $transaction->id];
        }
        if ($transaction->gateway !== null || $transaction->gateway_reference !== null) {
            return ['status' => 'conflict', 'reason' => 'partial_or_conflicting_identity', 'transaction_id' => $transaction->id];
        }
        if (!$this->matchesIntent($transaction, $intent)) {
            return ['status' => 'conflict', 'reason' => 'sale_values_do_not_reconcile', 'transaction_id' => $transaction->id];
        }

        if (!$apply) {
            return ['status' => 'would_update', 'transaction_id' => $transaction->id];
        }

        $before = ['gateway' => null, 'gateway_reference' => null];
        $transaction->forceFill([
            'gateway' => $gateway,
            'gateway_reference' => $reference,
        ])->save();

        AuditLogger::record('transaction.gateway_identity_backfilled', $transaction, $before, [
            'gateway' => $gateway,
            'gateway_reference' => $reference,
        ], [
            'payment_intent_id' => $intent->id,
            'booking_id' => $intent->booking_id,
        ]);

        return ['status' => 'updated', 'transaction_id' => $transaction->id];
    }

    private function matchesIntent(Transaction $transaction, PaymentIntent $intent): bool
    {
        $sameBooking = (string) $transaction->booking_id === (string) $intent->booking_id;
        $chargedTotal = (float) $transaction->grand_total + (float) $transaction->tax;
        $sameAmount = abs($chargedTotal - (float) $intent->amount) < 0.005;
        $paid = in_array(strtolower((string) $transaction->payment_status), [
            '1', 'completed', 'paid', 'success',
        ], true);

        return $sameBooking
            && $sameAmount
            && $paid
            && (int) $transaction->transcation_type === 1;
    }

    private function normalizeGateways(array $gateways): array
    {
        $allowed = array_values(array_unique(array_filter(array_map(
            static fn ($gateway) => strtolower(trim((string) $gateway)),
            (array) config('payments.production_ready_gateways', ['paystack', 'flutterwave'])
        ))));
        $requested = array_values(array_unique(array_filter(array_map(
            static fn ($gateway) => strtolower(trim((string) $gateway)),
            $gateways ?: $allowed
        ))));
        $unsupported = array_values(array_diff($requested, $allowed));

        if ($requested === [] || $unsupported !== []) {
            throw new InvalidArgumentException($unsupported === []
                ? 'At least one hardened gateway is required.'
                : 'Gateway is not on the hardened production allowlist: ' . implode(', ', $unsupported));
        }

        return $requested;
    }

    private function mergeOutcome(array &$result, PaymentIntent $intent, array $outcome): void
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

        if (in_array($status, ['missing', 'conflict', 'error'], true)) {
            $issue = [
                'payment_intent_id' => $intent->id,
                'gateway' => $intent->gateway,
                'reference' => $intent->reference,
                'booking_id' => $intent->booking_id,
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
}
