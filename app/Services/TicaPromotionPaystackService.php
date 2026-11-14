<?php

namespace App\Services;

use App\Models\PaymentIntent;
use App\Models\TicaPromotionCampaign;
use App\Models\TicaPromotionPlan;
use App\Models\Transaction;
use App\Support\TicaCurrency;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class TicaPromotionPaystackService
{
    public function initialize(int $campaignId, int $organizerId, ?PaystackGatewayClient $client = null): PaymentIntent
    {
        [$campaign, $intent, $isNew] = DB::transaction(function () use ($campaignId, $organizerId): array {
            $campaign = TicaPromotionCampaign::query()->with('organizer')->whereKey($campaignId)->where('organizer_id', $organizerId)->lockForUpdate()->firstOrFail();
            if ($campaign->payment_status === 'paid') throw ValidationException::withMessages(['campaign' => ['This campaign is already paid.']]);
            if ($campaign->status !== 'pending_payment') throw ValidationException::withMessages(['campaign' => ['This campaign is not waiting for payment.']]);
            if (!in_array(strtoupper($campaign->currency), TicaCurrency::PAYSTACK, true)) throw ValidationException::withMessages(['campaign' => ['Paystack does not support this package currency.']]);
            $metadata = (array) $campaign->metadata;
            $existing = !empty($metadata['payment_intent_id'])
                ? PaymentIntent::query()->whereKey((int) $metadata['payment_intent_id'])->where('gateway', 'paystack')->first()
                : null;
            if ($existing && in_array($existing->status, ['initializing', 'initialized', 'verification_required'], true) && (!$existing->expires_at || $existing->expires_at->isFuture())) {
                return [$campaign, $existing, false];
            }
            $reference = 'tica-promo-' . Str::lower(Str::random(28));
            $intent = PaymentIntent::create([
                'gateway' => 'paystack', 'reference' => $reference, 'event_id' => $campaign->event_id, 'customer_id' => null,
                'status' => 'initializing', 'amount' => $campaign->price, 'currency' => $campaign->currency,
                'email' => $campaign->organizer->email,
                'payload' => ['payment_kind' => 'promotion', 'campaign_id' => $campaign->id, 'organizer_id' => $organizerId, 'price' => (float) $campaign->price, 'tax' => 0, 'currencyText' => $campaign->currency, 'email' => $campaign->organizer->email],
                'expires_at' => now()->addDay(),
            ]);
            $metadata['payment_intent_id'] = $intent->id;
            $campaign->forceFill(['metadata' => $metadata])->save();
            return [$campaign, $intent, true];
        }, 3);
        if (!$isNew) return $intent;
        $reference = $intent->reference;
        try {
            $response = ($client ?: new PaystackGatewayClient())->initializeTransaction(
                $reference, (string) $campaign->organizer->email, (float) $campaign->price, (string) $campaign->currency,
                route('tica.promotion.paystack.callback'), ['payment_kind' => 'promotion', 'campaign_id' => $campaign->id]
            );
            if ((string) data_get($response, 'data.reference') !== $reference) throw new RuntimeException('Paystack returned a different transaction reference.');
            $intent->forceFill(['status' => 'initialized', 'authorization_url' => data_get($response, 'data.authorization_url'), 'last_error' => null])->save();
            AuditLogger::record('promotion.gateway_payment_initialized', $campaign, [], ['payment_status' => 'pending'], ['gateway' => 'paystack', 'reference' => $reference]);
            return $intent->fresh();
        } catch (Throwable $exception) {
            $intent->forceFill(['status' => 'failed', 'last_error' => mb_substr($exception->getMessage(), 0, 1000)])->save();
            throw $exception;
        }
    }

    public function verifyReference(string $reference, ?PaystackGatewayClient $client = null): TicaPromotionCampaign
    {
        $intent = $this->promotionIntent($reference);
        if ($intent->status === 'completed') return TicaPromotionCampaign::query()->whereKey((int) $intent->payload['campaign_id'])->firstOrFail();
        $payload = ($client ?: new PaystackGatewayClient())->verifyTransaction($reference);
        return $this->acceptVerifiedPayment($intent, (array) ($payload['data'] ?? []));
    }

    public function acceptWebhook(array $gatewayData): TicaPromotionCampaign
    {
        $reference = (string) ($gatewayData['reference'] ?? '');
        return $this->acceptVerifiedPayment($this->promotionIntent($reference), $gatewayData);
    }

    public function requestRefund(int $campaignId, string $reason, ?PaystackGatewayClient $client = null): TicaPromotionCampaign
    {
        $campaign = TicaPromotionCampaign::query()->with('paymentTransaction')->whereKey($campaignId)->firstOrFail();
        $payment = $campaign->paymentTransaction;
        if ($campaign->payment_status === 'refunded') return $campaign;
        if ($campaign->payment_status === 'refund_pending') return $campaign;
        if ($campaign->payment_status !== 'paid' || !$payment || $payment->gateway !== 'paystack' || !$payment->gateway_reference) {
            throw ValidationException::withMessages(['decision' => ['This campaign does not have a refundable Paystack payment.']]);
        }
        $response = ($client ?: new PaystackGatewayClient())->createRefund($payment->gateway_reference, (string) $campaign->price, (string) $campaign->currency, $reason ?: 'TICA promotion refund');
        $metadata = (array) $campaign->metadata;
        $metadata['refund'] = ['provider' => 'paystack', 'id' => (string) data_get($response, 'data.id'), 'status' => (string) data_get($response, 'data.status', 'pending')];
        $before = $campaign->only(['status', 'payment_status']);
        $campaign->forceFill(['status' => 'cancelled', 'payment_status' => 'refund_pending', 'cancelled_at' => now(), 'metadata' => $metadata])->save();
        AuditLogger::record('promotion.gateway_refund_requested', $campaign, $before, $campaign->only(array_keys($before)), ['gateway' => 'paystack', 'gateway_reference' => $payment->gateway_reference]);
        return $campaign;
    }

    public function acceptRefundWebhook(string $eventName, array $data): ?TicaPromotionCampaign
    {
        $reference = (string) (data_get($data, 'transaction.reference') ?: data_get($data, 'transaction_reference') ?: data_get($data, 'reference'));
        if ($reference === '') return null;
        $campaign = TicaPromotionCampaign::query()->where('payment_reference', $reference)->first();
        if (!$campaign) return null;
        $status = strtolower((string) ($data['status'] ?? ''));
        if ($eventName === 'refund.processed' || in_array($status, ['processed', 'success', 'succeeded'], true)) {
            $before = $campaign->only(['status', 'payment_status', 'refunded_at']);
            $campaign->forceFill(['status' => 'refunded', 'payment_status' => 'refunded', 'refunded_at' => now()])->save();
            AuditLogger::record('promotion.gateway_refund_completed', $campaign, $before, $campaign->only(array_keys($before)), ['gateway' => 'paystack', 'gateway_reference' => $reference]);
            app(TicaPromotionNotificationService::class)->send($campaign, 'refunded', 'Promotion payment refunded', 'Paystack confirmed your promotion refund. Bank processing times may vary.');
        } elseif (in_array($eventName, ['refund.failed', 'refund.error'], true) || in_array($status, ['failed', 'error'], true)) {
            $campaign->forceFill(['payment_status' => 'paid'])->save();
            AuditLogger::record('promotion.gateway_refund_failed', $campaign, ['payment_status' => 'refund_pending'], ['payment_status' => 'paid'], ['gateway' => 'paystack', 'gateway_reference' => $reference]);
        }
        return $campaign->fresh();
    }

    private function acceptVerifiedPayment(PaymentIntent $intent, array $gatewayData): TicaPromotionCampaign
    {
        $errors = PaystackPaymentVerifier::errors($gatewayData, (array) $intent->payload);
        if ($errors) {
            $intent->forceFill(['status' => 'failed', 'last_error' => implode(',', $errors)])->save();
            throw ValidationException::withMessages(['payment' => ['Paystack verification failed: ' . implode(', ', $errors)]]);
        }
        return DB::transaction(function () use ($intent, $gatewayData): TicaPromotionCampaign {
            $lockedIntent = PaymentIntent::query()->whereKey($intent->id)->lockForUpdate()->firstOrFail();
            $campaign = TicaPromotionCampaign::query()->whereKey((int) $lockedIntent->payload['campaign_id'])->lockForUpdate()->firstOrFail();
            if ($campaign->payment_status === 'paid' && $campaign->payment_reference === $lockedIntent->reference) return $campaign;
            if ($campaign->status !== 'pending_payment' || $campaign->payment_status !== 'pending') throw ValidationException::withMessages(['payment' => ['Campaign payment is no longer pending.']]);
            $plan = TicaPromotionPlan::query()->whereKey($campaign->plan_id)->lockForUpdate()->firstOrFail();
            app(TicaPromotionService::class)->assertCapacity($plan, $campaign->starts_at, $campaign->ends_at, $campaign->id);
            $transaction = Transaction::query()->firstOrCreate(
                ['gateway' => 'paystack', 'gateway_reference' => $lockedIntent->reference],
                [
                    'currency' => $campaign->currency, 'transcation_id' => $lockedIntent->reference, 'booking_id' => null,
                    'transcation_type' => 8, 'customer_id' => null, 'organizer_id' => $campaign->organizer_id,
                    'payment_status' => 1, 'payment_method' => 'Paystack promotion', 'grand_total' => $campaign->price,
                    'pre_balance' => null, 'after_balance' => null, 'gateway_type' => 'online',
                    'currency_symbol' => TicaCurrency::symbol($campaign->currency), 'currency_symbol_position' => 'left',
                ]
            );
            $campaign->forceFill([
                'payment_transaction_id' => $transaction->id, 'payment_reference' => $lockedIntent->reference,
                'payment_status' => 'paid', 'status' => $plan->approval_required ? 'pending_review' : ($campaign->starts_at?->isFuture() ? 'scheduled' : 'approved'),
                'paid_at' => now(), 'approved_at' => $plan->approval_required ? null : now(),
            ])->save();
            $lockedIntent->forceFill([
                'status' => 'completed', 'gateway_transaction_id' => (string) ($gatewayData['id'] ?? ''),
                'processed_at' => now(), 'last_error' => null,
            ])->save();
            AuditLogger::record('promotion.gateway_payment_verified', $campaign, ['payment_status' => 'pending'], ['payment_status' => 'paid', 'status' => $campaign->status], ['gateway' => 'paystack', 'reference' => $lockedIntent->reference]);
            DB::afterCommit(fn () => app(TicaPromotionNotificationService::class)->send($campaign, 'payment_confirmed', 'Promotion payment confirmed', 'Paystack verified your payment. The campaign will not appear publicly until its approval and start conditions are satisfied.'));
            return $campaign->fresh();
        }, 3);
    }

    private function promotionIntent(string $reference): PaymentIntent
    {
        $intent = PaymentIntent::query()->where('gateway', 'paystack')->where('reference', $reference)->firstOrFail();
        if (($intent->payload['payment_kind'] ?? null) !== 'promotion') throw new RuntimeException('The payment reference is not a promotion payment.');
        return $intent;
    }
}
