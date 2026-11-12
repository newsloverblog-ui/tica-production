<?php

namespace Tests\Unit;

use App\Http\Controllers\BackEnd\Organizer\EventRegistrationController;
use App\Models\EventRegistrationFile;
use App\Models\EventRegistrationSubmission;
use App\Models\Organizer;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class EventRegistrationFileAccessTest extends TestCase
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
        Storage::fake('local');
        $this->schema();
    }

    protected function tearDown(): void
    {
        Auth::guard('organizer')->logout();
        parent::tearDown();
    }

    public function test_private_registration_file_download_is_owner_scoped_audited_and_not_cacheable(): void
    {
        $owner = Organizer::create([
            'email' => 'owner@example.com', 'password' => bcrypt('secret'), 'status' => 1,
            'email_verified_at' => now(),
        ]);
        $other = Organizer::create([
            'email' => 'other@example.com', 'password' => bcrypt('secret'), 'status' => 1,
            'email_verified_at' => now(),
        ]);
        $owned = $this->file($owner->id, 'owned.pdf');
        $foreign = $this->file($other->id, 'foreign.pdf');
        Auth::guard('organizer')->login($owner);

        $response = app(EventRegistrationController::class)->downloadFile($owned);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertDatabaseHas('audit_logs', [
            'actor_type' => 'organizer', 'actor_id' => $owner->id,
            'action' => 'event.registration_file.downloaded', 'subject_id' => $owned->id,
        ]);

        try {
            app(EventRegistrationController::class)->downloadFile($foreign);
            $this->fail('An organizer must not download another organizer’s registration file.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    private function file(int $organizerId, string $name): EventRegistrationFile
    {
        $submission = EventRegistrationSubmission::create([
            'public_id' => (string) Str::ulid(), 'form_id' => 1, 'event_id' => 10,
            'organizer_id' => $organizerId, 'form_version' => 1, 'status' => 'submitted',
            'primary_name' => 'Private Person', 'primary_email' => 'private@example.com',
            'primary_email_hash' => hash('sha256', 'private@example.com'), 'attendee_count' => 1,
            'consent_version' => 'test', 'consented_at' => now(), 'submitted_at' => now(),
        ]);
        $path = 'registration-uploads/'.$submission->id.'/'.$name;
        Storage::disk('local')->put($path, '%PDF-private');

        return EventRegistrationFile::create([
            'public_id' => (string) Str::ulid(), 'answer_id' => $submission->id,
            'submission_id' => $submission->id, 'question_id' => 1, 'disk' => 'local', 'path' => $path,
            'original_name' => $name, 'mime_type' => 'application/pdf', 'size' => 12,
            'sha256' => hash('sha256', '%PDF-private'),
        ]);
    }

    private function schema(): void
    {
        Schema::create('organizers', function (Blueprint $table): void {
            $table->id(); $table->string('email')->unique(); $table->string('password');
            $table->boolean('status')->default(true); $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken(); $table->timestamps();
        });
        Schema::create('event_registration_submissions', function (Blueprint $table): void {
            $table->id(); $table->string('public_id')->unique(); $table->unsignedBigInteger('form_id');
            $table->unsignedBigInteger('event_id'); $table->unsignedBigInteger('organizer_id');
            $table->unsignedBigInteger('customer_id')->nullable(); $table->unsignedBigInteger('booking_id')->nullable();
            $table->unsignedInteger('form_version'); $table->string('status'); $table->text('primary_name');
            $table->text('primary_email'); $table->char('primary_email_hash', 64); $table->unsignedSmallInteger('attendee_count');
            $table->string('consent_version'); $table->timestamp('consented_at'); $table->char('source_ip_hash', 64)->nullable();
            $table->timestamp('submitted_at'); $table->unsignedBigInteger('reviewed_by_organizer_id')->nullable();
            $table->timestamp('reviewed_at')->nullable(); $table->text('review_note')->nullable(); $table->timestamps();
        });
        Schema::create('event_registration_files', function (Blueprint $table): void {
            $table->id(); $table->string('public_id')->unique(); $table->unsignedBigInteger('answer_id')->unique();
            $table->unsignedBigInteger('submission_id'); $table->unsignedBigInteger('attendee_id')->nullable();
            $table->unsignedBigInteger('question_id'); $table->string('disk'); $table->string('path')->unique();
            $table->text('original_name'); $table->string('mime_type'); $table->unsignedBigInteger('size');
            $table->char('sha256', 64); $table->timestamps();
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
