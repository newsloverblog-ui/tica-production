<?php

namespace App\Services;

use App\Models\Organizer;
use App\Models\TicaPromotionCampaign;
use App\Models\Transaction;
use App\Support\TicaCurrency;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TicaPromotionService
{
    public function payFromBalance(int $campaignId, int $organizerId): TicaPromotionCampaign
    {
        return DB::transaction(function () use ($campaignId, $organizerId): TicaPromotionCampaign {
            $campaign = TicaPromotionCampaign::query()
                ->whereKey($campaignId)
                ->where('organizer_id', $organizerId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($campaign->status !== 'pending_payment') {
                throw ValidationException::withMessages(['campaign' => ['This promotion is not waiting for payment.']]);
            }
            if (!TicaCurrency::isBase($campaign->currency)) {
                throw ValidationException::withMessages(['campaign' => ['Promotion balance payments use the platform base currency.']]);
            }

            $organizer = Organizer::query()->whereKey($organizerId)->lockForUpdate()->firstOrFail();
            $amount = round((float) $campaign->price, 2);
            $before = round((float) $organizer->amount, 2);
            if ($amount <= 0 || $before < $amount) {
                throw ValidationException::withMessages(['campaign' => ['Your available TICA balance is not enough for this promotion.']]);
            }
            $organizer->amount = round($before - $amount, 2);
            $organizer->save();

            $transaction = Transaction::create([
                'currency' => TicaCurrency::base(),
                'transcation_id' => (string) Str::uuid(),
                'booking_id' => null,
                'transcation_type' => 5,
                'customer_id' => null,
                'organizer_id' => $organizer->id,
                'payment_status' => 1,
                'payment_method' => 'TICA promotion balance',
                'grand_total' => $amount,
                'pre_balance' => $before,
                'after_balance' => (float) $organizer->amount,
                'gateway_type' => null,
                'currency_symbol' => TicaCurrency::symbol(TicaCurrency::base()),
                'currency_symbol_position' => 'left',
            ]);

            $campaign->forceFill([
                'payment_transaction_id' => $transaction->id,
                'status' => 'pending_review',
                'paid_at' => now(),
            ])->save();

            AuditLogger::record('promotion.paid_from_organizer_balance', $campaign, ['status' => 'pending_payment'], ['status' => 'pending_review'], [
                'organizer_id' => $organizerId,
                'amount' => $amount,
                'balance_before' => $before,
                'balance_after' => (float) $organizer->amount,
                'transaction_id' => $transaction->id,
            ]);

            return $campaign->fresh();
        }, 3);
    }
}
