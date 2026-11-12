<?php

namespace Tests\Unit;

use App\Exceptions\GatewayOutcomeUnknownException;
use App\Services\PaystackGatewayClient;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class PaystackGatewayClientTest extends TestCase
{
    public function test_payment_initialization_uses_tica_reference_and_authoritative_minor_units(): void
    {
        Http::fake([
            'https://api.paystack.co/transaction/initialize' => Http::response([
                'status' => true,
                'data' => [
                    'reference' => 'tica-shop-1',
                    'authorization_url' => 'https://checkout.paystack.com/example',
                ],
            ]),
        ]);

        $payload = (new PaystackGatewayClient('test-secret'))->initializeTransaction(
            'tica-shop-1', 'buyer@example.com', 10500.25, 'ngn', 'https://tica.test/product-order/paystack/notify',
            ['payment_kind' => 'product_order']
        );

        $this->assertTrue($payload['status']);
        Http::assertSent(function ($request) {
            return $request->method() === 'POST'
                && $request->url() === 'https://api.paystack.co/transaction/initialize'
                && $request['reference'] === 'tica-shop-1'
                && $request['email'] === 'buyer@example.com'
                && $request['amount'] === 1050025
                && $request['currency'] === 'NGN'
                && $request['metadata']['payment_kind'] === 'product_order';
        });
    }

    public function test_payment_initialization_server_error_is_an_unknown_outcome(): void
    {
        Http::fake([
            'https://api.paystack.co/transaction/initialize' => Http::response(['message' => 'timeout'], 503),
        ]);

        $this->expectException(GatewayOutcomeUnknownException::class);
        (new PaystackGatewayClient('test-secret'))->initializeTransaction(
            'tica-shop-2', 'buyer@example.com', 100, 'NGN', 'https://tica.test/callback'
        );
    }

    public function test_payment_initialization_rejects_checkout_urls_outside_paystack(): void
    {
        Http::fake([
            'https://api.paystack.co/transaction/initialize' => Http::response([
                'status' => true,
                'data' => [
                    'reference' => 'tica-shop-unsafe',
                    'authorization_url' => 'https://checkout.paystack.example/phishing',
                ],
            ]),
        ]);

        $this->expectException(RuntimeException::class);
        (new PaystackGatewayClient('test-secret'))->initializeTransaction(
            'tica-shop-unsafe', 'buyer@example.com', 100, 'NGN', 'https://tica.test/callback'
        );
    }

    public function test_refund_recovery_lists_by_original_provider_transaction_id(): void
    {
        Http::fake([
            'https://api.paystack.co/refund*' => Http::response([
                'status' => true,
                'data' => [[
                    'id' => 901,
                    'transaction' => 778899,
                    'amount' => 5000,
                    'currency' => 'NGN',
                    'status' => 'processing',
                ]],
            ]),
        ]);

        $payload = (new PaystackGatewayClient('test-secret'))
            ->listRefundsForTransaction('778899');

        $this->assertSame(901, $payload['data'][0]['id']);
        Http::assertSent(function ($request): bool {
            return $request->method() === 'GET'
                && str_starts_with($request->url(), 'https://api.paystack.co/refund?')
                && (string) $request['transaction'] === '778899'
                && (int) $request['page'] === 1
                && (int) $request['perPage'] === 50;
        });
    }

    public function test_transfer_uses_kobo_stable_reference_and_bearer_authentication(): void
    {
        Http::fake([
            'https://api.paystack.co/transfer' => Http::response([
                'status' => true,
                'data' => ['status' => 'pending', 'reference' => 'tica-payout-1'],
            ]),
        ]);

        $payload = (new PaystackGatewayClient('test-secret'))->initiateTransfer(
            'RCP_1', 9800, 'tica-payout-1', 'Organizer payout', 'ngn'
        );

        $this->assertTrue($payload['status']);
        Http::assertSent(function ($request) {
            return $request->method() === 'POST'
                && $request->url() === 'https://api.paystack.co/transfer'
                && $request->hasHeader('Authorization', 'Bearer test-secret')
                && $request['source'] === 'balance'
                && $request['amount'] === 980000
                && $request['recipient'] === 'RCP_1'
                && $request['reference'] === 'tica-payout-1'
                && $request['currency'] === 'NGN';
        });
    }

    public function test_server_error_after_transfer_submission_is_an_unknown_outcome(): void
    {
        Http::fake([
            'https://api.paystack.co/transfer' => Http::response(['message' => 'upstream timeout'], 503),
        ]);

        $this->expectException(GatewayOutcomeUnknownException::class);
        (new PaystackGatewayClient('test-secret'))->initiateTransfer(
            'RCP_1', 9800, 'tica-payout-2', 'Organizer payout'
        );
    }

    public function test_authoritative_client_rejection_is_not_treated_as_an_unknown_outcome(): void
    {
        Http::fake([
            'https://api.paystack.co/transfer' => Http::response([
                'status' => false, 'message' => 'Insufficient balance',
            ], 400),
        ]);

        try {
            (new PaystackGatewayClient('test-secret'))->initiateTransfer(
                'RCP_1', 9800, 'tica-payout-3', 'Organizer payout'
            );
            $this->fail('A rejected transfer must throw.');
        } catch (RuntimeException $exception) {
            $this->assertNotInstanceOf(GatewayOutcomeUnknownException::class, $exception);
            $this->assertStringContainsString('Insufficient balance', $exception->getMessage());
        }
    }
}
