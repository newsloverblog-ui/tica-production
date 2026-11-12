<?php

namespace Tests\Unit;

use App\Models\Organizer;
use App\Models\OrganizerKycSubmission;
use App\Services\OrganizerKycService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class OrganizerKycServiceTest extends TestCase
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
        $this->createSchema();
    }

    public function test_submission_is_private_hashed_audited_and_cannot_be_replaced_while_pending(): void
    {
        $organizer = $this->organizer();
        $service = new OrganizerKycService();

        $submission = $service->submit($organizer->id, $this->individualData(), [
            'identity' => UploadedFile::fake()->createWithContent('passport.pdf', "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\n%%EOF\n"),
        ]);

        $this->assertSame('pending', $submission->status);
        $this->assertSame('pending', $organizer->fresh()->kyc_status);
        $this->assertCount(1, $submission->documents);
        $document = $submission->documents->first();
        Storage::disk('local')->assertExists($document->path);
        $this->assertSame(64, strlen($document->sha256));
        $this->assertStringStartsWith('kyc/' . $organizer->id . '/', $document->path);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'organizer.kyc_submitted',
            'subject_id' => $submission->id,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('cannot be replaced');
        $service->submit($organizer->id, $this->individualData(), [
            'identity' => UploadedFile::fake()->create('replacement.pdf', 100, 'application/pdf'),
        ]);
    }

    public function test_verification_updates_the_organizer_and_is_idempotently_guarded(): void
    {
        $organizer = $this->organizer();
        $service = new OrganizerKycService();
        $submission = $service->submit($organizer->id, $this->individualData(), [
            'identity' => UploadedFile::fake()->image('identity.jpg'),
        ]);

        $reviewed = $service->review($submission->id, 'verified', null, 44);

        $organizer->refresh();
        $this->assertSame('verified', $reviewed->status);
        $this->assertSame(44, $reviewed->reviewed_by_admin_id);
        $this->assertNotNull($reviewed->reviewed_at);
        $this->assertSame('verified', $organizer->kyc_status);
        $this->assertSame('Ada Lovelace', $organizer->verified_legal_name);
        $this->assertNotNull($organizer->kyc_verified_at);
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'organizer.kyc_reviewed')->count());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Only pending');
        $service->review($submission->id, 'verified', null, 44);
    }

    public function test_rejection_requires_a_reason_and_allows_a_new_submission(): void
    {
        $organizer = $this->organizer();
        $service = new OrganizerKycService();
        $submission = $service->submit($organizer->id, $this->individualData(), [
            'identity' => UploadedFile::fake()->image('identity.png'),
        ]);

        try {
            $service->review($submission->id, 'rejected', '', 9);
            $this->fail('A rejection without a reason should fail.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('reason is required', $exception->getMessage());
        }

        $service->review($submission->id, 'rejected', 'The document image is unreadable.', 9);
        $this->assertSame('rejected', $organizer->fresh()->kyc_status);

        $replacement = $service->submit($organizer->id, $this->individualData(), [
            'identity' => UploadedFile::fake()->image('clear-identity.png'),
        ]);

        $this->assertSame('pending', $replacement->status);
        $this->assertSame('pending', $organizer->fresh()->kyc_status);
        $this->assertSame(2, OrganizerKycSubmission::where('organizer_id', $organizer->id)->count());
    }

    private function organizer(): Organizer
    {
        return Organizer::create([
            'email' => 'ada@example.com',
            'username' => 'ada',
            'password' => 'not-used-in-this-test',
            'status' => 1,
            'amount' => 0,
            'kyc_status' => 'unverified',
        ]);
    }

    private function individualData(): array
    {
        return [
            'entity_type' => 'individual',
            'legal_name' => 'Ada Lovelace',
            'business_name' => null,
            'registration_number' => null,
            'id_type' => 'passport',
        ];
    }

    private function createSchema(): void
    {
        Schema::create('organizers', function (Blueprint $table) {
            $table->id();
            $table->string('email')->nullable();
            $table->string('username')->nullable();
            $table->string('password')->nullable();
            $table->integer('status')->default(1);
            $table->decimal('amount', 18, 2)->default(0);
            $table->string('kyc_status', 24)->default('unverified');
            $table->string('verified_legal_name')->nullable();
            $table->timestamp('kyc_verified_at')->nullable();
            $table->timestamps();
        });
        Schema::create('organizer_kyc_submissions', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->unsignedBigInteger('organizer_id');
            $table->string('entity_type', 24);
            $table->string('legal_name');
            $table->string('business_name')->nullable();
            $table->string('registration_number')->nullable();
            $table->string('id_type', 40);
            $table->string('status', 24)->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->unsignedBigInteger('reviewed_by_admin_id')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('organizer_kyc_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('submission_id');
            $table->string('document_type', 40);
            $table->string('disk', 40);
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size');
            $table->char('sha256', 64);
            $table->timestamps();
        });
        Schema::create('organizer_payout_accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organizer_id');
            $table->string('status', 32);
            $table->boolean('is_default')->default(false);
            $table->string('resolved_account_name')->nullable();
            $table->string('name_match_status', 32)->nullable();
            $table->string('bank_name')->nullable();
            $table->string('account_last4', 4)->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('hold_until')->nullable();
            $table->timestamps();
        });
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('actor_type');
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('action');
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->json('metadata')->nullable();
            $table->string('request_id')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }
}
