<?php

namespace Tests\Unit;

use App\Exports\AdminDirectoryQueryExport;
use App\Http\Controllers\BackEnd\Organizer\OrganizerManagementController;
use App\Jobs\GenerateAdminDirectoryExport;
use App\Models\ApplicationMailDelivery;
use App\Models\Organizer;
use App\Services\ApplicationMailService;
use App\Services\DataExportService;
use DomainException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class AdminDirectoryOperationsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        config()->set('app.key', 'base64:'.base64_encode(str_repeat('d', 32)));
        config()->set('exports.disk', 'local');
        config()->set('exports.retention_days', 7);
        config()->set('exports.max_rows', 250000);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        $this->createSchema();
    }

    public function test_admin_directory_export_is_private_filtered_snapshotted_and_queued(): void
    {
        Queue::fake();
        $this->insertOrganizer(1, 'active', 'active@example.test', 1, true);
        $this->insertOrganizer(2, 'inactive', 'inactive@example.test', 0, false);

        $export = app(DataExportService::class)->queueAdminDirectory(7, 'organizers', [
            'search' => 'active', 'status' => '1', 'verified' => '1',
        ], 'csv');

        $this->assertSame('admin', $export->requested_by_type);
        $this->assertSame(7, $export->requested_by_id);
        $this->assertSame('organizers', $export->export_type);
        $this->assertSame(1, $export->row_count);
        $this->assertSame(2, $export->filters['max_id']);
        $this->assertArrayHasKey('snapshot_at', $export->filters);
        $this->assertStringNotContainsString('active', (string) DB::table('data_exports')->where('id', $export->id)->value('filters'));
        Queue::assertPushed(GenerateAdminDirectoryExport::class, fn ($job) => $job->exportId === $export->id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'data_export.queued', 'subject_id' => $export->id]);
    }

    public function test_export_cells_are_formula_safe_and_invalid_requests_fail_closed(): void
    {
        $organizer = new Organizer([
            'username' => '=HYPERLINK("https://bad.test")',
            'email' => '+formula@example.test',
            'phone' => "@phone\nline",
            'status' => 1,
            'kyc_status' => 'verified',
        ]);
        $organizer->id = 5;
        $organizer->email_verified_at = now();
        $organizer->created_at = now();
        $row = (new AdminDirectoryQueryExport(Organizer::query(), 'organizers'))->map($organizer);

        $this->assertSame("'=HYPERLINK(\"https://bad.test\")", $row[1]);
        $this->assertSame("'+formula@example.test", $row[2]);
        $this->assertSame("'@phone line", $row[3]);

        foreach ([[0, 'organizers', [], 'csv'], [7, 'unknown', [], 'csv'], [7, 'customers', [], 'pdf']] as $args) {
            try {
                app(DataExportService::class)->queueAdminDirectory(...$args);
                $this->fail('Invalid directory export request should be rejected.');
            } catch (DomainException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_routes_enforce_post_signed_downloads_and_bounded_impersonation(): void
    {
        $this->assertSame(['POST'], Route::getRoutes()->getByName('admin.organizer_management.export')->methods());
        $this->assertSame(['POST'], Route::getRoutes()->getByName('admin.customer_management.export')->methods());
        $this->assertContains('signed', Route::getRoutes()->getByName('admin.directory_exports.download')->gatherMiddleware());
        $this->assertSame(['POST'], Route::getRoutes()->getByName('admin.organizer_management.organizer.secret_login')->methods());
        $this->assertContains('throttle:10,1', Route::getRoutes()->getByName('admin.organizer_management.organizer.secret_login')->gatherMiddleware());
        $this->assertSame(['POST'], Route::getRoutes()->getByName('admin.organizer_management.organizer.message')->methods());
        $this->assertContains('throttle:10,1', Route::getRoutes()->getByName('admin.organizer_management.organizer.message')->gatherMiddleware());
        $this->assertSame(['POST'], Route::getRoutes()->getByName('organizer.impersonation.return')->methods());
        $this->assertContains('auth:organizer', Route::getRoutes()->getByName('organizer.impersonation.return')->gatherMiddleware());

        $controller = file_get_contents(app_path('Http/Controllers/BackEnd/Organizer/AdminImpersonationController.php'));
        $shell = file_get_contents(resource_path('views/components/shell/organizer.blade.php'));
        $this->assertStringContainsString("get('impersonator_admin_id')", $controller);
        $this->assertStringContainsString("Auth::guard('organizer')->logout()", $controller);
        $this->assertStringContainsString("->session()->regenerate()", $controller);
        $this->assertStringContainsString("route('organizer.impersonation.return')", $shell);
        $this->assertStringContainsString("{{ __('Return to admin') }}", $shell);
    }

    public function test_admin_message_to_organizer_is_queued_escaped_and_audited(): void
    {
        $this->insertOrganizer(8, 'tica-events', 'organizer@example.test', 1, true);
        $captured = [];
        $delivery = new ApplicationMailDelivery();
        $delivery->setAttribute('id', 44);
        $mail = Mockery::mock(ApplicationMailService::class);
        $mail->shouldReceive('queueHtml')->once()->withArgs(function (
            string $kind,
            string $recipient,
            string $subject,
            string $body,
            array $attachments,
            ?string $replyTo,
            Organizer $source
        ) use (&$captured): bool {
            $captured = compact('kind', 'recipient', 'subject', 'body', 'attachments', 'replyTo', 'source');
            return true;
        })->andReturn($delivery);

        $request = Request::create('/admin/organizer-management/organizer/8/message', 'POST', [
            'subject' => "Schedule update\r\nBcc: ignored@example.test",
            'message' => "Doors open at 6 PM.\n<script>alert('x')</script>",
        ]);
        app(OrganizerManagementController::class)->message($request, 8, $mail);

        $this->assertSame('admin_organizer_message', $captured['kind']);
        $this->assertSame('organizer@example.test', $captured['recipient']);
        $this->assertSame('Schedule update Bcc: ignored@example.test', $captured['subject']);
        $this->assertStringContainsString('Doors open at 6 PM.', $captured['body']);
        $this->assertStringContainsString('&lt;script&gt;', $captured['body']);
        $this->assertStringNotContainsString('<script>', $captured['body']);
        $this->assertSame(8, $captured['source']->id);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'organizer.message_queued',
            'subject_id' => 8,
        ]);
    }

    private function insertOrganizer(int $id, string $username, string $email, int $status, bool $verified): void
    {
        DB::table('organizers')->insert([
            'id' => $id, 'username' => $username, 'email' => $email, 'password' => 'hash',
            'phone' => null, 'status' => $status, 'email_verified_at' => $verified ? now() : null,
            'kyc_status' => 'unverified', 'amount' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function createSchema(): void
    {
        Schema::create('organizers', function (Blueprint $table): void {
            $table->id();
            $table->string('username');
            $table->string('email');
            $table->string('password');
            $table->string('phone')->nullable();
            $table->integer('status')->default(1);
            $table->timestamp('email_verified_at')->nullable();
            $table->string('kyc_status')->nullable();
            $table->decimal('amount', 18, 2)->default(0);
            $table->timestamps();
        });
        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->string('fname')->nullable();
            $table->string('lname')->nullable();
            $table->string('username');
            $table->string('email');
            $table->string('password')->nullable();
            $table->string('phone')->nullable();
            $table->integer('status')->default(1);
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamps();
        });
        Schema::create('data_exports', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('requested_by_type', 20);
            $table->unsignedBigInteger('requested_by_id');
            $table->unsignedBigInteger('organizer_id')->nullable();
            $table->unsignedBigInteger('schedule_id')->nullable();
            $table->string('export_type', 40);
            $table->string('format', 8);
            $table->string('status', 20);
            $table->longText('filters')->nullable();
            $table->string('disk', 40);
            $table->string('path', 500)->nullable();
            $table->string('filename', 191);
            $table->unsignedBigInteger('row_count')->default(0);
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('downloaded_at')->nullable();
            $table->timestamps();
        });
        Schema::create('audit_logs', function (Blueprint $table): void {
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
