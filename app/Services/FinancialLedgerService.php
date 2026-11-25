<?php

namespace App\Services;

use App\Models\Event;
use App\Models\FinancialLedgerAccount;
use App\Models\FinancialLedgerEntry;
use App\Models\FinancialLedgerTransaction;
use App\Models\Organizer;
use App\Models\OrganizerEarningRelease;
use App\Models\TicaPromotionCampaign;
use App\Models\Transaction;
use App\Models\Withdraw;
use App\Support\Money;
use App\Support\EventFinancialBreakdown;
use App\Support\TicaCurrency;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

/**
 * Authoritative, integer, double-entry money ledger.
 *
 * Legacy decimal balances remain a compatibility mirror while the product is
 * migrated. Every new sale is posted here first as pending earnings; only a
 * release posting makes that money available for withdrawal.
 */
class FinancialLedgerService
{
    public const PENDING = 'organizer_earnings_pending';
    public const AVAILABLE = 'organizer_earnings_available';
    public const HOLD = 'organizer_payout_hold';
    public const CLEARING = 'platform_gateway_clearing';
    public const PLATFORM_FEES = 'platform_fee_revenue';
    public const PROCESSOR_FEES = 'gateway_processing_expense';
    public const PROMOTION_REVENUE = 'platform_promotion_revenue';

    public function postPaystackChargeback(int $disputeId, string $currency, int $amountMinor): void
    {
        $suspense = $this->account(null, null, 'platform_chargeback_suspense', 'Chargebacks pending allocation', 'asset', 'debit', $currency);
        $clearing = $this->account(null, null, self::CLEARING, 'Gateway clearing', 'asset', 'debit', $currency);
        $this->post('paystack-chargeback:' . $disputeId, 'payment_chargeback', [
            [$suspense, 'debit', $amountMinor], [$clearing, 'credit', $amountMinor],
        ], \App\Models\PaystackDispute::class, $disputeId);
    }

    public function recordSale(object $booking, Transaction $legacy, ?int $processorFeeMinor = null): OrganizerEarningRelease
    {
        $organizerId = (int) ($booking->organizer_id ?? 0);
        if ($organizerId < 1) {
            throw new LogicException('An organizer sale needs an organizer.');
        }

        $currency = TicaCurrency::normalize($booking->currencyText ?? $legacy->currency ?? null);
        $breakdown = EventFinancialBreakdown::fromBooking($booking);
        $gross = Money::toMinor($breakdown['customer_paid']);
        $platformFee = Money::toMinor($breakdown['platform_revenue']);
        $organizerAmount = Money::toMinor($breakdown['organizer_revenue']);
        $processorFeeMinor = max(0, (int) ($processorFeeMinor ?? 0));
        if ($gross !== $platformFee + $organizerAmount) {
            throw new LogicException('Sale amounts do not balance in minor units.');
        }

        return DB::transaction(function () use (
            $booking, $legacy, $currency, $gross, $platformFee, $organizerAmount, $processorFeeMinor, $organizerId
        ): OrganizerEarningRelease {
            $existing = OrganizerEarningRelease::where('booking_id', $booking->id)->lockForUpdate()->first();
            if ($existing) {
                if ((int) $existing->organizer_amount_minor !== $organizerAmount
                    || (int) $existing->platform_fee_minor !== $platformFee
                    || strtoupper($existing->currency) !== $currency) {
                    throw new LogicException('Booking is already linked to different financial ledger amounts.');
                }
                return $existing;
            }

            $organizer = Organizer::whereKey($organizerId)->lockForUpdate()->firstOrFail();
            $clearing = $this->account(null, null, self::CLEARING, 'Gateway clearing', 'asset', 'debit', $currency);
            $fees = $this->account(null, null, self::PLATFORM_FEES, 'Platform fee revenue', 'revenue', 'credit', $currency);
            $pending = $this->account(Organizer::class, $organizer->id, self::PENDING, 'Pending earnings', 'liability', 'credit', $currency);

            $ledger = $this->post(
                'sale:' . strtolower((string) $legacy->gateway) . ':' . (string) $legacy->gateway_reference,
                'ticket_sale',
                [
                    [$clearing, 'debit', $gross],
                    [$fees, 'credit', $platformFee],
                    [$pending, 'credit', $organizerAmount],
                ],
                Transaction::class,
                $legacy->id,
                ['booking_id' => (int) $booking->id, 'processor_fee_minor' => $processorFeeMinor]
            );

            return OrganizerEarningRelease::create([
                'public_id' => (string) Str::uuid(),
                'organizer_id' => $organizer->id,
                'event_id' => $booking->event_id ?? null,
                'booking_id' => $booking->id,
                'financial_ledger_transaction_id' => $ledger->id,
                'currency' => $currency,
                'gross_amount_minor' => $gross,
                'platform_fee_minor' => $platformFee,
                'processor_fee_minor' => $processorFeeMinor,
                'organizer_amount_minor' => $organizerAmount,
                'status' => 'pending',
                'available_at' => $this->availableAt($booking->event_id ?? null),
            ]);
        }, 3);
    }

    public function releaseDue(int $limit = 200): array
    {
        $ids = OrganizerEarningRelease::query()
            ->where('status', 'pending')
            ->whereNotNull('available_at')->where('available_at', '<=', now())
            ->when(\Illuminate\Support\Facades\Schema::hasTable('paystack_disputes'), fn ($query) => $query->whereNotExists(
                fn ($disputes) => $disputes->selectRaw('1')->from('paystack_disputes')
                    ->whereColumn('paystack_disputes.booking_id', 'organizer_earning_releases.booking_id')
                    ->where('paystack_disputes.status', '!=', 'declined')
            ))
            ->oldest('available_at')->limit(max(1, min(500, $limit)))->pluck('id');
        $summary = ['checked' => $ids->count(), 'released' => 0, 'held' => 0];

        foreach ($ids as $id) {
            $outcome = DB::transaction(function () use ($id): string {
                $release = OrganizerEarningRelease::whereKey($id)->lockForUpdate()->first();
                if (!$release || $release->status !== 'pending' || !$release->available_at || $release->available_at->isFuture()) {
                    return 'skipped';
                }
                if (\App\Models\PaystackDispute::blocksBooking((int) $release->booking_id)) {
                    return 'held';
                }
                $event = $release->event_id ? Event::whereKey($release->event_id)->lockForUpdate()->first() : null;
                if ($event && (($event->lifecycle_status ?? 'scheduled') === 'cancelled' || $event->cancelled_at)) {
                    $release->forceFill(['status' => 'held', 'held_at' => now(), 'hold_reason' => 'Event cancelled; finance review required.'])->save();
                    return 'held';
                }

                $pending = $this->account(Organizer::class, $release->organizer_id, self::PENDING, 'Pending earnings', 'liability', 'credit', $release->currency);
                $available = $this->account(Organizer::class, $release->organizer_id, self::AVAILABLE, 'Available earnings', 'liability', 'credit', $release->currency);
                $this->post('release:' . $release->public_id, 'earnings_release', [
                    [$pending, 'debit', $release->organizer_amount_minor],
                    [$available, 'credit', $release->organizer_amount_minor],
                ], OrganizerEarningRelease::class, $release->id);

                $release->forceFill(['status' => 'available', 'released_at' => now(), 'hold_reason' => null])->save();
                return 'released';
            }, 3);
            if (isset($summary[$outcome])) $summary[$outcome]++;
        }

        return $summary;
    }

    public function balances(int $organizerId, string $currency = 'NGN'): array
    {
        $currency = TicaCurrency::normalize($currency);
        return [
            'pending_minor' => $this->accountBalance($organizerId, self::PENDING, $currency),
            'available_minor' => $this->accountBalance($organizerId, self::AVAILABLE, $currency),
            'on_hold_minor' => $this->accountBalance($organizerId, self::HOLD, $currency),
        ];
    }

    public function ensureOpeningAvailable(int $organizerId, int $amountMinor, string $currency = 'NGN'): void
    {
        $currency = TicaCurrency::normalize($currency);
        if ($this->hasOrganizerLedger($organizerId, $currency) || $amountMinor < 1) return;

        DB::transaction(function () use ($organizerId, $amountMinor, $currency): void {
            Organizer::whereKey($organizerId)->lockForUpdate()->firstOrFail();
            if ($this->hasOrganizerLedger($organizerId, $currency)) return;
            $clearing = $this->account(null, null, self::CLEARING, 'Gateway clearing', 'asset', 'debit', $currency);
            $available = $this->account(Organizer::class, $organizerId, self::AVAILABLE, 'Available earnings', 'liability', 'credit', $currency);
            $this->post('opening:organizer:' . $organizerId . ':' . $currency, 'opening_balance', [
                [$clearing, 'debit', $amountMinor], [$available, 'credit', $amountMinor],
            ], Organizer::class, $organizerId, ['migration_boundary' => true]);
        }, 3);
    }

    /** Spend an organiser's available earnings on a TICA promotion. */
    public function spendAvailableOnPromotion(TicaPromotionCampaign $campaign, int $organizerId): FinancialLedgerTransaction
    {
        $currency = TicaCurrency::normalize($campaign->currency);
        $amount = Money::toMinor($campaign->price);
        if ($amount < 1) throw new LogicException('A promotion ledger payment needs a positive amount.');

        return DB::transaction(function () use ($campaign, $organizerId, $currency, $amount): FinancialLedgerTransaction {
            $organizer = Organizer::whereKey($organizerId)->lockForUpdate()->firstOrFail();
            if (!$this->hasOrganizerLedger($organizerId, $currency)) {
                $opening = Money::toMinor($organizer->amount);
                if ($opening > 0) {
                    $clearing = $this->account(null, null, self::CLEARING, 'Gateway clearing', 'asset', 'debit', $currency);
                    $available = $this->account(Organizer::class, $organizerId, self::AVAILABLE, 'Available earnings', 'liability', 'credit', $currency);
                    $this->post('opening:organizer:' . $organizerId . ':' . $currency, 'opening_balance', [
                        [$clearing, 'debit', $opening], [$available, 'credit', $opening],
                    ], Organizer::class, $organizerId, ['migration_boundary' => true]);
                }
            }

            $available = $this->account(Organizer::class, $organizerId, self::AVAILABLE, 'Available earnings', 'liability', 'credit', $currency);
            if ($this->accountBalance($organizerId, self::AVAILABLE, $currency) < $amount) {
                throw new LogicException('The financial ledger has insufficient available balance.');
            }
            $revenue = $this->account(null, null, self::PROMOTION_REVENUE, 'Promotion revenue', 'revenue', 'credit', $currency);
            $transaction = $this->post('promotion:spend:' . $campaign->reference, 'promotion_purchase', [
                [$available, 'debit', $amount], [$revenue, 'credit', $amount],
            ], TicaPromotionCampaign::class, $campaign->id, ['organizer_id' => $organizerId]);
            $organizer->forceFill(['amount' => Money::fromMinor($this->accountBalance($organizerId, self::AVAILABLE, $currency))])->save();

            return $transaction;
        }, 3);
    }

    /** Return a balance-funded promotion payment to available earnings. */
    public function refundPromotionToAvailable(TicaPromotionCampaign $campaign): FinancialLedgerTransaction
    {
        $currency = TicaCurrency::normalize($campaign->currency);
        $amount = Money::toMinor($campaign->price);
        if ($amount < 1) throw new LogicException('A promotion ledger refund needs a positive amount.');

        return DB::transaction(function () use ($campaign, $currency, $amount): FinancialLedgerTransaction {
            $organizer = Organizer::whereKey($campaign->organizer_id)->lockForUpdate()->firstOrFail();
            $available = $this->account(Organizer::class, $organizer->id, self::AVAILABLE, 'Available earnings', 'liability', 'credit', $currency);
            $revenue = $this->account(null, null, self::PROMOTION_REVENUE, 'Promotion revenue', 'revenue', 'credit', $currency);
            $transaction = $this->post('promotion:refund:' . $campaign->reference, 'promotion_refund', [
                [$revenue, 'debit', $amount], [$available, 'credit', $amount],
            ], TicaPromotionCampaign::class, $campaign->id, ['organizer_id' => $organizer->id]);
            $organizer->forceFill(['amount' => Money::fromMinor($this->accountBalance($organizer->id, self::AVAILABLE, $currency))])->save();

            return $transaction;
        }, 3);
    }

    public function reserveWithdrawal(Withdraw $withdrawal, string $currency = 'NGN'): FinancialLedgerTransaction
    {
        $currency = TicaCurrency::normalize($currency);
        $amount = Money::toMinor($withdrawal->amount);
        return DB::transaction(function () use ($withdrawal, $currency, $amount) {
            $locked = Withdraw::whereKey($withdrawal->id)->lockForUpdate()->firstOrFail();
            $available = $this->account(Organizer::class, $locked->organizer_id, self::AVAILABLE, 'Available earnings', 'liability', 'credit', $currency);
            $hold = $this->account(Organizer::class, $locked->organizer_id, self::HOLD, 'Payouts on hold', 'liability', 'credit', $currency);
            if ($this->accountBalance($locked->organizer_id, self::AVAILABLE, $currency) < $amount) {
                throw new LogicException('The financial ledger has insufficient available balance.');
            }
            return $this->post('withdrawal:reserve:' . $locked->withdraw_id, 'withdrawal_reserved', [
                [$available, 'debit', $amount], [$hold, 'credit', $amount],
            ], Withdraw::class, $locked->id);
        }, 3);
    }

    public function restoreWithdrawal(Withdraw $withdrawal, string $currency = 'NGN'): FinancialLedgerTransaction
    {
        $currency = TicaCurrency::normalize($currency); $amount = Money::toMinor($withdrawal->amount);
        $hold = $this->account(Organizer::class, $withdrawal->organizer_id, self::HOLD, 'Payouts on hold', 'liability', 'credit', $currency);
        $available = $this->account(Organizer::class, $withdrawal->organizer_id, self::AVAILABLE, 'Available earnings', 'liability', 'credit', $currency);
        return $this->post('withdrawal:restore:' . $withdrawal->withdraw_id, 'withdrawal_restored', [
            [$hold, 'debit', $amount], [$available, 'credit', $amount],
        ], Withdraw::class, $withdrawal->id);
    }

    public function settleWithdrawal(Withdraw $withdrawal, string $currency = 'NGN'): FinancialLedgerTransaction
    {
        $currency = TicaCurrency::normalize($currency);
        $amount = Money::toMinor($withdrawal->amount);
        $payable = Money::toMinor($withdrawal->payable_amount);
        $fee = Money::toMinor($withdrawal->total_charge);
        if ($amount !== $payable + $fee) throw new LogicException('Withdrawal payout and fee do not balance.');
        $hold = $this->account(Organizer::class, $withdrawal->organizer_id, self::HOLD, 'Payouts on hold', 'liability', 'credit', $currency);
        $clearing = $this->account(null, null, self::CLEARING, 'Gateway clearing', 'asset', 'debit', $currency);
        $fees = $this->account(null, null, self::PLATFORM_FEES, 'Platform fee revenue', 'revenue', 'credit', $currency);
        $lines = [[$hold, 'debit', $amount], [$clearing, 'credit', $payable]];
        if ($fee > 0) $lines[] = [$fees, 'credit', $fee];
        return $this->post('withdrawal:paid:' . $withdrawal->withdraw_id, 'withdrawal_paid', $lines, Withdraw::class, $withdrawal->id);
    }

    public function reversePaidWithdrawal(Withdraw $withdrawal, string $currency = 'NGN'): FinancialLedgerTransaction
    {
        $currency = TicaCurrency::normalize($currency);
        $amount = Money::toMinor($withdrawal->amount); $payable = Money::toMinor($withdrawal->payable_amount);
        $fee = Money::toMinor($withdrawal->total_charge);
        $hold = $this->account(Organizer::class, $withdrawal->organizer_id, self::HOLD, 'Payouts on hold', 'liability', 'credit', $currency);
        $clearing = $this->account(null, null, self::CLEARING, 'Gateway clearing', 'asset', 'debit', $currency);
        $fees = $this->account(null, null, self::PLATFORM_FEES, 'Platform fee revenue', 'revenue', 'credit', $currency);
        $lines = [[$clearing, 'debit', $payable], [$hold, 'credit', $amount]];
        if ($fee > 0) $lines[] = [$fees, 'debit', $fee];
        return $this->post('withdrawal:reversed:' . $withdrawal->withdraw_id, 'withdrawal_reversed', $lines, Withdraw::class, $withdrawal->id);
    }

    public function recordProcessorFee(int $bookingId, string $gatewayReference, int $feeMinor): ?FinancialLedgerTransaction
    {
        if ($feeMinor < 1) return null;
        return DB::transaction(function () use ($bookingId, $gatewayReference, $feeMinor) {
            $release = OrganizerEarningRelease::where('booking_id', $bookingId)->lockForUpdate()->first();
            if (!$release) return null;
            if ((int) $release->processor_fee_minor > 0 && (int) $release->processor_fee_minor !== $feeMinor) {
                throw new LogicException('The verified processor fee conflicts with the recorded settlement fee.');
            }
            $expense = $this->account(null, null, self::PROCESSOR_FEES, 'Gateway processing fees', 'expense', 'debit', $release->currency);
            $clearing = $this->account(null, null, self::CLEARING, 'Gateway clearing', 'asset', 'debit', $release->currency);
            $transaction = $this->post('gateway-fee:paystack:' . $gatewayReference, 'gateway_fee', [
                [$expense, 'debit', $feeMinor], [$clearing, 'credit', $feeMinor],
            ], OrganizerEarningRelease::class, $release->id, ['booking_id' => $bookingId]);
            $release->forceFill(['processor_fee_minor' => $feeMinor])->save();
            return $transaction;
        }, 3);
    }

    public function hasOrganizerLedger(int $organizerId, string $currency = 'NGN'): bool
    {
        return FinancialLedgerAccount::where('owner_type', Organizer::class)
            ->where('owner_id', $organizerId)->where('currency', strtoupper($currency))->exists();
    }

    public function accountBalance(int $organizerId, string $code, string $currency = 'NGN'): int
    {
        $account = FinancialLedgerAccount::where('owner_type', Organizer::class)
            ->where('owner_id', $organizerId)->where('code', $code)
            ->where('currency', strtoupper($currency))->first();
        if (!$account) return 0;

        $debits = (int) FinancialLedgerEntry::where('financial_ledger_account_id', $account->id)
            ->where('direction', 'debit')->sum('amount_minor');
        $credits = (int) FinancialLedgerEntry::where('financial_ledger_account_id', $account->id)
            ->where('direction', 'credit')->sum('amount_minor');

        return $account->normal_balance === 'debit' ? $debits - $credits : $credits - $debits;
    }

    /** @param array<int,array{0:FinancialLedgerAccount,1:string,2:int}> $lines */
    public function post(
        string $reference,
        string $kind,
        array $lines,
        ?string $sourceType = null,
        ?int $sourceId = null,
        array $metadata = []
    ): FinancialLedgerTransaction {
        $debits = 0; $credits = 0; $currency = null;
        foreach ($lines as [$account, $direction, $amount]) {
            if (!in_array($direction, ['debit', 'credit'], true) || $amount < 1) {
                throw new LogicException('Ledger lines require a positive minor-unit amount and debit or credit direction.');
            }
            $currency ??= $account->currency;
            if ($currency !== $account->currency) throw new LogicException('A ledger posting cannot mix currencies.');
            $direction === 'debit' ? $debits += $amount : $credits += $amount;
        }
        if ($debits !== $credits) throw new LogicException('Ledger posting is not balanced.');

        $existing = FinancialLedgerTransaction::where('reference', $reference)->lockForUpdate()->first();
        if ($existing) return $existing;

        try {
            $transaction = FinancialLedgerTransaction::create([
                'public_id' => (string) Str::uuid(), 'reference' => $reference, 'kind' => $kind,
                'source_type' => $sourceType, 'source_id' => $sourceId,
                'occurred_at' => now(), 'metadata' => $metadata ?: null,
            ]);
            foreach ($lines as [$account, $direction, $amount]) {
                FinancialLedgerEntry::create([
                    'financial_ledger_transaction_id' => $transaction->id,
                    'financial_ledger_account_id' => $account->id,
                    'direction' => $direction, 'amount_minor' => $amount, 'currency' => $currency,
                ]);
            }
            return $transaction;
        } catch (QueryException $exception) {
            return FinancialLedgerTransaction::where('reference', $reference)->firstOrFail();
        }
    }

    private function account(?string $ownerType, ?int $ownerId, string $code, string $name, string $category, string $normal, string $currency): FinancialLedgerAccount
    {
        $ownerType ??= 'platform';
        $ownerId ??= 0;
        return FinancialLedgerAccount::firstOrCreate([
            'owner_type' => $ownerType, 'owner_id' => $ownerId, 'code' => $code, 'currency' => strtoupper($currency),
        ], [
            'public_id' => (string) Str::uuid(), 'name' => $name, 'category' => $category,
            'normal_balance' => $normal, 'is_active' => true,
        ]);
    }

    private function availableAt(?int $eventId): CarbonImmutable
    {
        $holdDays = max(0, (int) config('payouts.event_hold_days', 3));
        $event = $eventId ? Event::find($eventId) : null;
        if (!$event) return CarbonImmutable::now()->addDays($holdDays);

        $timezone = $event->timezone ?: config('app.timezone', 'Africa/Lagos');
        try {
            $date = $event->end_date ?: $event->start_date;
            $time = $event->end_time ?: $event->start_time ?: '23:59:59';
            return CarbonImmutable::parse($date . ' ' . $time, $timezone)->utc()->addDays($holdDays);
        } catch (\Throwable) {
            return CarbonImmutable::now()->addDays($holdDays);
        }
    }
}
