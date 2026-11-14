<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Organizer;
use App\Models\TicaPromotionCampaign;
use App\Models\TicaPromotionPlan;
use App\Models\Transaction;
use App\Support\TicaCurrency;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TicaPromotionService
{
    private const RESERVED_STATUSES = ['pending_review', 'approved', 'scheduled', 'active', 'paused', 'cancellation_requested'];

    public function createCampaign(int $organizerId, Event $event, int $planId, CarbonInterface $startsAt, array $creative): TicaPromotionCampaign
    {
        return DB::transaction(function () use ($organizerId, $event, $planId, $startsAt, $creative): TicaPromotionCampaign {
            $plan = TicaPromotionPlan::query()->whereKey($planId)->lockForUpdate()->firstOrFail();
            $this->assertPlanAvailable($plan);
            $endsAt = $startsAt->copy()->addDays((int) $plan->duration_days);
            $this->assertCapacity($plan, $startsAt, $endsAt);

            $campaign = TicaPromotionCampaign::create([
                'reference' => $this->reference(), 'organizer_id' => $organizerId, 'event_id' => $event->id,
                'plan_id' => $plan->id, 'placement' => $plan->placement,
                'status' => (float) $plan->price > 0 ? 'pending_payment' : ($plan->approval_required ? 'pending_review' : ($startsAt->isFuture() ? 'scheduled' : 'approved')),
                'payment_status' => (float) $plan->price > 0 ? 'pending' : 'paid',
                'headline' => $creative['headline'] ?? null, 'body' => $creative['body'] ?? null,
                'destination_url' => $creative['destination_url'] ?? null, 'creative_path' => $creative['creative_path'] ?? null,
                'priority' => (int) $plan->priority, 'price' => $plan->price, 'currency' => $plan->currency,
                'paid_at' => (float) $plan->price > 0 ? null : now(), 'starts_at' => $startsAt, 'ends_at' => $endsAt,
                'approved_at' => (!$plan->approval_required && (float) $plan->price <= 0) ? now() : null,
                'metadata' => ['plan_snapshot' => ['name' => $plan->name, 'type' => $plan->type, 'duration_days' => (int) $plan->duration_days, 'benefits' => $plan->benefits ?: []]],
            ]);
            AuditLogger::record('promotion.requested', $campaign, [], $campaign->only(['reference', 'status', 'payment_status', 'price', 'currency', 'starts_at', 'ends_at']));
            return $campaign;
        }, 3);
    }

    public function payFromBalance(int $campaignId, int $organizerId): TicaPromotionCampaign
    {
        return DB::transaction(function () use ($campaignId, $organizerId): TicaPromotionCampaign {
            $campaign = TicaPromotionCampaign::query()->with('plan')->whereKey($campaignId)->where('organizer_id', $organizerId)->lockForUpdate()->firstOrFail();
            if ($campaign->payment_status === 'paid' && $campaign->paid_at && $campaign->payment_transaction_id) {
                return $campaign;
            }
            if ($campaign->status !== 'pending_payment' || $campaign->payment_status !== 'pending') {
                throw ValidationException::withMessages(['campaign' => ['This promotion is not waiting for payment.']]);
            }
            if (!TicaCurrency::isBase($campaign->currency)) {
                throw ValidationException::withMessages(['campaign' => ['Promotion balance payments use the platform base currency.']]);
            }
            $plan = TicaPromotionPlan::query()->whereKey($campaign->plan_id)->lockForUpdate()->firstOrFail();
            $this->assertPlanAvailable($plan);
            $this->assertCapacity($plan, $campaign->starts_at, $campaign->ends_at, (int) $campaign->id);

            $organizer = Organizer::query()->whereKey($organizerId)->lockForUpdate()->firstOrFail();
            $amount = round((float) $campaign->price, 2);
            $before = round((float) $organizer->amount, 2);
            if ($amount <= 0 || $before < $amount) {
                throw ValidationException::withMessages(['campaign' => ['Your available TICA balance is not enough for this promotion.']]);
            }
            $organizer->forceFill(['amount' => round($before - $amount, 2)])->save();
            $transaction = Transaction::create([
                'currency' => TicaCurrency::base(), 'transcation_id' => (string) Str::uuid(), 'booking_id' => null,
                'transcation_type' => 5, 'customer_id' => null, 'organizer_id' => $organizer->id, 'payment_status' => 1,
                'payment_method' => 'TICA promotion balance', 'grand_total' => $amount, 'pre_balance' => $before,
                'after_balance' => (float) $organizer->amount, 'gateway_type' => null,
                'currency_symbol' => TicaCurrency::symbol(TicaCurrency::base()), 'currency_symbol_position' => 'left',
            ]);
            $requiresApproval = (bool) $plan->approval_required;
            $campaign->forceFill([
                'payment_transaction_id' => $transaction->id, 'payment_reference' => 'BAL-' . $transaction->id,
                'payment_status' => 'paid', 'status' => $requiresApproval ? 'pending_review' : ($campaign->starts_at?->isFuture() ? 'scheduled' : 'approved'),
                'paid_at' => now(), 'approved_at' => $requiresApproval ? null : now(),
            ])->save();
            AuditLogger::record('promotion.paid_from_organizer_balance', $campaign, ['status' => 'pending_payment', 'payment_status' => 'pending'], $campaign->only(['status', 'payment_status', 'payment_reference']), [
                'organizer_id' => $organizerId, 'amount' => $amount, 'balance_before' => $before,
                'balance_after' => (float) $organizer->amount, 'transaction_id' => $transaction->id,
            ]);
            return $campaign->fresh();
        }, 3);
    }

    public function refundToBalance(int $campaignId): TicaPromotionCampaign
    {
        return DB::transaction(function () use ($campaignId): TicaPromotionCampaign {
            $campaign = TicaPromotionCampaign::query()->whereKey($campaignId)->lockForUpdate()->firstOrFail();
            if ($campaign->payment_status === 'refunded' && $campaign->refunded_at) {
                return $campaign;
            }
            if ($campaign->payment_status !== 'paid' || !$campaign->payment_transaction_id || !$campaign->paid_at) {
                throw ValidationException::withMessages(['decision' => ['Only a verified balance payment can be refunded.']]);
            }
            $payment = Transaction::query()->whereKey($campaign->payment_transaction_id)->first();
            if (!$payment || $payment->payment_method !== 'TICA promotion balance') {
                throw ValidationException::withMessages(['decision' => ['Gateway-paid promotions must be refunded through the verified gateway refund workflow.']]);
            }
            $organizer = Organizer::query()->whereKey($campaign->organizer_id)->lockForUpdate()->firstOrFail();
            $before = round((float) $organizer->amount, 2);
            $amount = round((float) $campaign->price, 2);
            $after = round($before + $amount, 2);
            $organizer->forceFill(['amount' => $after])->save();
            $transaction = Transaction::create([
                'currency' => $campaign->currency, 'transcation_id' => (string) Str::uuid(), 'booking_id' => null,
                'transcation_type' => 4, 'customer_id' => null, 'organizer_id' => $organizer->id, 'payment_status' => 1,
                'payment_method' => 'TICA promotion refund', 'grand_total' => $amount, 'pre_balance' => $before,
                'after_balance' => $after, 'gateway_type' => null, 'currency_symbol' => TicaCurrency::symbol($campaign->currency),
                'currency_symbol_position' => 'left',
            ]);
            $beforeState = $campaign->only(['status', 'payment_status', 'refunded_at']);
            $campaign->forceFill(['status' => 'refunded', 'payment_status' => 'refunded', 'refunded_at' => now(), 'cancelled_at' => now()])->save();
            if (in_array($campaign->placement, ['featured_event', 'sponsored_event'], true)) {
                $hasOther = TicaPromotionCampaign::query()->where('event_id', $campaign->event_id)->where('id', '!=', $campaign->id)
                    ->where('status', 'active')->whereIn('placement', ['featured_event', 'sponsored_event'])->exists();
                if (!$hasOther) $campaign->event()->update(['is_featured' => 'no']);
            }
            AuditLogger::record('promotion.refunded_to_organizer_balance', $campaign, $beforeState, $campaign->only(array_keys($beforeState)), ['refund_transaction_id' => $transaction->id, 'amount' => $amount]);
            return $campaign;
        }, 3);
    }

    public function assertCapacity(TicaPromotionPlan $plan, CarbonInterface $startsAt, CarbonInterface $endsAt, ?int $ignoreCampaignId = null): void
    {
        $limit = (int) ($plan->max_concurrent ?: $plan->capacity ?: 0);
        if ($limit < 1) return;
        $occupied = TicaPromotionCampaign::query()->where('plan_id', $plan->id)
            ->when($ignoreCampaignId, fn ($query) => $query->where('id', '!=', $ignoreCampaignId))
            ->whereIn('status', self::RESERVED_STATUSES)->where('starts_at', '<', $endsAt)->where('ends_at', '>', $startsAt)->count();
        if ($occupied >= $limit) {
            throw ValidationException::withMessages(['starts_at' => ['That promotion period is fully booked. Choose another start date.']]);
        }
    }

    private function assertPlanAvailable(TicaPromotionPlan $plan): void
    {
        if (!$plan->is_active || $plan->status !== 'published' || $plan->archived_at
            || ($plan->available_from && $plan->available_from->isFuture()) || ($plan->available_until && $plan->available_until->isPast())) {
            throw ValidationException::withMessages(['plan_id' => ['This promotion package is not currently available.']]);
        }
    }

    private function reference(): string
    {
        do { $reference = 'TP-' . strtoupper(Str::random(10)); }
        while (TicaPromotionCampaign::query()->where('reference', $reference)->exists());
        return $reference;
    }
}
