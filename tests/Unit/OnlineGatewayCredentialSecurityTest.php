<?php

namespace Tests\Unit;

use App\Http\Controllers\BackEnd\PaymentGateway\OnlineGatewayController;
use App\Models\PaymentGateway\OnlineGateway;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OnlineGatewayCredentialSecurityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');

        Schema::create('online_gateways', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('keyword')->unique();
            $table->longText('information');
            $table->unsignedTinyInteger('status')->default(0);
        });
    }

    public function test_model_encrypts_gateway_information_at_rest_and_reads_legacy_plaintext(): void
    {
        $gateway = OnlineGateway::create([
            'name' => 'Paystack',
            'keyword' => 'paystack',
            'information' => json_encode(['key' => 'sk_live_sensitive']),
            'status' => 1,
        ]);

        $raw = (string) DB::table('online_gateways')->where('id', $gateway->id)->value('information');
        $this->assertStringStartsWith(OnlineGateway::ENCRYPTED_INFORMATION_PREFIX, $raw);
        $this->assertStringNotContainsString('sk_live_sensitive', $raw);
        $this->assertSame('sk_live_sensitive', json_decode($gateway->fresh()->information, true)['key']);

        DB::table('online_gateways')->insert([
            'name' => 'Legacy', 'keyword' => 'legacy',
            'information' => json_encode(['token' => 'legacy-token']), 'status' => 0,
        ]);
        $this->assertSame(
            'legacy-token',
            json_decode(OnlineGateway::whereKeyword('legacy')->firstOrFail()->information, true)['token']
        );
    }

    public function test_encryption_command_is_dry_run_first_and_idempotent(): void
    {
        DB::table('online_gateways')->insert([
            'name' => 'Paystack', 'keyword' => 'paystack',
            'information' => json_encode(['key' => 'secret']), 'status' => 1,
        ]);

        $this->artisan('gateways:encrypt-credentials', ['--json' => true])
            ->expectsOutputToContain('"pending": 1')
            ->assertSuccessful();
        $this->assertFalse(OnlineGateway::informationIsEncrypted(
            DB::table('online_gateways')->value('information')
        ));

        $this->artisan('gateways:encrypt-credentials', ['--apply' => true, '--json' => true])
            ->expectsOutputToContain('"encrypted": 1')
            ->assertSuccessful();
        $raw = (string) DB::table('online_gateways')->value('information');
        $this->assertTrue(OnlineGateway::informationIsEncrypted($raw));
        $this->assertSame('secret', json_decode(OnlineGateway::firstOrFail()->information, true)['key']);

        $this->artisan('gateways:encrypt-credentials', ['--apply' => true, '--json' => true])
            ->expectsOutputToContain('"encrypted": 0')
            ->assertSuccessful();
    }

    public function test_hardened_gateway_forms_never_render_saved_secret_values(): void
    {
        $view = (string) file_get_contents(resource_path('views/backend/payment-gateways/online-gateways.blade.php'));

        $this->assertStringContainsString('name="paystack_key" value=""', $view);
        $this->assertStringContainsString('name="flutterwave_secret_key" value=""', $view);
        $this->assertStringContainsString('Transactional gateway policy', $view);
        $this->assertStringContainsString('Legacy gateway activation is blocked in this environment.', $view);
        $this->assertStringNotContainsString('value="{{ $paystackInfo[\'key\'] }}"', $view);
        $this->assertStringNotContainsString('value="{{ $flutterwaveInfo[\'secret_key\'] }}"', $view);

        foreach ([
            'monnify_api_key', 'monnify_secret_key', 'stripe_secret', 'stripe_webhook_secret',
        ] as $name) {
            $this->assertMatchesRegularExpression(
                '/<input[^>]+type="password"[^>]+name="'.preg_quote($name, '/').'"[^>]+value=""/s',
                $view,
                "{$name} must never be rendered back to an administrator."
            );
        }

    }

    public function test_blank_hardened_gateway_secret_keeps_existing_credential(): void
    {
        OnlineGateway::create([
            'name' => 'Paystack', 'keyword' => 'paystack',
            'information' => json_encode(['key' => 'paystack-existing']), 'status' => 1,
        ]);
        OnlineGateway::create([
            'name' => 'Flutterwave', 'keyword' => 'flutterwave',
            'information' => json_encode(['public_key' => 'flw-public', 'secret_key' => 'flw-existing']), 'status' => 1,
        ]);
        OnlineGateway::create([
            'name' => 'Stripe', 'keyword' => 'stripe',
            'information' => json_encode(['key' => 'pk_test_existing', 'secret' => 'sk_test_existing']), 'status' => 1,
        ]);

        app('session')->start();
        $controller = new OnlineGatewayController();
        $controller->updatePaystackInfo(Request::create('/admin/payment-gateways/update-paystack-info', 'POST', [
            'paystack_status' => '0', 'paystack_key' => '',
        ]));
        $controller->updateFlutterwaveInfo(Request::create('/admin/payment-gateways/update-flutterwave-info', 'POST', [
            'flutterwave_status' => '0', 'flutterwave_public_key' => 'flw-public-rotated',
            'flutterwave_secret_key' => '',
        ]));
        $controller->updateStripeInfo(Request::create('/admin/payment-gateways/update-stripe-info', 'POST', [
            'stripe_status' => '0', 'stripe_key' => 'pk_test_rotated', 'stripe_secret' => '',
        ]));

        $paystack = OnlineGateway::whereKeyword('paystack')->firstOrFail();
        $flutterwave = OnlineGateway::whereKeyword('flutterwave')->firstOrFail();
        $stripe = OnlineGateway::whereKeyword('stripe')->firstOrFail();
        $this->assertSame('paystack-existing', json_decode($paystack->information, true)['key']);
        $this->assertSame('flw-existing', json_decode($flutterwave->information, true)['secret_key']);
        $this->assertSame('flw-public-rotated', json_decode($flutterwave->information, true)['public_key']);
        $this->assertSame('sk_test_existing', json_decode($stripe->information, true)['secret']);
        $this->assertSame('pk_test_rotated', json_decode($stripe->information, true)['key']);
        $this->assertSame(0, (int) $paystack->status);
        $this->assertSame(0, (int) $flutterwave->status);
        $this->assertSame(0, (int) $stripe->status);
    }

    public function test_legacy_gateway_cannot_be_activated_without_local_development_opt_in(): void
    {
        OnlineGateway::create([
            'name' => 'Razorpay', 'keyword' => 'razorpay',
            'information' => json_encode(['key' => 'rzp-key', 'secret' => 'rzp-secret']), 'status' => 0,
        ]);
        config()->set('payments.allow_legacy_non_production', false);

        app('session')->start();

        try {
            (new OnlineGatewayController())->updateRazorpayInfo(Request::create('/admin/payment-gateways/update-razorpay-info', 'POST', [
                'razorpay_status' => '1', 'razorpay_key' => 'rzp-key', 'razorpay_secret' => 'rzp-secret',
            ]));
            $this->fail('Legacy gateway activation should fail closed.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('razorpay_status', $exception->errors());
        }

        $this->assertSame(0, (int) OnlineGateway::whereKeyword('razorpay')->value('status'));
    }

    public function test_permanently_retired_gateway_cannot_be_reactivated_by_testing_opt_in(): void
    {
        OnlineGateway::create([
            'name' => 'Razorpay', 'keyword' => 'razorpay',
            'information' => json_encode(['key' => 'rzp-key', 'secret' => 'rzp-secret']), 'status' => 0,
        ]);
        config()->set('payments.allow_legacy_non_production', true);

        app('session')->start();
        $this->expectException(ValidationException::class);

        (new OnlineGatewayController())->updateRazorpayInfo(Request::create('/admin/payment-gateways/update-razorpay-info', 'POST', [
            'razorpay_status' => '1', 'razorpay_key' => 'rzp-key', 'razorpay_secret' => 'rzp-secret',
        ]));
    }

    public function test_gateway_activation_status_rejects_non_boolean_values(): void
    {
        OnlineGateway::create([
            'name' => 'Paystack', 'keyword' => 'paystack',
            'information' => json_encode(['key' => 'paystack-key']), 'status' => 0,
        ]);

        app('session')->start();
        (new OnlineGatewayController())->updatePaystackInfo(Request::create('/admin/payment-gateways/update-paystack-info', 'POST', [
            'paystack_status' => 'enabled', 'paystack_key' => 'paystack-key',
        ]));

        $this->assertSame(0, (int) OnlineGateway::whereKeyword('paystack')->value('status'));
        $this->assertTrue(session('errors')->has('paystack_status'));
    }
}
