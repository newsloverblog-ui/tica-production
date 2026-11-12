<?php

namespace Tests\Unit;

use App\Services\FlutterwaveGatewayClient;
use App\Exceptions\GatewayOutcomeUnknownException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class FlutterwaveGatewayClientTest extends TestCase
{
    public function test_initialize_uses_v3_api_and_accepts_only_flutterwave_checkout_host(): void
    {
        Http::fake([
            'https://api.flutterwave.com/v3/payments' => Http::response([
                'status' => 'success',
                'data' => ['link' => 'https://checkout.flutterwave.com/v3/hosted/pay/example'],
            ]),
        ]);

        $payload = (new FlutterwaveGatewayClient('test-secret'))->initialize([
            'tx_ref' => 'tica-flw-1', 'amount' => '100.00', 'currency' => 'NGN',
        ]);

        $this->assertSame('https://checkout.flutterwave.com/v3/hosted/pay/example', $payload['data']['link']);
        Http::assertSent(fn ($request) => $request->url() === 'https://api.flutterwave.com/v3/payments'
            && $request->hasHeader('Authorization', 'Bearer test-secret'));
    }

    public function test_initialize_rejects_a_success_response_with_untrusted_redirect_host(): void
    {
        Http::fake([
            'https://api.flutterwave.com/v3/payments' => Http::response([
                'status' => 'success',
                'data' => ['link' => 'https://attacker.example/pay'],
            ]),
        ]);

        $this->expectException(RuntimeException::class);
        (new FlutterwaveGatewayClient('test-secret'))->initialize(['tx_ref' => 'tica-flw-2']);
    }

    public function test_initialize_server_failure_is_treated_as_an_unknown_outcome(): void
    {
        Http::fake([
            'https://api.flutterwave.com/v3/payments' => Http::response([
                'status' => 'error', 'message' => 'temporarily unavailable',
            ], 503),
        ]);

        $this->expectException(GatewayOutcomeUnknownException::class);
        (new FlutterwaveGatewayClient('test-secret'))->initialize([
            'tx_ref' => 'tica-flw-unknown', 'amount' => '100.00', 'currency' => 'NGN',
        ]);
    }

    public function test_event_controller_never_trusts_browser_status_or_legacy_reference_only_ledger_lookup(): void
    {
        $source = (string) file_get_contents(app_path(
            'Http/Controllers/FrontEnd/PaymentGateway/FlutterwaveController.php'
        ));

        $this->assertStringNotContainsString("query('status'", $source);
        $this->assertStringNotContainsString("Transaction::where('transcation_id'", $source);
        $this->assertStringContainsString("Transaction::where('gateway', 'flutterwave')", $source);
        $this->assertStringContainsString("->where('gateway_reference', \$intent->reference)", $source);
        $this->assertStringContainsString("'status' => 'verification_required'", $source);
    }

    public function test_verify_uses_transaction_id_and_requires_structured_success(): void
    {
        Http::fake([
            'https://api.flutterwave.com/v3/transactions/123/verify' => Http::response([
                'status' => 'success',
                'data' => ['id' => 123, 'status' => 'successful'],
            ]),
        ]);

        $payload = (new FlutterwaveGatewayClient('test-secret'))->verifyTransaction(123);

        $this->assertSame(123, $payload['data']['id']);
        Http::assertSent(fn ($request) => $request->method() === 'GET'
            && $request->url() === 'https://api.flutterwave.com/v3/transactions/123/verify');
    }

    public function test_recovery_can_verify_by_merchant_reference(): void
    {
        Http::fake([
            'https://api.flutterwave.com/v3/transactions/verify_by_reference*' => Http::response([
                'status' => 'success',
                'data' => ['id' => 456, 'tx_ref' => 'tica-flw-recovery', 'status' => 'successful'],
            ]),
        ]);

        $payload = (new FlutterwaveGatewayClient('test-secret'))->verifyByReference('tica-flw-recovery');

        $this->assertSame(456, $payload['data']['id']);
        Http::assertSent(fn ($request) => $request->url() === 'https://api.flutterwave.com/v3/transactions/verify_by_reference?tx_ref=tica-flw-recovery');
    }

    public function test_reconciliation_lists_only_successful_transactions_for_the_requested_day(): void
    {
        Http::fake([
            'https://api.flutterwave.com/v3/transactions*' => Http::response([
                'status' => 'success',
                'data' => [['id' => 77, 'tx_ref' => 'tica-flw-reconcile', 'status' => 'successful']],
                'meta' => ['page_info' => ['current_page' => 2, 'total_pages' => 3]],
            ]),
        ]);

        $payload = (new FlutterwaveGatewayClient('test-secret'))->listTransactions(
            CarbonImmutable::parse('2026-11-01'),
            CarbonImmutable::parse('2026-11-01'),
            2
        );

        $this->assertSame('tica-flw-reconcile', $payload['data'][0]['tx_ref']);
        Http::assertSent(fn ($request) => $request->method() === 'GET'
            && str_starts_with($request->url(), 'https://api.flutterwave.com/v3/transactions?')
            && $request['from'] === '2026-11-01'
            && $request['to'] === '2026-11-01'
            && $request['page'] === 2
            && $request['status'] === 'successful');
    }

    public function test_refund_submission_uses_verified_transaction_id_and_major_currency_units(): void
    {
        Http::fake([
            'https://api.flutterwave.com/v3/transactions/456/refund' => Http::response([
                'status' => 'success',
                'message' => 'Transaction refund initiated',
                'data' => ['id' => 987, 'status' => 'completed', 'amount_refunded' => 150.25],
            ]),
        ]);

        $payload = (new FlutterwaveGatewayClient('test-secret'))->createRefund(
            456,
            '150.25',
            'Customer cancellation approved.'
        );

        $this->assertSame(987, $payload['data']['id']);
        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && $request->url() === 'https://api.flutterwave.com/v3/transactions/456/refund'
            && $request['amount'] === 150.25
            && $request['comments'] === 'Customer cancellation approved.');
    }

    public function test_refund_server_failure_is_treated_as_an_unknown_outcome(): void
    {
        Http::fake([
            'https://api.flutterwave.com/v3/transactions/456/refund' => Http::response([
                'status' => 'error', 'message' => 'temporarily unavailable',
            ], 503),
        ]);

        $this->expectException(GatewayOutcomeUnknownException::class);
        (new FlutterwaveGatewayClient('test-secret'))->createRefund(456, '100.00', 'Approved refund.');
    }

    public function test_refund_reconciliation_fetches_the_provider_refund_id(): void
    {
        Http::fake([
            'https://api.flutterwave.com/v3/refunds/987' => Http::response([
                'status' => 'success',
                'data' => ['id' => 987, 'status' => 'completed-mpgs'],
            ]),
        ]);

        $payload = (new FlutterwaveGatewayClient('test-secret'))->fetchRefund('987');

        $this->assertSame('completed-mpgs', $payload['data']['status']);
    }
}
