<?php

namespace Tests\Unit;

use App\Jobs\GenerateGuestListExport;
use App\Jobs\ProcessEventGuestImport;
use App\Jobs\SendEventGuestInvitation;
use App\Models\Event;
use App\Models\EventGuestImport;
use App\Models\EventGuestInvitation;
use App\Services\DataExportService;
use App\Services\EventGuestImportService;
use App\Services\EventGuestListService;
use App\Services\GuestInvitationExportQueryService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EventGuestOperationsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ]);
        config()->set('app.key', 'base64:'.base64_encode(str_repeat('g', 32)));
        config()->set('tickets.guest_import_disk', 'local');
        config()->set('tickets.guest_import_max_rows', 100);
        config()->set('tickets.guest_import_max_columns', 10);
        config()->set('tickets.guest_import_retention_days', 7);
        config()->set('tickets.guest_retention_days', 90);
        config()->set('exports.disk', 'local');
        config()->set('exports.retention_days', 7);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        $this->schema();
    }

    public function test_private_guest_import_reports_rows_deletes_source_and_is_idempotent(): void
    {
        Storage::fake('local');
        Queue::fake();
        $event = $this->event(12, now()->addMonth());
        $csv = implode("\n", [
            'name,email,party_size',
            'Ada Guest,ada@example.com,2',
            'Bad Guest,not-an-email,1',
            'Duplicate Guest,ada@example.com,1',
        ])."\n";

        $import = app(EventGuestImportService::class)->queue($event, 12,
            UploadedFile::fake()->createWithContent('private-list.csv', $csv), true);
        Storage::disk('local')->assertExists($import->path);
        Queue::assertPushed(ProcessEventGuestImport::class);

        (new ProcessEventGuestImport($import->id))->handle(app(EventGuestListService::class));
        $ready = $import->fresh();
        $this->assertSame('completed_with_errors', $ready->status);
        $this->assertSame(1, $ready->accepted_rows);
        $this->assertSame(2, $ready->rejected_rows);
        $this->assertNotNull($ready->source_deleted_at);
        Storage::disk('local')->assertMissing($ready->path);
        $this->assertDatabaseCount('event_guest_invitations', 1);
        $this->assertDatabaseHas('event_guest_import_rows', ['row_number' => 3, 'status' => 'rejected']);
        $this->assertDatabaseHas('event_guest_import_rows', ['row_number' => 4, 'error_code' => 'duplicate_email']);
        $this->assertDatabaseMissing('event_guest_import_rows', ['email_fingerprint' => 'ada@example.com']);
        Queue::assertPushed(SendEventGuestInvitation::class);

        Storage::disk('local')->put($ready->path, $csv);
        $ready->forceFill(['status' => 'failed', 'source_deleted_at' => null, 'completed_at' => null])->save();
        (new ProcessEventGuestImport($ready->id))->handle(app(EventGuestListService::class));
        $this->assertDatabaseCount('event_guest_invitations', 1);
        $this->assertSame('completed_with_errors', $ready->fresh()->status);
    }

    public function test_guest_export_is_private_snapshot_scoped_and_formula_safe(): void
    {
        Storage::fake('local');
        Queue::fake();
        $event = $this->event(20, now()->addMonth(), 'Export Event');
        $invitation = app(EventGuestListService::class)->invite($event, 20, [
            'name' => '=Unsafe Guest', 'email' => 'export@example.com', 'party_size' => 3, 'source_attested' => true,
        ]);

        $export = app(DataExportService::class)->queueGuestList(20, $event->id, [], 'csv', true);
        $this->assertSame('event_guest_list', $export->export_type);
        $this->assertSame($invitation->id, $export->filters['max_invitation_id']);
        Queue::assertPushed(GenerateGuestListExport::class);
        (new GenerateGuestListExport($export->id))->handle(app(GuestInvitationExportQueryService::class));

        $ready = $export->fresh();
        $this->assertSame('ready', $ready->status);
        Storage::disk('local')->assertExists($ready->path);
        $contents = Storage::disk('local')->get($ready->path);
        $this->assertStringContainsString("'=Unsafe Guest", $contents);
        $this->assertStringContainsString('export@example.com', $contents);

        EventGuestInvitation::whereKey($invitation->id)->update(['anonymized_at' => now()]);
        $this->expectException(\DomainException::class);
        app(DataExportService::class)->queueGuestList(20, $event->id, [], 'csv', true);
    }

    public function test_guest_retention_dry_run_skips_unresolved_delivery_and_anonymizes_terminal_contacts(): void
    {
        Queue::fake();
        $past = $this->event(30, now()->subDays(120));
        $eligible = $this->invitation($past, 'declined', 'sent', 120, 'eligible@example.com');
        $unresolved = $this->invitation($past, 'cancelled', 'failed', 120, 'failed@example.com');
        $accepted = $this->invitation($past, 'accepted', 'sent', 120, 'accepted@example.com');

        $this->artisan('guests:apply-retention --days=90')->assertSuccessful();
        $this->assertNull($eligible->fresh()->anonymized_at);
        $this->artisan('guests:apply-retention --apply --days=90')->assertSuccessful();

        $this->assertSame('Anonymized guest', $eligible->fresh()->name);
        $this->assertNotNull($eligible->fresh()->anonymized_at);
        $this->assertNotNull($accepted->fresh()->anonymized_at);
        $this->assertNull($unresolved->fresh()->anonymized_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'event.guest_invitation_anonymized']);
    }

    private function event(int $organizerId, $end, string $title = 'Guest Event'): Event
    {
        $event = Event::create([
            'organizer_id' => $organizerId, 'status' => 1, 'lifecycle_status' => 'scheduled',
            'end_date' => $end->toDateString(), 'end_date_time' => $end,
        ]);
        DB::table('event_contents')->insert(['event_id' => $event->id, 'language_id' => 1,
            'title' => $title, 'slug' => 'guest-event-'.$event->id, 'created_at' => now(), 'updated_at' => now()]);
        return $event;
    }

    private function invitation(Event $event, string $status, string $delivery, int $ageDays, string $email): EventGuestInvitation
    {
        Queue::fake();
        $invitation = app(EventGuestListService::class)->invite($event, (int) $event->organizer_id, [
            'name' => 'Private Guest', 'email' => $email, 'party_size' => 1, 'source_attested' => true,
        ]);
        $invitation->forceFill(['status' => $status, 'delivery_status' => $delivery,
            'created_at' => now()->subDays($ageDays), 'expires_at' => now()->subDays($ageDays - 14)])->save();
        return $invitation;
    }

    private function schema(): void
    {
        Schema::create('events', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('organizer_id'); $table->boolean('status');
            $table->string('lifecycle_status'); $table->timestamp('cancelled_at')->nullable();
            $table->date('end_date')->nullable(); $table->timestamp('end_date_time')->nullable(); $table->timestamps();
        });
        Schema::create('event_contents', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('event_id'); $table->unsignedBigInteger('language_id');
            $table->string('title'); $table->string('slug'); $table->timestamps();
        });
        Schema::create('event_guest_imports', function (Blueprint $table): void {
            $table->id(); $table->string('public_id')->unique(); $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('organizer_id'); $table->string('status'); $table->string('disk'); $table->string('path');
            $table->unsignedInteger('total_rows')->default(0); $table->unsignedInteger('accepted_rows')->default(0);
            $table->unsignedInteger('rejected_rows')->default(0); $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('attestation_version'); $table->timestamp('attested_at'); $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable(); $table->timestamp('source_deleted_at')->nullable();
            $table->timestamp('expires_at')->nullable(); $table->text('last_error')->nullable(); $table->timestamps();
        });
        Schema::create('event_guest_import_rows', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('import_id'); $table->unsignedInteger('row_number');
            $table->char('email_fingerprint', 64); $table->string('status'); $table->unsignedBigInteger('invitation_id')->nullable();
            $table->string('error_code')->nullable(); $table->text('error_message')->nullable();
            $table->char('row_fingerprint', 64); $table->timestamps(); $table->unique(['import_id', 'row_number']);
        });
        Schema::create('event_guest_invitations', function (Blueprint $table): void {
            $table->id(); $table->string('public_id', 26)->unique(); $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('organizer_id'); $table->text('name'); $table->text('email'); $table->char('email_hash', 64);
            $table->unsignedSmallInteger('party_size')->default(1); $table->string('status'); $table->string('delivery_status');
            $table->char('token_hash', 64); $table->text('dispatch_token')->nullable();
            $table->unsignedSmallInteger('dispatch_attempts')->default(0); $table->timestamp('dispatch_last_attempt_at')->nullable();
            $table->text('dispatch_last_error')->nullable(); $table->string('source_attestation_version'); $table->timestamp('source_attested_at');
            $table->string('source_attested_ip_hash', 64)->nullable(); $table->unsignedBigInteger('source_import_id')->nullable();
            $table->unsignedInteger('source_import_row')->nullable(); $table->timestamp('invited_at')->nullable();
            $table->timestamp('expires_at'); $table->timestamp('responded_at')->nullable(); $table->timestamp('rsvp_consent_at')->nullable();
            $table->timestamp('marketing_consent_at')->nullable(); $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('checked_in_at')->nullable(); $table->unsignedBigInteger('checked_in_by_organizer_id')->nullable();
            $table->unsignedSmallInteger('delivery_attempts')->default(0); $table->timestamp('processing_at')->nullable();
            $table->timestamp('alerted_at')->nullable(); $table->text('last_error')->nullable();
            $table->timestamp('anonymized_at')->nullable(); $table->string('anonymization_reason')->nullable(); $table->timestamps();
            $table->unique(['event_id', 'email_hash']); $table->unique(['source_import_id', 'source_import_row']);
        });
        Schema::create('data_exports', function (Blueprint $table): void {
            $table->id(); $table->uuid('public_id')->unique(); $table->string('requested_by_type');
            $table->unsignedBigInteger('requested_by_id'); $table->unsignedBigInteger('organizer_id')->nullable();
            $table->string('export_type'); $table->string('format'); $table->string('status'); $table->longText('filters')->nullable();
            $table->string('disk'); $table->string('path')->nullable(); $table->string('filename');
            $table->unsignedBigInteger('row_count'); $table->integer('attempts')->default(0); $table->text('last_error')->nullable();
            $table->timestamp('started_at')->nullable(); $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable(); $table->timestamp('downloaded_at')->nullable(); $table->timestamps();
        });
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id(); $table->string('actor_type'); $table->unsignedBigInteger('actor_id')->nullable(); $table->string('action');
            $table->string('subject_type')->nullable(); $table->unsignedBigInteger('subject_id')->nullable(); $table->json('before')->nullable();
            $table->json('after')->nullable(); $table->json('metadata')->nullable(); $table->string('request_id')->nullable();
            $table->string('ip_address')->nullable(); $table->text('user_agent')->nullable(); $table->timestamp('created_at')->nullable();
        });
    }
}
