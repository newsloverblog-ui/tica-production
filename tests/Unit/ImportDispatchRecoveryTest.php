<?php

namespace Tests\Unit;

use App\Jobs\ProcessEventGuestImport;
use App\Jobs\ProcessEventRegistrationImport;
use App\Models\EventGuestImport;
use App\Models\EventRegistrationImport;
use App\Services\EventGuestImportService;
use App\Services\EventRegistrationImportService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class ImportDispatchRecoveryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        config()->set('app.key', 'base64:'.base64_encode(str_repeat('r', 32)));
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        $this->createImportTable('event_guest_imports', false);
        $this->createImportTable('event_registration_imports', true);
    }

    public function test_failed_handoffs_are_encrypted_and_recovered_without_duplicate_imports(): void
    {
        $guest = EventGuestImport::create($this->attributes('01RECOVERGUESTIMPORT000001'));
        $registration = EventRegistrationImport::create(array_merge(
            $this->attributes('01RECOVERREGIMPORT0000001'),
            ['form_id' => 45, 'original_filename' => 'registration-import.csv'],
        ));

        $guestFailure = new class extends EventGuestImportService {
            protected function pushImportJob(int $importId): void
            {
                throw new RuntimeException('guest queue unavailable');
            }
        };
        $registrationFailure = new class extends EventRegistrationImportService {
            protected function pushImportJob(int $importId): void
            {
                throw new RuntimeException('registration queue unavailable');
            }
        };

        $this->assertFalse($guestFailure->dispatchQueued($guest->id));
        $this->assertFalse($registrationFailure->dispatchQueued($registration->id));
        $this->assertSame(1, $guest->fresh()->dispatch_attempts);
        $this->assertSame(1, $registration->fresh()->dispatch_attempts);
        $this->assertStringContainsString('guest queue unavailable', $guest->fresh()->dispatch_last_error);
        $this->assertStringNotContainsString('guest queue unavailable', (string) DB::table('event_guest_imports')
            ->where('id', $guest->id)->value('dispatch_last_error'));

        Queue::fake();
        $this->artisan('imports:dispatch-pending')->assertSuccessful();

        Queue::assertPushed(ProcessEventGuestImport::class,
            fn (ProcessEventGuestImport $job): bool => $job->importId === $guest->id);
        Queue::assertPushed(ProcessEventRegistrationImport::class,
            fn (ProcessEventRegistrationImport $job): bool => $job->importId === $registration->id);
        $this->assertSame(2, $guest->fresh()->dispatch_attempts);
        $this->assertSame(2, $registration->fresh()->dispatch_attempts);
        $this->assertNull($guest->fresh()->dispatch_last_error);
        $this->assertNull($registration->fresh()->dispatch_last_error);
        $this->assertDatabaseCount('event_guest_imports', 1);
        $this->assertDatabaseCount('event_registration_imports', 1);
    }

    private function attributes(string $publicId): array
    {
        return [
            'public_id' => $publicId,
            'event_id' => 100,
            'organizer_id' => 10,
            'status' => 'queued',
            'disk' => 'local',
            'path' => 'private/import.csv',
            'attestation_version' => 'v1',
            'attested_at' => now(),
            'expires_at' => now()->addDay(),
        ];
    }

    private function createImportTable(string $name, bool $registration): void
    {
        Schema::create($name, function (Blueprint $table) use ($registration): void {
            $table->id();
            $table->string('public_id', 30)->unique();
            if ($registration) {
                $table->unsignedBigInteger('form_id');
            }
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('organizer_id');
            $table->string('status', 32)->default('queued');
            $table->string('disk', 64);
            $table->string('path', 500);
            if ($registration) {
                $table->string('original_filename');
            }
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('accepted_rows')->default(0);
            $table->unsignedInteger('rejected_rows')->default(0);
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedSmallInteger('dispatch_attempts')->default(0);
            $table->timestamp('dispatch_last_attempt_at')->nullable();
            $table->text('dispatch_last_error')->nullable();
            $table->timestamp('dispatch_alerted_at')->nullable();
            $table->string('attestation_version', 64);
            $table->timestamp('attested_at');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('source_deleted_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }
}
