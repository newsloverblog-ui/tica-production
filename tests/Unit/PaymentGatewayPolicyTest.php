<?php

namespace Tests\Unit;

use App\Services\PaymentGatewayPolicy;
use Tests\TestCase;

class PaymentGatewayPolicyTest extends TestCase
{
    public function test_it_normalizes_and_deduplicates_the_production_allowlist(): void
    {
        config()->set('payments.production_ready_gateways', [' Paystack ', 'flutterwave', 'PAYSTACK', '']);

        $this->assertSame(
            ['paystack', 'flutterwave'],
            (new PaymentGatewayPolicy())->productionReadyGateways()
        );
    }

    public function test_it_identifies_enabled_gateways_without_the_production_contract(): void
    {
        config()->set('payments.production_ready_gateways', ['paystack', 'flutterwave']);

        $this->assertSame(
            ['paypal', 'stripe'],
            (new PaymentGatewayPolicy())->unsafeEnabledGateways(['PAYSTACK', 'paypal', 'stripe', 'paypal'])
        );
    }

    public function test_production_rejects_a_gateway_outside_the_allowlist(): void
    {
        config()->set('payments.production_ready_gateways', ['paystack']);
        app()->detectEnvironment(fn () => 'production');

        $policy = new PaymentGatewayPolicy();

        $this->assertTrue($policy->canOfferOnline('paystack'));
        $this->assertFalse($policy->canOfferOnline('paypal'));
    }

    public function test_production_shop_checkout_allows_only_hardened_paystack(): void
    {
        app()->detectEnvironment(fn () => 'production');

        $policy = new PaymentGatewayPolicy();

        $this->assertSame(['paystack'], $policy->productionReadyShopGateways());
        $this->assertTrue($policy->canOfferShopOnline('paystack'));
        $this->assertFalse($policy->canOfferShopOnline('flutterwave'));
        $this->assertFalse($policy->canOfferShopOnline('paypal'));
    }

    public function test_non_production_rejects_legacy_gateways_by_default(): void
    {
        app()->detectEnvironment(fn () => 'local');
        config()->set('payments.production_ready_gateways', ['paystack', 'flutterwave']);
        config()->set('payments.allow_legacy_non_production', false);

        $policy = new PaymentGatewayPolicy();

        $this->assertTrue($policy->canOfferOnline('paystack'));
        $this->assertTrue($policy->canOfferShopOnline('paystack'));
        $this->assertFalse($policy->canOfferOnline('razorpay'));
        $this->assertFalse($policy->canOfferShopOnline('flutterwave'));
    }

    public function test_explicit_local_opt_in_can_exercise_non_retired_legacy_gateways(): void
    {
        app()->detectEnvironment(fn () => 'local');
        config()->set('payments.production_ready_gateways', ['paystack']);
        config()->set('payments.allow_legacy_non_production', true);

        $policy = new PaymentGatewayPolicy();

        $this->assertTrue($policy->canOfferOnline('stripe'));
        $this->assertTrue($policy->canOfferShopOnline('flutterwave'));
        $this->assertFalse($policy->canOfferOnline('razorpay'));
    }

    public function test_production_ignores_legacy_opt_in(): void
    {
        app()->detectEnvironment(fn () => 'production');
        config()->set('payments.production_ready_gateways', ['paystack', 'flutterwave']);
        config()->set('payments.allow_legacy_non_production', true);

        $policy = new PaymentGatewayPolicy();

        $this->assertFalse($policy->canOfferOnline('razorpay'));
        $this->assertFalse($policy->canOfferShopOnline('flutterwave'));
    }

    public function test_staging_ignores_legacy_opt_in(): void
    {
        app()->detectEnvironment(fn () => 'staging');
        config()->set('payments.production_ready_gateways', ['paystack', 'flutterwave']);
        config()->set('payments.allow_legacy_non_production', true);

        $policy = new PaymentGatewayPolicy();

        $this->assertTrue($policy->canOfferOnline('paystack'));
        $this->assertFalse($policy->canOfferOnline('razorpay'));
        $this->assertFalse($policy->canOfferShopOnline('flutterwave'));
    }

    public function test_retired_gateway_cannot_be_reenabled_in_any_environment(): void
    {
        config()->set('payments.production_ready_gateways', ['paystack', 'paypal']);
        app()->detectEnvironment(fn () => 'local');

        $policy = new PaymentGatewayPolicy();

        $this->assertContains('paypal', $policy->retiredGateways());
        $this->assertContains('razorpay', $policy->retiredGateways());
        $this->assertSame(['paystack'], $policy->productionReadyGateways());
        $this->assertFalse($policy->canOfferOnline('paypal'));
        $this->assertFalse($policy->canOfferShopOnline('paypal'));
    }

    public function test_configuration_cannot_relabel_a_legacy_gateway_as_hardened(): void
    {
        config()->set('payments.production_ready_gateways', ['paystack', 'razorpay']);
        config()->set('payments.allow_legacy_non_production', true);
        app()->detectEnvironment(fn () => 'production');

        $policy = new PaymentGatewayPolicy();

        $this->assertSame(['paystack'], $policy->productionReadyGateways());
        $this->assertSame(['razorpay'], $policy->unsafeEnabledGateways(['paystack', 'razorpay']));
        $this->assertFalse($policy->canOfferOnline('razorpay'));
    }
}
