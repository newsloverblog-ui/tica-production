<?php

namespace Tests\Unit;

use App\Jobs\SendApplicationMailDelivery;
use App\Models\CustomerPrivacyRequest;
use App\Services\CustomerPrivacyRequestService;
use App\Services\ReleaseReadinessService;
use DomainException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CustomerPrivacyRequestTest extends TestCase
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
        config()->set('privacy.policy_version', 'tica-privacy-2026-11');
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        $this->createSchema();
        $this->customer(1, 'owner@example.test');
        $this->customer(2, 'other@example.test');
    }

    public function test_deletion_request_is_encrypted_audited_notified_and_idempotent(): void
    {
        Queue::fake();
        $service = app(CustomerPrivacyRequestService::class);

        $request = $service->requestDeletion(1, 'I no longer use this account.');
        $same = $service->requestDeletion(1, 'A repeated browser submission.');

        $this->assertSame($request->id, $same->id);
        $this->assertSame('pending', $request->status);
        $this->assertSame('tica-privacy-2026-11', $request->policy_version);
        $raw = DB::table('customer_privacy_requests')->where('id', $request->id)->first();
        $this->assertStringNotContainsString('owner@example.test', (string) $raw->customer_email);
        $this->assertStringNotContainsString('no longer use', (string) $raw->reason);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'customer_privacy_request.submitted', 'subject_id' => $request->id,
        ]);
        $this->assertDatabaseCount('application_mail_deliveries', 1);
        Queue::assertPushed(SendApplicationMailDelivery::class, 1);
    }

    public function test_customer_can_cancel_only_own_pending_request(): void
    {
        Queue::fake();
        $service = app(CustomerPrivacyRequestService::class);
        $request = $service->requestDeletion(1, null);

        try {
            $service->cancel($request->id, 2);
            $this->fail('Cross-customer cancellation must fail.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('cannot change', $exception->getMessage());
        }

        $cancelled = $service->cancel($request->id, 1);
        $this->assertSame('cancelled', $cancelled->status);
        $this->assertNotNull($cancelled->completed_at);
        $this->expectException(DomainException::class);
        $service->cancel($request->id, 1);
    }

    public function test_fulfilment_requires_review_and_evidence_then_archives_account(): void
    {
        Queue::fake();
        $service = app(CustomerPrivacyRequestService::class);
        $request = $service->requestDeletion(1, 'Please close my account after review.');

        try {
            $service->review($request->id, 9, 'fulfilled', str_repeat('x', 30), 'CASE-1');
            $this->fail('Pending requests must first move under review.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('cannot move', $exception->getMessage());
        }

        $reviewing = $service->review(
            $request->id, 9, 'under_review',
            'Identity and retention scope are being verified.', null
        );
        $this->assertSame('under_review', $reviewing->status);

        try {
            $service->review($request->id, 9, 'fulfilled',
                'Approved work is complete with retained finance exceptions.', null);
            $this->fail('Fulfilment without evidence must fail.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('evidence', strtolower($exception->getMessage()));
        }

        $fulfilled = $service->review(
            $request->id, 9, 'fulfilled',
            'Approved profile deletion work is complete; finance records remain under policy.',
            'privacy-case/CASE-1'
        );
        $this->assertSame('fulfilled', $fulfilled->status);
        $this->assertSame(0, (int) DB::table('customers')->where('id', 1)->value('status'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'customer.archived', 'subject_id' => 1]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'customer_privacy_request.reviewed', 'subject_id' => $request->id,
        ]);
        $this->expectException(DomainException::class);
        $service->review($request->id, 9, 'rejected', str_repeat('x', 30), null);
    }

    public function test_routes_and_release_schema_enforce_the_privacy_workflow_boundary(): void
    {
        $routes = app('router')->getRoutes();
        foreach (['customer.privacy-request.store', 'customer.privacy-request.cancel'] as $name) {
            $route = $routes->getByName($name);
            $this->assertNotNull($route);
            $this->assertContains('auth:customer', $route->gatherMiddleware());
            $this->assertContains('Deactive:customer', $route->gatherMiddleware());
            $this->assertContains('EmailStatus:customer', $route->gatherMiddleware());
        }
        $admin = $routes->getByName('admin.customer_privacy_requests.update');
        $this->assertNotNull($admin);
        $this->assertContains('auth:admin', $admin->gatherMiddleware());
        $this->assertContains('permission:Customer Management', $admin->gatherMiddleware());

        $required = app(ReleaseReadinessService::class)->requiredTransactionalColumns();
        $this->assertArrayHasKey('customer_privacy_requests', $required);
        $this->assertContains('evidence_reference', $required['customer_privacy_requests']);
    }

    private function customer(int $id, string $email): void
    {
        DB::table('customers')->insert([
            'id' => $id, 'fname' => 'Test', 'lname' => 'Customer', 'email' => $email,
            'password' => password_hash('Password1', PASSWORD_BCRYPT), 'status' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function createSchema(): void
    {
        Schema::create('customers', function (Blueprint $table): void {
            $table->id(); $table->string('fname')->nullable(); $table->string('lname')->nullable();
            $table->string('email'); $table->string('password'); $table->boolean('status')->default(true); $table->timestamps();
        });
        Schema::create('customer_privacy_requests', function (Blueprint $table): void {
            $table->id(); $table->uuid('public_id')->unique(); $table->unsignedBigInteger('customer_id');
            $table->string('request_type'); $table->string('status'); $table->text('customer_email');
            $table->text('customer_name')->nullable(); $table->text('reason')->nullable();
            $table->string('policy_version'); $table->unsignedBigInteger('reviewed_by_admin_id')->nullable();
            $table->text('resolution')->nullable(); $table->string('evidence_reference')->nullable();
            $table->timestamp('submitted_at'); $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('completed_at')->nullable(); $table->timestamps();
        });
        Schema::create('application_mail_deliveries', function (Blueprint $table): void {
            $table->id(); $table->uuid('public_id')->unique(); $table->string('kind');
            $table->string('source_type')->nullable(); $table->unsignedBigInteger('source_id')->nullable();
            $table->string('created_by_type'); $table->unsignedBigInteger('created_by_id')->nullable();
            $table->string('recipient_hash'); $table->text('recipient_email'); $table->text('subject');
            $table->text('body'); $table->text('attachments')->nullable(); $table->text('reply_to')->nullable();
            $table->string('status'); $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedSmallInteger('dispatch_attempts')->default(0);
            $table->timestamp('dispatch_last_attempt_at')->nullable(); $table->text('dispatch_last_error')->nullable();
            $table->text('last_error')->nullable(); $table->timestamp('processing_at')->nullable();
            $table->timestamp('sent_at')->nullable(); $table->timestamp('failed_at')->nullable();
            $table->timestamp('alerted_at')->nullable(); $table->timestamps();
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
