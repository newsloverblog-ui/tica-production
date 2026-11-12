<?php

namespace Tests\Unit;

use App\Http\Controllers\BackEnd\PaymentGateway\OfflineGatewayController;
use App\Models\PaymentGateway\OfflineGateway;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OfflineGatewayConfigurationSecurityTest extends TestCase
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

        Schema::create('offline_gateways', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->text('short_description')->nullable();
            $table->text('instructions')->nullable();
            $table->boolean('status')->default(false);
            $table->boolean('has_attachment');
            $table->unsignedMediumInteger('serial_number');
            $table->timestamps();
        });

        app('session')->start();
    }

    public function test_new_gateway_is_allowlisted_sanitized_and_inactive_by_default(): void
    {
        $request = Request::create('/admin/payment-gateways/store-offline-gateway', 'POST', [
            'name' => '  Bank transfer  ',
            'short_description' => '  Pay from your bank  ',
            'instructions' => '<p>Use reference 123</p><script>alert(1)</script>',
            'has_attachment' => '1',
            'serial_number' => '7',
            'status' => '1',
            'unexpected' => 'must-not-persist',
        ]);

        $response = (new OfflineGatewayController())->store($request);
        $gateway = OfflineGateway::firstOrFail();

        $this->assertSame(200, $response->status());
        $this->assertSame('Bank transfer', $gateway->name);
        $this->assertSame('Pay from your bank', $gateway->short_description);
        $this->assertStringNotContainsString('<script', (string) $gateway->instructions);
        $this->assertSame(1, (int) $gateway->has_attachment);
        $this->assertSame(7, (int) $gateway->serial_number);
        $this->assertSame(0, (int) $gateway->status);
        $this->assertArrayNotHasKey('unexpected', $gateway->getAttributes());
    }

    public function test_configuration_edit_cannot_bypass_the_status_transition(): void
    {
        $gateway = OfflineGateway::create([
            'name' => 'Bank transfer', 'short_description' => null,
            'instructions' => '<p>Old</p>', 'status' => 0,
            'has_attachment' => 1, 'serial_number' => 1,
        ]);

        $response = (new OfflineGatewayController())->update(Request::create('/admin/payment-gateways/update-offline-gateway', 'POST', [
            'id' => $gateway->id,
            'name' => 'Updated bank transfer',
            'short_description' => 'Updated',
            'instructions' => '<p>Updated instructions</p>',
            'has_attachment' => '0',
            'serial_number' => '2',
            'status' => '1',
        ]));

        $gateway->refresh();
        $this->assertSame(200, $response->status());
        $this->assertSame('Updated bank transfer', $gateway->name);
        $this->assertSame(0, (int) $gateway->status);
        $this->assertSame(0, (int) $gateway->has_attachment);
    }

    public function test_status_transition_accepts_only_explicit_boolean_values(): void
    {
        $gateway = OfflineGateway::create([
            'name' => 'Bank transfer', 'short_description' => null,
            'instructions' => null, 'status' => 0,
            'has_attachment' => 1, 'serial_number' => 1,
        ]);

        try {
            (new OfflineGatewayController())->updateStatus(
                Request::create('/admin/payment-gateways/update-status/'.$gateway->id, 'POST', ['status' => 'enabled']),
                $gateway->id
            );
            $this->fail('A non-boolean status should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }

        $this->assertSame(0, (int) $gateway->fresh()->status);

        (new OfflineGatewayController())->updateStatus(
            Request::create('/admin/payment-gateways/update-status/'.$gateway->id, 'POST', ['status' => '1']),
            $gateway->id
        );

        $this->assertSame(1, (int) $gateway->fresh()->status);
    }

    public function test_missing_record_actions_fail_without_a_null_dereference(): void
    {
        $response = (new OfflineGatewayController())->destroy(9999);

        $this->assertSame(302, $response->status());
        $this->assertSame('No record found in database!', session('warning'));
    }
}
