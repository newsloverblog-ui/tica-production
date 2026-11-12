<?php

namespace Tests\Unit;

use App\Http\Middleware\AuditConfigurationChanges;
use App\Jobs\GenerateAuditLogExport;
use App\Services\AuditLogExportQueryService;
use App\Services\DataExportService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class AuditGovernanceTest extends TestCase
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
        config()->set('app.key', 'base64:' . base64_encode(str_repeat('a', 32)));
        config()->set('exports.disk', 'local');
        config()->set('exports.retention_days', 7);
        config()->set('exports.max_rows', 250000);
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id(); $table->string('actor_type'); $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('action'); $table->string('subject_type')->nullable(); $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('before')->nullable(); $table->json('after')->nullable(); $table->json('metadata')->nullable();
            $table->string('request_id')->nullable(); $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable(); $table->timestamp('created_at')->nullable();
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
    }

    public function test_successful_configuration_mutation_records_scope_without_values(): void
    {
        $request = Request::create('/admin/basic-settings/update-currency', 'POST', [
            'base_currency_text' => 'NGN',
            'paystack_secret_key' => 'must-never-enter-audit-history',
            '_token' => 'csrf-value',
        ]);
        $route = new Route(['POST'], '/admin/basic-settings/update-currency', fn () => null);
        $route->name('admin.basic_settings.update_currency');
        $route->bind($request);
        $request->setRouteResolver(fn () => $route);

        (new AuditConfigurationChanges())->handle($request, fn () => new Response('', 302));

        $row = DB::table('audit_logs')->sole();
        $metadata = json_decode((string) $row->metadata, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('configuration.changed', $row->action);
        $this->assertSame('admin.basic_settings.update_currency', $metadata['route']);
        $this->assertSame(['base_currency_text', 'paystack_secret_key'], $metadata['changed_fields']);
        $this->assertStringNotContainsString('must-never-enter-audit-history', (string) $row->metadata);
        $this->assertStringNotContainsString('csrf-value', (string) $row->metadata);
    }

    public function test_non_configuration_and_failed_requests_are_not_recorded(): void
    {
        foreach ([
            ['admin.event_management.store_event', 302],
            ['admin.basic_settings.update_currency', 422],
        ] as [$name, $status]) {
            $request = Request::create('/admin/action', 'POST', ['value' => 'changed']);
            $route = new Route(['POST'], '/admin/action', fn () => null);
            $route->name($name);
            $route->bind($request);
            $request->setRouteResolver(fn () => $route);
            (new AuditConfigurationChanges())->handle($request, fn () => new Response('', $status));
        }

        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_retention_is_dry_run_first_policy_gated_and_audited(): void
    {
        config()->set('audit.retention_days', 365);
        DB::table('audit_logs')->insert([
            ['actor_type' => 'system', 'action' => 'old.action', 'created_at' => now()->subDays(366)],
            ['actor_type' => 'system', 'action' => 'current.action', 'created_at' => now()->subDays(364)],
        ]);

        $this->artisan('audit:apply-retention --json')
            ->expectsOutputToContain('"mode":"dry-run"')
            ->assertSuccessful();
        $this->assertDatabaseCount('audit_logs', 2);

        config()->set('audit.retention_enabled', false);
        $this->artisan('audit:apply-retention --apply')->assertFailed();
        $this->assertDatabaseCount('audit_logs', 2);

        config()->set('audit.retention_enabled', true);
        $this->artisan('audit:apply-retention --apply --json')->assertSuccessful();
        $this->assertDatabaseMissing('audit_logs', ['action' => 'old.action']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'current.action']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'audit.retention_applied']);
    }

    public function test_audit_export_is_private_encrypted_snapshotted_and_formula_safe(): void
    {
        Storage::fake('local');
        Queue::fake();
        DB::table('audit_logs')->insert([
            'actor_type' => 'admin', 'actor_id' => 7, 'action' => '=unsafe-action',
            'metadata' => json_encode(['scope' => 'settings']), 'created_at' => now(),
        ]);

        $export = app(DataExportService::class)->queueAuditLogs(7, [
            'from_date' => now()->format('Y-m-d'),
            'to_date' => now()->format('Y-m-d'),
        ], 'csv');
        $this->assertSame('audit_logs', $export->export_type);
        $this->assertSame(7, $export->requested_by_id);
        $this->assertNull($export->organizer_id);
        $this->assertSame(1, $export->filters['max_audit_id']);
        $raw = (string) DB::table('data_exports')->where('id', $export->id)->value('filters');
        $this->assertStringNotContainsString(now()->format('Y-m-d'), $raw);
        Queue::assertPushed(GenerateAuditLogExport::class, fn ($job) => $job->exportId === $export->id);

        DB::table('audit_logs')->insert([
            'actor_type' => 'admin', 'actor_id' => 7, 'action' => 'late.action', 'created_at' => now(),
        ]);
        $job = new GenerateAuditLogExport($export->id);
        $job->handle(app(AuditLogExportQueryService::class));
        $ready = $export->fresh();
        $csv = Storage::disk('local')->get($ready->path);

        $this->assertSame('ready', $ready->status);
        $this->assertStringContainsString("'=unsafe-action", $csv);
        $this->assertStringNotContainsString('late.action', $csv);
        $job->handle(app(AuditLogExportQueryService::class));
        $this->assertSame(1, $ready->fresh()->attempts);
    }
}
