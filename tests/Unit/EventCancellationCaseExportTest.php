<?php

namespace Tests\Unit;

use App\Http\Controllers\BackEnd\EventCancellationExportController;
use App\Jobs\GenerateEventCancellationCaseExport;
use App\Models\Admin;
use App\Models\DataExport;
use App\Services\DataExportService;
use App\Services\EventCancellationCaseExportQueryService;
use DomainException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EventCancellationCaseExportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        config()->set('app.key', 'base64:'.base64_encode(str_repeat('c', 32)));
        config()->set('exports.disk', 'local');
        config()->set('exports.retention_days', 7);
        config()->set('exports.max_rows', 250000);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        $this->createSchema();
        $this->insertCancellationRequest();
    }

    public function test_export_is_parent_bound_filtered_encrypted_snapshotted_and_queued(): void
    {
        Queue::fake();
        $this->insertCase(1, 1, 'manual_review', '=BOOKING-1', '@external-ref-1');
        $this->insertCase(2, 1, 'refund_queued', 'BOOKING-2', null);
        $this->insertCase(3, 2, 'manual_review', 'OTHER-REQUEST', null);

        $export = app(DataExportService::class)->queueEventCancellationCases(
            7, 1, ['status' => 'manual_review'], 'csv'
        );

        $this->assertSame('event_cancellation_cases', $export->export_type);
        $this->assertSame(1, $export->row_count);
        $this->assertSame(2, $export->filters['max_case_id']);
        $this->assertSame(1, $export->filters['cancellation_request_id']);
        $this->assertStringNotContainsString('manual_review', (string) DB::table('data_exports')->value('filters'));
        Queue::assertPushed(GenerateEventCancellationCaseExport::class, fn ($job) => $job->exportId === $export->id);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'data_export.queued', 'subject_id' => $export->id,
        ]);
    }

    public function test_generator_minimizes_sensitive_fields_is_formula_safe_snapshot_bound_and_idempotent(): void
    {
        Queue::fake();
        Storage::fake('local');
        $this->insertCase(1, 1, 'manual_review', '=BOOKING-1', '@external-ref-1');
        $export = app(DataExportService::class)->queueEventCancellationCases(7, 1, [], 'csv');
        $this->insertCase(2, 1, 'refund_queued', 'LATE-BOOKING', null);

        $job = new GenerateEventCancellationCaseExport($export->id);
        $job->handle(app(EventCancellationCaseExportQueryService::class));
        $csv = Storage::disk('local')->get($export->fresh()->path);

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString("'=BOOKING-1", $csv);
        $this->assertStringContainsString("'@external-ref-1", $csv);
        $this->assertStringNotContainsString('LATE-BOOKING', $csv);
        $this->assertStringNotContainsString('customer@example.test', $csv);
        $this->assertStringNotContainsString('private cancellation reason', $csv);
        $this->assertStringNotContainsString('sensitive resolution note', $csv);

        $job->handle(app(EventCancellationCaseExportQueryService::class));
        $this->assertSame(1, $export->fresh()->attempts);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'data_export.ready', 'subject_id' => $export->id,
        ]);
    }

    public function test_invalid_empty_or_overscoped_requests_fail_closed(): void
    {
        Queue::fake();
        foreach ([
            [0, 1, [], 'csv'],
            [7, 0, [], 'csv'],
            [7, 1, [], 'pdf'],
            [7, 1, ['status' => 'unknown'], 'csv'],
        ] as [$admin, $request, $filters, $format]) {
            try {
                app(DataExportService::class)->queueEventCancellationCases($admin, $request, $filters, $format);
                $this->fail('Expected rejection.');
            } catch (DomainException) {
                $this->addToAssertionCount(1);
            }
        }
        try {
            app(DataExportService::class)->queueEventCancellationCases(7, 999, [], 'csv');
            $this->fail('Unknown cancellation request must fail.');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            $this->addToAssertionCount(1);
        }
        try {
            app(DataExportService::class)->queueEventCancellationCases(7, 1, [], 'csv');
            $this->fail('Empty cancellation ledger must fail.');
        } catch (DomainException) {
            $this->addToAssertionCount(1);
        }

        $this->assertDatabaseCount('data_exports', 0);
        Queue::assertNothingPushed();
    }

    public function test_routes_require_event_and_transaction_permissions_and_downloads_are_signed_actor_owned(): void
    {
        foreach ([
            'admin.event_cancellation_exports.store',
            'admin.event_cancellation_exports.index',
            'admin.event_cancellation_exports.download',
        ] as $name) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertNotNull($route);
            $this->assertContains('permission:Event Management', $route->gatherMiddleware());
            $this->assertContains('permission:Transaction', $route->gatherMiddleware());
        }
        $this->assertSame(
            ['POST'],
            Route::getRoutes()->getByName('admin.event_cancellation_exports.store')->methods()
        );
        $this->assertContains(
            'signed',
            Route::getRoutes()->getByName('admin.event_cancellation_exports.download')->gatherMiddleware()
        );

        Storage::fake('local');
        DB::table('admins')->insert([
            ['id' => 7, 'username' => 'finance', 'status' => 1],
            ['id' => 8, 'username' => 'other', 'status' => 1],
        ]);
        $export = DataExport::create([
            'requested_by_type' => 'admin', 'requested_by_id' => 7,
            'export_type' => 'event_cancellation_cases', 'format' => 'csv',
            'status' => 'ready', 'filters' => ['cancellation_request_id' => 1],
            'disk' => 'local', 'path' => 'exports/event-cancellation-cases/test.csv',
            'filename' => 'test.csv', 'row_count' => 1, 'expires_at' => now()->addHour(),
        ]);
        Storage::disk('local')->put($export->path, 'evidence');
        Auth::guard('admin')->setUser(Admin::findOrFail(8));
        try {
            (new EventCancellationExportController())->download($export);
            $this->fail('Cross-admin download must fail.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        Auth::guard('admin')->setUser(Admin::findOrFail(7));
        $response = (new EventCancellationExportController())->download($export);
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertNotNull($export->fresh()->downloaded_at);
    }

    private function insertCancellationRequest(): void
    {
        DB::table('event_cancellation_requests')->insert([
            ['id' => 1, 'public_id' => '10000000-0000-4000-8000-000000000001', 'event_id' => 10, 'organizer_id' => 20, 'status' => 'approved', 'reason' => 'private request reason', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'public_id' => '10000000-0000-4000-8000-000000000002', 'event_id' => 11, 'organizer_id' => 21, 'status' => 'approved', 'reason' => 'other request', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    private function insertCase(
        int $id,
        int $requestId,
        string $status,
        string $bookingReference,
        ?string $externalReference
    ): void {
        DB::table('bookings')->insert([
            'id' => $id,
            'booking_id' => $bookingReference,
            'email' => 'customer@example.test',
        ]);
        DB::table('event_cancellation_cases')->insert([
            'id' => $id,
            'event_cancellation_request_id' => $requestId,
            'booking_id' => $id,
            'payment_intent_id' => 100 + $id,
            'payment_refund_id' => 200 + $id,
            'payment_refund_dispute_id' => null,
            'resolved_by_admin_id' => $externalReference ? 7 : null,
            'external_refund_reference' => $externalReference,
            'status' => $status,
            'amount' => 5000 + $id,
            'reason' => 'private cancellation reason',
            'resolution' => 'sensitive resolution note',
            'resolved_at' => $externalReference ? now() : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createSchema(): void
    {
        Schema::create('admins', fn (Blueprint $table) => [
            $table->id(), $table->unsignedBigInteger('role_id')->nullable(),
            $table->string('username'), $table->string('status')->default('1'), $table->timestamps(),
        ]);
        Schema::create('event_cancellation_requests', function (Blueprint $table): void {
            $table->id(); $table->uuid('public_id'); $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('organizer_id'); $table->string('status');
            $table->text('reason')->nullable(); $table->timestamps();
        });
        Schema::create('bookings', function (Blueprint $table): void {
            $table->id(); $table->string('booking_id')->nullable(); $table->string('email')->nullable();
        });
        Schema::create('event_cancellation_cases', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('event_cancellation_request_id');
            $table->unsignedBigInteger('booking_id'); $table->unsignedBigInteger('payment_intent_id')->nullable();
            $table->unsignedBigInteger('payment_refund_id')->nullable();
            $table->unsignedBigInteger('payment_refund_dispute_id')->nullable();
            $table->unsignedBigInteger('resolved_by_admin_id')->nullable();
            $table->string('external_refund_reference')->nullable(); $table->string('status');
            $table->decimal('amount', 18, 2); $table->text('reason')->nullable();
            $table->text('resolution')->nullable(); $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
        Schema::create('data_exports', function (Blueprint $table): void {
            $table->id(); $table->uuid('public_id')->unique(); $table->string('requested_by_type', 20);
            $table->unsignedBigInteger('requested_by_id'); $table->unsignedBigInteger('organizer_id')->nullable();
            $table->unsignedBigInteger('schedule_id')->nullable(); $table->string('export_type', 40);
            $table->string('format', 8); $table->string('status', 20); $table->longText('filters')->nullable();
            $table->string('disk', 40); $table->string('path', 500)->nullable(); $table->string('filename', 191);
            $table->unsignedBigInteger('row_count')->default(0); $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('last_error')->nullable(); $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable(); $table->timestamp('expires_at')->nullable();
            $table->timestamp('downloaded_at')->nullable(); $table->timestamps();
        });
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id(); $table->string('actor_type'); $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('action'); $table->string('subject_type')->nullable(); $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('before')->nullable(); $table->json('after')->nullable(); $table->json('metadata')->nullable();
            $table->string('request_id')->nullable(); $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable(); $table->timestamp('created_at')->nullable();
        });
    }
}
