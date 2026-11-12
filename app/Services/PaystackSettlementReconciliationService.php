<?php

namespace App\Services;

use App\Models\GatewaySettlement;
use App\Models\GatewaySettlementEntry;
use App\Models\PaymentIntent;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Throwable;

class PaystackSettlementReconciliationService
{
    private const MAX_PROVIDER_PAGES = 1000;

    public function reconcile(
        CarbonImmutable $from,
        CarbonImmutable $to,
        ?PaystackGatewayClient $client = null
    ): array {
        $client = $client ?: new PaystackGatewayClient();
        $results = [];
        $from = $from->startOfDay();
        $to = $to->endOfDay();
        $page = 1;

        try {
            do {
                $response = $client->listSettlements($from, $to, $page, 100);
                $rows = is_array($response['data'] ?? null) ? $response['data'] : [];
                $pageCount = $this->providerPageCount($response, $page);

                foreach ($rows as $payload) {
                    $settlementId = trim((string) ($payload['id'] ?? ''));
                    if ($settlementId === '') {
                        continue;
                    }
                    $settlement = $this->persistSettlement($payload);
                    if (strtolower($settlement->status) !== 'success') {
                        $results[] = $settlement;
                        continue;
                    }

                    try {
                        $results[] = $this->reconcileSettlement($settlement, $client, $settlementId);
                    } catch (Throwable $exception) {
                        $settlement->update([
                            'reconciliation_status' => 'failed',
                            'last_error' => mb_substr($exception->getMessage(), 0, 2000),
                            'reconciled_at' => now(),
                        ]);
                        app(IncidentAlertService::class)->alert(
                            'paystack-settlement-failed:' . $settlementId,
                            'Paystack settlement reconciliation failed.', [
                            'settlement_id' => $settlementId,
                            'exception_type' => $exception::class,
                            'exception_code' => $exception->getCode(),
                        ]);
                        $results[] = $settlement->fresh();
                    }
                }

                $page++;
            } while ($rows !== [] && $page <= $pageCount);
        } catch (Throwable $exception) {
            app(IncidentAlertService::class)->alert(
                'paystack-settlement-list-failed:' . $from->toDateString() . ':' . $to->toDateString(),
                'Paystack settlement listing failed.', [
                    'from' => $from->toDateString(),
                    'to' => $to->toDateString(),
                    'exception_type' => $exception::class,
                    'exception_code' => $exception->getCode(),
                ]
            );
            throw $exception;
        }

        return $results;
    }

    private function persistSettlement(array $payload): GatewaySettlement
    {
        $money = fn ($value) => round(((float) ($value ?? 0)) / 100, 2);
        return GatewaySettlement::updateOrCreate([
            'gateway' => 'paystack',
            'gateway_settlement_id' => (string) $payload['id'],
        ], [
            'status' => strtolower((string) ($payload['status'] ?? 'pending')),
            'currency' => strtoupper((string) ($payload['currency'] ?? '')) ?: null,
            'total_processed' => $money($payload['total_processed'] ?? 0),
            'total_fees' => $money($payload['total_fees'] ?? 0),
            'total_amount' => $money($payload['total_amount'] ?? 0),
            'effective_amount' => $money($payload['effective_amount'] ?? 0),
            'reconciliation_status' => 'pending',
            'gateway_payload' => $payload,
            'settlement_date' => $payload['settlement_date'] ?? null,
            'fetched_at' => now(),
        ]);
    }

    private function reconcileSettlement(
        GatewaySettlement $settlement,
        PaystackGatewayClient $client,
        string $settlementId
    ): GatewaySettlement
    {
        DB::transaction(function () use ($settlement): void {
            $settlement = GatewaySettlement::whereKey($settlement->id)->lockForUpdate()->firstOrFail();
            $settlement->entries()->delete();
            $settlement->update([
                'transaction_count' => 0,
                'matched_count' => 0,
                'mismatch_count' => 0,
                'reconciliation_status' => 'pending',
                'last_error' => null,
                'reconciled_at' => null,
            ]);
        }, 3);

        $matched = 0;
        $mismatched = 0;
        $processedCount = 0;
        $gross = 0.0;
        $fees = 0.0;
        $page = 1;

        do {
            $response = $client->listSettlementTransactions($settlementId, $page, 100);
            $transactions = is_array($response['data'] ?? null) ? $response['data'] : [];
            $pageCount = $this->providerPageCount($response, $page);
            $references = collect($transactions)
                ->pluck('reference')
                ->filter(fn ($reference) => trim((string) $reference) !== '')
                ->map(fn ($reference) => trim((string) $reference))
                ->unique()
                ->values()
                ->all();
            $intents = $references === []
                ? collect()
                : PaymentIntent::where('gateway', 'paystack')
                    ->whereIn('reference', $references)
                    ->get()
                    ->keyBy('reference');
            $ledgerEntries = (new GatewayLedgerIntegrityService())
                ->forReferences('paystack', $references);

            $pageEntries = [];
            $pageMatched = 0;
            $pageMismatched = 0;
            $pageGross = 0.0;
            $pageFees = 0.0;

            foreach ($transactions as $transaction) {
                $reference = trim((string) ($transaction['reference'] ?? ''));
                if ($reference === '') {
                    continue;
                }
                $intent = $intents->get($reference);
                $ledgerTransaction = $ledgerEntries->get($reference);
                $gatewayAmount = round(((float) ($transaction['amount'] ?? 0)) / 100, 2);
                $gatewayFee = round(((float) ($transaction['fees'] ?? 0)) / 100, 2);
                $gatewayCurrency = strtoupper((string) ($transaction['currency'] ?? $settlement->currency ?? ''));
                $issues = [];

                if (!$intent) {
                    $issues[] = 'settled_charge_missing_locally';
                } else {
                    if (!$intent->booking_id) $issues[] = 'settled_without_booking';
                    if ($intent->status !== 'completed') $issues[] = 'local_status_not_completed';
                    if (abs((float) $intent->amount - $gatewayAmount) > 0.009) $issues[] = 'amount_mismatch';
                    if (strtoupper($intent->currency) !== $gatewayCurrency) $issues[] = 'currency_mismatch';
                    $issues = array_merge(
                        $issues,
                        (new GatewayLedgerIntegrityService())->issues($intent, $ledgerTransaction)
                    );
                }

                $result = $issues === [] ? 'matched' : $issues[0];
                $result === 'matched' ? $pageMatched++ : $pageMismatched++;
                $pageGross += $gatewayAmount;
                $pageFees += $gatewayFee;
                $pageEntries[] = [
                    'payment_intent_id' => optional($intent)->id,
                    'booking_id' => optional($intent)->booking_id,
                    'reference' => $reference,
                    'result' => $result,
                    'expected_amount' => optional($intent)->amount,
                    'gateway_amount' => $gatewayAmount,
                    'gateway_fee' => $gatewayFee,
                    'expected_currency' => $intent ? strtoupper($intent->currency) : null,
                    'gateway_currency' => $gatewayCurrency,
                    'local_status' => optional($intent)->status,
                    'gateway_status' => strtolower((string) ($transaction['status'] ?? 'success')),
                    'details' => [
                        'issues' => $issues,
                        'gateway_id' => $transaction['id'] ?? null,
                        'ledger_transaction_id' => optional($ledgerTransaction)->id,
                    ],
                    'checked_at' => now(),
                ];
            }

            $nextMatched = $matched + $pageMatched;
            $nextMismatched = $mismatched + $pageMismatched;
            $nextProcessedCount = $processedCount + count($pageEntries);

            DB::transaction(function () use (
                $settlement,
                $pageEntries,
                $nextMatched,
                $nextMismatched,
                $nextProcessedCount
            ): void {
                $lockedSettlement = GatewaySettlement::whereKey($settlement->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                foreach ($pageEntries as $entry) {
                    GatewaySettlementEntry::create(array_merge($entry, [
                        'gateway_settlement_id' => $lockedSettlement->id,
                    ]));
                }

                $lockedSettlement->update([
                    'transaction_count' => $nextProcessedCount,
                    'matched_count' => $nextMatched,
                    'mismatch_count' => $nextMismatched,
                ]);
            }, 3);

            $matched = $nextMatched;
            $mismatched = $nextMismatched;
            $processedCount = $nextProcessedCount;
            $gross += $pageGross;
            $fees += $pageFees;

            $page++;
        } while ($transactions !== [] && $page <= $pageCount);

        return DB::transaction(function () use (
            $settlement,
            $matched,
            $mismatched,
            $processedCount,
            $gross,
            $fees
        ) {
            $settlement = GatewaySettlement::whereKey($settlement->id)->lockForUpdate()->firstOrFail();
            $aggregateIssues = [];
            if (abs($gross - (float) $settlement->total_processed) > 0.009) {
                $aggregateIssues[] = 'transaction gross does not match settlement total processed';
                $mismatched++;
            }
            if (abs($fees - (float) $settlement->total_fees) > 0.009) {
                $aggregateIssues[] = 'transaction fees do not match settlement total fees';
                $mismatched++;
            }

            $settlement->update([
                'transaction_count' => $processedCount,
                'matched_count' => $matched,
                'mismatch_count' => $mismatched,
                'reconciliation_status' => $mismatched > 0 ? 'mismatched' : 'matched',
                'last_error' => $aggregateIssues === [] ? null : implode('; ', $aggregateIssues),
                'reconciled_at' => now(),
            ]);

            if ($mismatched > 0) {
                $context = [
                    'settlement_id' => $settlement->gateway_settlement_id,
                    'mismatch_count' => $mismatched,
                    'gross_expected' => (float) $settlement->total_processed,
                    'gross_transactions' => round($gross, 2),
                    'fees_expected' => (float) $settlement->total_fees,
                    'fees_transactions' => round($fees, 2),
                ];
                DB::afterCommit(fn () => app(IncidentAlertService::class)->alert(
                    'paystack-settlement-mismatch:' . $settlement->gateway_settlement_id,
                    'Paystack settlement contains reconciliation mismatches.',
                    $context
                ));
            }

            return $settlement->fresh();
        }, 3);
    }

    private function providerPageCount(array $response, int $page): int
    {
        $meta = is_array($response['meta'] ?? null) ? $response['meta'] : [];
        $pageCount = max($page, (int) ($meta['pageCount'] ?? $meta['page_count'] ?? $page));
        if ($pageCount > self::MAX_PROVIDER_PAGES) {
            throw new \RuntimeException('Paystack settlement reconciliation exceeded the provider page safety limit.');
        }

        return $pageCount;
    }
}
