<?php

namespace App\Services;

class PaymentGatewayPolicy
{
    private const HARDENED_EVENT_GATEWAYS = ['paystack', 'flutterwave', 'monnify', 'stripe'];

    /** The only online gateways TICA takes payments with. */
    public const TICA_GATEWAYS = ['paystack', 'monnify', 'flutterwave', 'stripe'];

    /** Every other online gateway from the original template is switched off for good. */
    public function retiredGateways(): array
    {
        return [
            'paypal', 'instamojo', 'razorpay', 'mercadopago', 'mollie', 'paytm', 'midtrans', 'iyzico',
            'paytabs', 'toyyibpay', 'phonepe', 'yoco', 'xendit', 'myfatoorah', 'perfect_money',
        ];
    }

    public function productionReadyGateways(): array
    {
        $configured = array_values(array_unique(array_filter(array_map(
            static fn ($gateway) => strtolower(trim((string) $gateway)),
            (array) config('payments.production_ready_gateways', [])
        ))));

        return array_values(array_intersect($configured, self::HARDENED_EVENT_GATEWAYS));
    }

    public function canOfferOnline(string $gateway): bool
    {
        $gateway = strtolower(trim($gateway));
        if (!in_array($gateway, self::TICA_GATEWAYS, true) || in_array($gateway, $this->retiredGateways(), true)) {
            return false;
        }
        if (in_array($gateway, $this->productionReadyGateways(), true)) {
            return true;
        }

        return $this->canExerciseLegacyGateway();
    }

    /**
     * Keep this application-owned: an operator must not be able to relabel a
     * legacy shop integration as safe with an environment variable. Paystack
     * is backed by a durable intent, authoritative stock hold, signed webhook,
     * server verification, recovery, idempotent fulfilment and provider refund.
     */
    public function productionReadyShopGateways(): array
    {
        return ['paystack'];
    }

    public function canOfferShopOnline(string $gateway): bool
    {
        $gateway = strtolower(trim($gateway));
        if (in_array($gateway, $this->retiredGateways(), true)) {
            return false;
        }
        if (in_array($gateway, $this->productionReadyShopGateways(), true)) {
            return true;
        }

        return $this->canExerciseLegacyGateway();
    }

    public function unsafeEnabledGateways(array $enabledGateways): array
    {
        $enabled = array_values(array_unique(array_filter(array_map(
            static fn ($gateway) => strtolower(trim((string) $gateway)),
            $enabledGateways
        ))));

        return array_values(array_diff($enabled, $this->productionReadyGateways()));
    }

    private function canExerciseLegacyGateway(): bool
    {
        return app()->environment(['local', 'testing'])
            && (bool) config('payments.allow_legacy_non_production', false);
    }
}
