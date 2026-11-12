<?php

namespace Tests\Unit;

use App\Services\IncidentAlertService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class PaymentIntentMonitorBoundedTest extends TestCase
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

        Schema::create('payment_intents', function (Blueprint $table): void {
            $table->id();
            $table->string('gateway');
            $table->string('reference');
            $table->unsignedBigInteger('event_id');
            $table->string('status');
            $table->decimal('amount', 18, 2);
            $table->string('currency', 8);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
        Schema::create('ticket_reservations', function (Blueprint $table): void {
            $table->id();
            $table->string('token');
            $table->string('status');
            $table->timestamp('expires_at');
        });
    }

    public function test_stale_intent_alert_reports_exact_total_with_bounded_sample(): void
    {
        foreach (range(1, 3) as $id) {
            DB::table('payment_intents')->insert([
                'gateway' => 'paystack',
                'reference' => 'stale-'.$id,
                'event_id' => 10,
                'status' => 'processing',
                'amount' => 1000,
                'currency' => 'NGN',
                'expires_at' => null,
                'created_at' => now()->subHour(),
                'updated_at' => now()->subHour(),
            ]);
        }

        $alerts = Mockery::mock(IncidentAlertService::class);
        $alerts->shouldReceive('alert')
            ->once()
            ->with(
                'payment-intents-stuck',
                'Payment intents are stuck in processing.',
                Mockery::on(function (array $context): bool {
                    return $context['count'] === 3
                        && $context['sampled_count'] === 2
                        && count($context['intents']) === 2;
                }),
                'critical'
            );
        $this->app->instance(IncidentAlertService::class, $alerts);

        $this->artisan('payments:monitor-intents', [
            '--stale-minutes' => 15,
            '--batch-size' => 2,
        ])->expectsOutputToContain('found 3 stale event and 0 stale product intent(s)')
            ->assertExitCode(1);
    }
}
