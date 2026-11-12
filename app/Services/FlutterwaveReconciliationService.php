<?php

namespace App\Services;

use App\Models\PaymentIntent;
use App\Models\PaymentReconciliationEntry;
use App\Models\PaymentReconciliationRun;
use App\Models\Transaction;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Throwable;

class FlutterwaveReconciliationService
{
    private const MAX_PROVIDER_PAGES = 1000;

    public function reconcile(CarbonImmutable $date, ?FlutterwaveGatewayClient $client = null): PaymentReconciliationRun
    {
        $run = PaymentReconciliationRun::create([
            'public_id' => (string) Str::uuid(),
            'gateway' => 'flutterwave',
            'reconciliation_date' => $date->toDateString(),
            'status' => 'running',
            'started_at' => now(),
        ]);
        $gatewayCount = 0;
        $localCount = 0;
        $matched = 0;
        $mismatched = 0;

        try {
            $from = $date->startOfDay();
            $to = $date->endOfDay();
            $client ??= new FlutterwaveGatewayClient();
            $localQuery = PaymentIntent::query()
                ->where('gateway', 'flutterwave')
                ->where(function ($query) use ($from, $to) {
                    $query->whereBetween('created_at', [$from, $to])
                        ->orWhereBetween('processed_at', [$from, $to]);
                });
            $localCount = (clone $localQuery)->count();
            $page = 1;

            do {
                $payload = $client->listTransactions($from, $to, $page);
                $rows = is_array($payload['data'] ?? null) ? $payload['data'] : [];
                $gatewayCount += count($rows);
                $references = collect($rows)
                    ->pluck('tx_ref')
                    ->filter(fn ($reference) => trim((string) $reference) !== '')
                    ->map(fn ($reference) => trim((string) $reference))
                    ->unique()
                    ->values()
                    ->all();
                $localIntents = $references === []
                    ? collect()
                    : (clone $localQuery)->whereIn('reference', $references)->get()->keyBy('reference');
                $ledgerEntries = (new GatewayLedgerIntegrityService())
                    ->forReferences('flutterwave', $references);

                foreach ($rows as $transaction) {
                    $reference = trim((string) ($transaction['tx_ref'] ?? ''));
                    if ($reference === '') {
                        continue;
                    }
                    $result = $this->recordGatewayTransaction(
                        $run,
                        $localIntents->get($reference),
                        $transaction,
                        $ledgerEntries->get($reference)
                    );
                    $result === 'matched' ? $matched++ : $mismatched++;
                }

                $pageInfo = is_array($payload['meta']['page_info'] ?? null)
                    ? $payload['meta']['page_info']
                    : (is_array($payload['meta'] ?? null) ? $payload['meta'] : []);
                $currentPage = max(1, (int) ($pageInfo['current_page'] ?? $page));
                $totalPages = max($currentPage, (int) ($pageInfo['total_pages'] ?? $pageInfo['totalPages'] ?? $currentPage));
                if ($totalPages > self::MAX_PROVIDER_PAGES) {
                    throw new \RuntimeException('Flutterwave reconciliation exceeded the provider page safety limit.');
                }
                $page = $currentPage + 1;
            } while ($rows !== [] && $page <= $totalPages);

            $mismatched += $this->recordLocalOnly($run, $localQuery);

            $run->update([
                'status' => $mismatched > 0 ? 'mismatched' : 'matched',
                'gateway_count' => $gatewayCount,
                'local_count' => $localCount,
                'matched_count' => $matched,
                'mismatch_count' => $mismatched,
                'completed_at' => now(),
            ]);

            if ($mismatched > 0) {
                app(IncidentAlertService::class)->alert(
                    'flutterwave-reconciliation-mismatch:' . $run->public_id,
                    'Flutterwave reconciliation found payment mismatches.', [
                        'run_id' => $run->public_id,
                        'date' => $date->toDateString(),
                        'mismatch_count' => $mismatched,
                    ]
                );
            }
        } catch (Throwable $exception) {
            $run->update([
                'status' => 'failed',
                'gateway_count' => $gatewayCount,
                'local_count' => $localCount,
                'matched_count' => $matched,
                'mismatch_count' => $mismatched,
                'last_error' => mb_substr($exception->getMessage(), 0, 2000),
                'completed_at' => now(),
            ]);
            app(IncidentAlertService::class)->alert(
                'flutterwave-reconciliation-failed:' . $run->public_id,
                'Flutterwave reconciliation failed.', [
                    'run_id' => $run->public_id,
                    'date' => $date->toDateString(),
                    'exception_type' => $exception::class,
                    'exception_code' => $exception->getCode(),
                ]
            );
        }

        return $run->fresh();
    }

    private function recordLocalOnly(PaymentReconciliationRun $run, Builder $localQuery): int
    {
        $count = 0;
        (clone $localQuery)
            ->where('status', 'completed')
            ->whereNotExists(function ($query) use ($run): void {
                $query->selectRaw('1')
                    ->from('payment_reconciliation_entries')
                    ->whereColumn('payment_reconciliation_entries.payment_intent_id', 'payment_intents.id')
                    ->where('payment_reconciliation_entries.run_id', $run->id);
            })
            ->chunkById(200, function ($intents) use ($run, &$count): void {
                foreach ($intents as $intent) {
                    PaymentReconciliationEntry::create([
                        'run_id' => $run->id,
                        'payment_intent_id' => $intent->id,
                        'booking_id' => $intent->booking_id,
                        'gateway' => 'flutterwave',
                        'reference' => $intent->reference,
                        'result' => 'local_only',
                        'expected_amount' => $intent->amount,
                        'expected_currency' => strtoupper($intent->currency),
                        'local_status' => $intent->status,
                        'details' => ['issues' => ['completed_locally_missing_from_gateway_day']],
                        'checked_at' => now(),
                    ]);
                    $count++;
                }
            });

        return $count;
    }

    private function recordGatewayTransaction(
        PaymentReconciliationRun $run,
        ?PaymentIntent $intent,
        array $transaction,
        ?Transaction $ledgerTransaction = null
    ): string {
        $reference = (string) $transaction['tx_ref'];
        $gatewayAmount = round((float) ($transaction['amount'] ?? 0), 2);
        $gatewayCurrency = strtoupper((string) ($transaction['currency'] ?? ''));
        $gatewayStatus = strtolower((string) ($transaction['status'] ?? ''));
        $issues = [];

        if (!$intent) {
            $result = 'gateway_only';
            $issues[] = 'successful_gateway_charge_missing_locally';
        } else {
            if (!$intent->booking_id) {
                $issues[] = 'paid_without_booking';
            }
            if ($intent->status !== 'completed') {
                $issues[] = 'local_status_not_completed';
            }
            if (abs((float) $intent->amount - $gatewayAmount) > 0.009) {
                $issues[] = 'amount_mismatch';
            }
            if (strtoupper($intent->currency) !== $gatewayCurrency) {
                $issues[] = 'currency_mismatch';
            }
            $issues = array_merge(
                $issues,
                (new GatewayLedgerIntegrityService())->issues($intent, $ledgerTransaction)
            );
            $result = $issues === [] ? 'matched' : $issues[0];
        }

        PaymentReconciliationEntry::create([
            'run_id' => $run->id,
            'payment_intent_id' => optional($intent)->id,
            'booking_id' => optional($intent)->booking_id,
            'gateway' => 'flutterwave',
            'reference' => $reference,
            'result' => $result,
            'expected_amount' => optional($intent)->amount,
            'gateway_amount' => $gatewayAmount,
            'expected_currency' => $intent ? strtoupper($intent->currency) : null,
            'gateway_currency' => $gatewayCurrency,
            'local_status' => optional($intent)->status,
            'gateway_status' => $gatewayStatus,
            'details' => [
                'issues' => $issues,
                'gateway_id' => $transaction['id'] ?? null,
                'ledger_transaction_id' => optional($ledgerTransaction)->id,
            ],
            'checked_at' => now(),
        ]);

        return $result;
    }
}
