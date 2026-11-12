<?php

namespace Tests\Unit;

use App\Jobs\SendApplicationMailDelivery;
use App\Models\Admin;
use App\Models\ApplicationMailDelivery;
use App\Models\DataExport;
use App\Models\Customer;
use App\Models\Organizer;
use App\Models\ScannerStaff;
use App\Services\ConfiguredMailer;
use App\Services\DataExportReadyNotificationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Mockery;
use Tests\TestCase;

class DataExportReadyNotificationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        config()->set('app.key', 'base64:'.base64_encode(str_repeat('n', 32)));
        config()->set('app.url', 'https://tickets.example.test');
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        $this->createActors();
        (require database_path('migrations/2026_11_01_250000_create_data_exports_table.php'))->up();
        (require database_path('migrations/2026_11_05_080000_add_data_export_dispatch_metadata.php'))->up();
        (require database_path('migrations/2026_11_05_220000_add_ready_notification_metadata_to_data_exports.php'))->up();
        (require database_path('migrations/2026_11_03_150000_create_application_mail_deliveries_table.php'))->up();
        (require database_path('migrations/2026_11_05_160000_add_application_mail_dispatch_metadata.php'))->up();
        Queue::fake();
    }

    public function test_ready_export_creates_one_private_durable_mail_with_an_actor_owned_signed_link(): void
    {
        $admin = Admin::query()->create([
            'email' => 'Finance@Example.com', 'username' => 'finance', 'password' => 'unused', 'status' => 1,
        ]);
        $export = $this->readyExport('admin', $admin->id, 'bookings');

        $service = app(DataExportReadyNotificationService::class);
        $this->assertTrue($service->dispatchNotification($export->id));
        $this->assertTrue($service->dispatchNotification($export->id));

        $fresh = $export->fresh();
        $this->assertNotNull($fresh->ready_notification_delivery_id);
        $this->assertSame(1, $fresh->ready_notification_attempts);
        $this->assertDatabaseCount('application_mail_deliveries', 1);
        $delivery = ApplicationMailDelivery::query()->findOrFail($fresh->ready_notification_delivery_id);
        $this->assertSame('finance@example.com', $delivery->recipient_email);
        $this->assertSame('data_export.ready', $delivery->kind);
        $this->assertStringContainsString($export->public_id, $delivery->body);
        $this->assertStringContainsString('signature=', $delivery->body);
        $this->assertTrue(URL::hasValidSignature(
            \Illuminate\Http\Request::create(html_entity_decode(
                (string) preg_replace('/^.*href="([^"]+)".*$/s', '$1', $delivery->body)
            ))
        ));
        $raw = DB::table('application_mail_deliveries')->where('id', $delivery->id)->first();
        $this->assertStringNotContainsString('finance@example.com', (string) $raw->recipient_email);
        $this->assertStringNotContainsString($export->public_id, (string) $raw->body);
        Queue::assertPushed(SendApplicationMailDelivery::class, 1);
    }

    public function test_sent_mail_marks_the_export_notified(): void
    {
        $admin = Admin::query()->create([
            'email' => 'ops@example.com', 'username' => 'ops', 'password' => 'unused', 'status' => 1,
        ]);
        $export = $this->readyExport('admin', $admin->id, 'audit_logs');
        app(DataExportReadyNotificationService::class)->dispatchNotification($export->id);
        $delivery = $export->fresh()->readyNotificationDelivery;
        $mailer = Mockery::mock(ConfiguredMailer::class);
        $mailer->shouldReceive('sendHtml')->once();

        (new SendApplicationMailDelivery($delivery->id))->handle($mailer);

        $this->assertNotNull($export->fresh()->ready_notified_at);
        $this->assertSame('sent', $delivery->fresh()->status);
    }

    public function test_each_supported_requester_receives_its_existing_authorized_download_route(): void
    {
        $organizer = Organizer::query()->create(['email' => 'organizer@example.com']);
        $customer = Customer::query()->create(['email' => 'customer@example.com']);
        $scanner = ScannerStaff::query()->create([
            'organizer_id' => $organizer->id, 'email' => 'scanner@example.com',
        ]);
        $cases = [
            [$this->readyExport('organizer', $organizer->id, 'bookings', $organizer->id), '/organizer/event-booking/exports/'],
            [$this->readyExport('organizer', $organizer->id, 'withdrawals', $organizer->id), '/organizer/withdraw/exports/'],
            [$this->readyExport('customer', $customer->id, 'customer_personal_data'), '/customer/my-data/'],
            [$this->readyExport('scanner', $scanner->id, 'check_in_ledger', $organizer->id), '/scanner/check-in-ledger/exports/'],
        ];

        foreach ($cases as [$export, $path]) {
            $this->assertTrue(app(DataExportReadyNotificationService::class)
                ->dispatchNotification($export->id));
            $delivery = $export->fresh()->readyNotificationDelivery;
            $this->assertStringContainsString($path.$export->public_id.'/download', $delivery->body);
            $this->assertStringContainsString('signature=', $delivery->body);
        }

        $this->assertDatabaseCount('application_mail_deliveries', 4);
    }

    public function test_poller_recovers_a_ready_export_and_records_missing_actor_failure_privately(): void
    {
        $admin = Admin::query()->create([
            'email' => 'owner@example.com', 'username' => 'owner', 'password' => 'unused', 'status' => 1,
        ]);
        $recoverable = $this->readyExport('admin', $admin->id, 'transactions');
        $invalid = $this->readyExport('admin', 9999, 'bookings');

        $this->artisan('exports:notify-ready')
            ->expectsOutputToContain('Export-ready notifications attempted: 2 | failed: 1')
            ->assertFailed();

        $this->assertNotNull($recoverable->fresh()->ready_notification_delivery_id);
        $this->assertNull($invalid->fresh()->ready_notification_delivery_id);
        $this->assertSame(1, $invalid->fresh()->ready_notification_attempts);
        $this->assertStringContainsString('valid email address', $invalid->fresh()->ready_notification_last_error);
        $rawError = (string) DB::table('data_exports')->where('id', $invalid->id)
            ->value('ready_notification_last_error');
        $this->assertStringNotContainsString('valid email address', $rawError);
    }

    public function test_migration_exposes_ready_notification_contract(): void
    {
        $this->assertTrue(Schema::hasColumns('data_exports', [
            'ready_notification_delivery_id', 'ready_notification_attempts',
            'ready_notification_last_attempt_at', 'ready_notification_last_error',
            'ready_notification_alerted_at', 'ready_notified_at',
        ]));
    }

    private function readyExport(
        string $actorType,
        int $actorId,
        string $type,
        ?int $organizerId = null
    ): DataExport
    {
        return DataExport::query()->create([
            'requested_by_type' => $actorType,
            'requested_by_id' => $actorId,
            'organizer_id' => $organizerId,
            'export_type' => $type,
            'format' => 'csv',
            'status' => 'ready',
            'disk' => 'local',
            'path' => 'exports/example.csv',
            'filename' => 'tica-example.csv',
            'row_count' => 2,
            'completed_at' => now(),
            'expires_at' => now()->addDay(),
        ]);
    }

    private function createActors(): void
    {
        Schema::create('admins', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('role_id')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('username')->nullable();
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
        });
        Schema::create('organizers', function (Blueprint $table): void {
            $table->id(); $table->string('email')->nullable(); $table->timestamps();
        });
        Schema::create('customers', function (Blueprint $table): void {
            $table->id(); $table->string('email')->nullable(); $table->timestamps();
        });
        Schema::create('scanner_staff', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('organizer_id')->nullable();
            $table->string('email')->nullable(); $table->string('role')->default('supervisor');
            $table->boolean('status')->default(true); $table->timestamps();
        });
    }
}
