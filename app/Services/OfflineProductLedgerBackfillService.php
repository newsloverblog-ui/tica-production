<?php

namespace App\Services;

use App\Models\ShopManagement\ProductOrder;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Throwable;

class OfflineProductLedgerBackfillService
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

        ProductOrder::query()
            ->where('gateway_type', 'offline')
            ->whereIn('payment_status', ['completed', '1'])
            ->orderBy('id')
            ->chunkById(200, function ($orders) use ($apply, &$result): void {
                foreach ($orders as $order) {
                    $result['scanned']++;
                    try {
                        $outcome = $apply
                            ? DB::transaction(function () use ($order): array {
                                $locked = ProductOrder::query()->lockForUpdate()->findOrFail($order->id);

                                return $this->inspect($locked, true);
                            }, 3)
                            : $this->inspect($order, false);
                    } catch (Throwable $exception) {
                        report($exception);
                        app(IncidentAlertService::class)->alert(
                            'offline-product-ledger-backfill:'.$order->id,
                            'Offline product sale ledger backfill failed for an order.',
                            [
                                'order_id' => $order->id,
                                'exception_type' => $exception::class,
                                'exception_code' => $exception->getCode(),
                            ]
                        );
                        $outcome = ['status' => 'error', 'reason' => 'write_failed'];
                    }

                    $this->mergeOutcome($result, $order, $outcome);
                }
            });

        $result['healthy'] = $result['missing'] === 0
            && $result['conflicts'] === 0
            && $result['errors'] === 0;

        return $result;
    }

    private function inspect(ProductOrder $order, bool $apply): array
    {
        $reference = 'order:'.$order->id;
        $identityQuery = Transaction::query()
            ->where('gateway', 'offline')
            ->where('gateway_reference', $reference);
        if ($apply) {
            $identityQuery->lockForUpdate();
        }
        $identities = $identityQuery->get();
        if ($identities->count() > 1) {
            return ['status' => 'conflict', 'reason' => 'duplicate_stable_identity'];
        }
        if ($identities->count() === 1) {
            $identity = $identities->first();

            return $this->matchesOrder($identity, $order)
                ? ['status' => 'already_linked', 'transaction_id' => $identity->id]
                : ['status' => 'conflict', 'reason' => 'identity_points_to_another_sale', 'transaction_id' => $identity->id];
        }

        $candidateQuery = Transaction::query()
            ->where('booking_id', $order->id)
            ->where('transcation_type', 2);
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
        if (!$this->matchesOrder($transaction, $order)) {
            return ['status' => 'conflict', 'reason' => 'sale_values_do_not_reconcile', 'transaction_id' => $transaction->id];
        }

        if (!$apply) {
            return ['status' => 'would_update', 'transaction_id' => $transaction->id];
        }

        $transaction->forceFill([
            'gateway' => 'offline',
            'gateway_reference' => $reference,
        ])->save();

        AuditLogger::record('transaction.offline_product_identity_backfilled', $transaction, [
            'gateway' => null,
            'gateway_reference' => null,
        ], [
            'gateway' => 'offline',
            'gateway_reference' => $reference,
        ], [
            'order_id' => $order->id,
            'order_reference' => $order->order_number,
        ]);

        return ['status' => 'updated', 'transaction_id' => $transaction->id];
    }

    private function matchesOrder(Transaction $transaction, ProductOrder $order): bool
    {
        $paid = in_array(strtolower((string) $transaction->payment_status), [
            '1', 'completed', 'paid', 'success',
        ], true);
        $ledgerMethod = strtolower(trim((string) $transaction->payment_method));
        $orderMethod = strtolower(trim((string) $order->method));
        $sameMethod = $ledgerMethod === '' || $orderMethod === '' || $ledgerMethod === $orderMethod;

        return (string) $transaction->booking_id === (string) $order->id
            && (int) $transaction->transcation_type === 2
            && $paid
            && abs((float) $transaction->grand_total - (float) $order->total) < 0.005
            && (string) ($transaction->customer_id ?? '') === (string) ($order->user_id ?? '')
            && $sameMethod;
    }

    private function mergeOutcome(array &$result, ProductOrder $order, array $outcome): void
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
            'order_id' => $order->id,
            'order_reference' => $order->order_number,
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
