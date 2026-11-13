<?php

namespace Tests\Unit;

use App\Models\ApplicationMailDelivery;
use App\Support\LifecycleMailCatalog;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DispatchLifecycleNotificationPreferencesTest extends TestCase
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

        Schema::create('organizers', function (Blueprint $table): void {
            $table->id();
            $table->string('email')->nullable();
            $table->boolean('status')->default(true);
            $table->json('preferences')->nullable();
            $table->timestamps();
        });
        Schema::create('bookings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('organizer_id');
            $table->string('paymentStatus')->nullable();
            $table->unsignedInteger('quantity')->default(0);
            $table->decimal('price', 12, 2)->default(0);
            $table->decimal('tax', 12, 2)->default(0);
            $table->decimal('refunded_amount', 12, 2)->default(0);
            $table->timestamps();
        });
        (require database_path('migrations/2026_11_03_150000_create_application_mail_deliveries_table.php'))->up();
        (require database_path('migrations/2026_11_05_160000_add_application_mail_dispatch_metadata.php'))->up();
        (require database_path('migrations/2026_11_10_121000_add_dedupe_key_to_application_mail_deliveries.php'))->up();
        Queue::fake();
    }

    public function test_daily_and_weekly_digests_only_queue_for_opted_in_organizers(): void
    {
        DB::table('organizers')->insert([
            [
                'email' => 'summaries@example.test', 'status' => true,
                'preferences' => json_encode(['daily_summary' => true]),
                'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'email' => 'no-summaries@example.test', 'status' => true,
                'preferences' => json_encode(['daily_summary' => false]),
                'created_at' => now(), 'updated_at' => now(),
            ],
        ]);

        $this->artisan('notifications:dispatch-lifecycle', ['--mode' => 'daily'])
            ->expectsOutput('Queued 1 lifecycle notification(s).')
            ->assertSuccessful();
        $this->artisan('notifications:dispatch-lifecycle', ['--mode' => 'weekly'])
            ->expectsOutput('Queued 1 lifecycle notification(s).')
            ->assertSuccessful();

        $deliveries = ApplicationMailDelivery::query()
            ->where('kind', LifecycleMailCatalog::ORGANIZER_SALES_SUMMARY)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $deliveries);
        $this->assertSame(['summaries@example.test', 'summaries@example.test'], $deliveries->pluck('recipient_email')->all());
    }
}
