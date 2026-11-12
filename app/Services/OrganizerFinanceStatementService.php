<?php

namespace App\Services;

use App\Models\Organizer;
use App\Models\Transaction;
use App\Models\Withdraw;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class OrganizerFinanceStatementService
{
    public function query(
        int $organizerId,
        CarbonImmutable $from,
        CarbonImmutable $to,
        ?int $maxTransactionId = null
    ): Builder
    {
        return Transaction::where('organizer_id', $organizerId)
            ->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()])
            ->when($maxTransactionId !== null, fn (Builder $query) => $query->where('id', '<=', $maxTransactionId));
    }

    public function statement(
        int $organizerId,
        CarbonImmutable $from,
        CarbonImmutable $to,
        ?int $maxTransactionId = null,
        bool $includeTransactions = true
    ): array
    {
        $baseQuery = $this->query($organizerId, $from, $to, $maxTransactionId);
        $transactionCount = (clone $baseQuery)->count();
        $transactions = collect();
        if ($includeTransactions) {
            $transactions = (clone $baseQuery)->orderBy('created_at')->orderBy('id')->get();
            $sales = $transactions->where('transcation_type', 1)->filter(fn ($row) => $this->isPaid($row));
            $refunds = $transactions->where('transcation_type', 6);
            $manualCredits = $transactions->where('transcation_type', 4)->filter(fn ($row) => $this->isPaid($row));
            $manualDebits = $transactions->where('transcation_type', 5)->filter(fn ($row) => $this->isPaid($row));
            $grossSales = $this->sum($sales, 'grand_total');
            $platformFees = $this->sum($sales, 'commission');
            $refundAmount = $this->sum($refunds, 'grand_total');
            $refundedPlatformFees = $this->sum($refunds, 'commission');
            $credits = $this->sum($manualCredits, 'grand_total');
            $debits = $this->sum($manualDebits, 'grand_total');
        } else {
            $paidStatuses = ['1', 'completed', 'processed'];
            $sales = (clone $baseQuery)->where('transcation_type', 1)->whereIn('payment_status', $paidStatuses);
            $refunds = (clone $baseQuery)->where('transcation_type', 6);
            $manualCredits = (clone $baseQuery)->where('transcation_type', 4)->whereIn('payment_status', $paidStatuses);
            $manualDebits = (clone $baseQuery)->where('transcation_type', 5)->whereIn('payment_status', $paidStatuses);
            $grossSales = $this->querySum($sales, 'grand_total');
            $platformFees = $this->querySum($sales, 'commission');
            $refundAmount = $this->querySum($refunds, 'grand_total');
            $refundedPlatformFees = $this->querySum($refunds, 'commission');
            $credits = $this->querySum($manualCredits, 'grand_total');
            $debits = $this->querySum($manualDebits, 'grand_total');
        }
        $netSales = round(
            $grossSales - $platformFees - $refundAmount + $refundedPlatformFees + $credits - $debits,
            2
        );

        $pendingPayouts = Withdraw::where('organizer_id', $organizerId)
            ->whereIn('status', [Withdraw::STATUS_PENDING, Withdraw::STATUS_HELD])->get();
        $paidPayouts = Withdraw::where('organizer_id', $organizerId)
            ->where('status', Withdraw::STATUS_PAID)
            ->whereBetween('paid_at', [$from->startOfDay(), $to->endOfDay()])->get();

        return [
            'from' => $from->startOfDay(),
            'to' => $to->endOfDay(),
            'gross_sales' => $grossSales,
            'platform_fees' => $platformFees,
            'refunds' => $refundAmount,
            'refunded_platform_fees' => $refundedPlatformFees,
            'manual_credits' => $credits,
            'manual_debits' => $debits,
            'net_sales' => $netSales,
            'available_balance' => (float) Organizer::whereKey($organizerId)->value('amount'),
            'pending_payouts' => $this->sum($pendingPayouts, 'amount'),
            'paid_out' => $this->sum($paidPayouts, 'payable_amount'),
            'payout_fees' => $this->sum($paidPayouts, 'total_charge'),
            'gateway_fees' => null,
            'transaction_count' => $transactionCount,
            'transactions' => $transactions->map(fn ($transaction) => $this->line($transaction)),
        ];
    }

    public function line(Transaction $transaction): array
    {
        $type = (int) $transaction->transcation_type;
        $gross = round((float) $transaction->grand_total, 2);
        $fee = round((float) $transaction->commission, 2);
        $net = $gross - $fee;
        if (in_array($type, [3, 5], true)) $net = -$gross;
        if ($type === 6) $net = -($gross - $fee);
        if ($type === 3 && (string) $transaction->payment_status === '2') $net = 0.0;

        return [
            'id' => $transaction->id,
            'reference' => (string) $transaction->transcation_id,
            'occurred_at' => optional($transaction->created_at)->toIso8601String(),
            'type' => $this->typeLabel($type),
            'payment_method' => (string) ($transaction->payment_method ?? ''),
            'status' => $this->statusLabel($transaction->payment_status),
            'gross_amount' => $gross,
            'platform_fee' => $fee,
            'net_amount' => round($net, 2),
            'pre_balance' => round((float) $transaction->pre_balance, 2),
            'after_balance' => round((float) $transaction->after_balance, 2),
            'currency' => (string) ($transaction->currency_symbol ?? ''),
        ];
    }

    private function typeLabel(int $type): string
    {
        return [
            1 => 'event_sale', 2 => 'product_order', 3 => 'withdrawal',
            4 => 'manual_credit', 5 => 'manual_debit', 6 => 'refund',
        ][$type] ?? 'other';
    }

    private function statusLabel($status): string
    {
        if (in_array((string) $status, ['1', 'completed', 'processed'], true)) return 'completed';
        if (in_array((string) $status, ['2', 'declined', 'rejected'], true)) return 'rejected';
        return 'pending';
    }

    private function isPaid(Transaction $transaction): bool
    {
        return $this->statusLabel($transaction->payment_status) === 'completed';
    }

    private function sum(Collection $items, string $field): float
    {
        return round((float) $items->sum(fn ($item) => (float) $item->{$field}), 2);
    }

    private function querySum(Builder $query, string $field): float
    {
        return round((float) (clone $query)->sum($field), 2);
    }
}
