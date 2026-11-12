<?php

namespace Tests\Unit;

use App\Http\Controllers\BackEnd\DataExportController as AdminDataExportController;
use App\Http\Controllers\BackEnd\Organizer\DataExportController as OrganizerDataExportController;
use App\Jobs\GenerateWithdrawalExport;
use App\Models\Admin;
use App\Models\DataExport;
use App\Models\Organizer;
use App\Services\DataExportService;
use App\Services\WithdrawalExportQueryService;
use DomainException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WithdrawalDataExportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        config()->set('app.key', 'base64:'.base64_encode(str_repeat('w', 32)));
        config()->set('exports.disk', 'local');
        config()->set('exports.retention_days', 7);
        config()->set('exports.max_rows', 250000);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        $this->createSchema();
        DB::table('basic_settings')->insert(['id' => 1, 'base_currency_text' => 'NGN']);
        DB::table('withdraw_payment_methods')->insert(['id' => 1, 'name' => 'Bank transfer']);
        $this->insertOrganizer(10, 'alpha');
        $this->insertOrganizer(20, 'beta');
    }

    public function test_organizer_export_is_private_scoped_encrypted_and_queued(): void
    {
        Queue::fake();
        $this->insertWithdrawal(1, 10, '=ALPHA-WITHDRAWAL', 0, '2026-11-02 10:00:00');
        $this->insertWithdrawal(2, 20, 'BETA-WITHDRAWAL', 0, '2026-11-02 11:00:00');

        $export = app(DataExportService::class)->queueWithdrawals('organizer', 10, 10, [
            'withdrawal_reference' => '=ALPHA', 'status' => 0,
            'from_date' => '2026-11-01', 'to_date' => '2026-11-30',
        ], 'csv');

        $this->assertSame('withdrawals', $export->export_type);
        $this->assertSame(10, $export->organizer_id);
        $this->assertSame(1, $export->row_count);
        $this->assertSame(1, $export->filters['max_withdrawal_id']);
        $this->assertStringNotContainsString('ALPHA', (string) DB::table('data_exports')->value('filters'));
        Queue::assertPushed(GenerateWithdrawalExport::class, fn ($job) => $job->exportId === $export->id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'data_export.queued', 'subject_id' => $export->id]);
        $this->assertSame(2, app(WithdrawalExportQueryService::class)->query(['status' => ''])->count());
    }

    public function test_generator_is_snapshotted_formula_safe_masked_analysis_ready_and_idempotent(): void
    {
        Queue::fake();
        Storage::fake('local');
        DB::table('organizer_payout_accounts')->insert([
            'id' => 1, 'public_id' => 'acc-public-1', 'organizer_id' => 10,
            'currency' => 'NGN', 'bank_name' => '=Unsafe Bank',
            'account_number' => 'SECRET-1234567890', 'account_last4' => '7890',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->insertWithdrawal(1, 10, '=WITHDRAW-ONE', 1, '2026-11-02 10:00:00', 1, '+PAYOUT-ONE');

        $export = app(DataExportService::class)->queueWithdrawals('admin', 7, null, [], 'csv');
        $this->insertWithdrawal(2, 10, 'LATE-WITHDRAWAL', 0, '2026-11-03 10:00:00');

        $job = new GenerateWithdrawalExport($export->id);
        $job->handle(app(WithdrawalExportQueryService::class));
        $ready = $export->fresh();
        $csv = Storage::disk('local')->get($ready->path);

        $this->assertSame('ready', $ready->status);
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('"withdrawal_id","withdrawal_reference","organizer_id"', $csv);
        $this->assertStringContainsString("'=WITHDRAW-ONE", $csv);
        $this->assertStringContainsString("'+PAYOUT-ONE", $csv);
        $this->assertStringContainsString("'=Unsafe Bank", $csv);
        $this->assertStringContainsString('******7890', $csv);
        $this->assertStringContainsString('2026-11-02T10:00:00', $csv);
        $this->assertStringNotContainsString('LATE-WITHDRAWAL', $csv);
        $this->assertStringNotContainsString('SECRET-1234567890', $csv);
        $this->assertStringNotContainsString('private-bank-routing', $csv);
        $this->assertStringNotContainsString('decision-only-secret', $csv);

        $job->handle(app(WithdrawalExportQueryService::class));
        $this->assertSame(1, $ready->fresh()->attempts);
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'data_export.ready')->count());
    }

    public function test_invalid_cross_organizer_or_empty_export_fails_without_history_or_job(): void
    {
        Queue::fake();
        foreach ([
            ['admin', 7, null, [], 'pdf'],
            ['organizer', 10, 20, [], 'csv'],
            ['organizer', 10, 10, [], 'xlsx'],
        ] as [$actorType, $actorId, $organizerId, $filters, $format]) {
            try {
                app(DataExportService::class)->queueWithdrawals($actorType, $actorId, $organizerId, $filters, $format);
                $this->fail('The invalid, cross-organizer or empty export should have failed.');
            } catch (DomainException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertDatabaseCount('data_exports', 0);
        Queue::assertNothingPushed();
    }

    public function test_routes_require_safe_methods_permissions_signatures_and_downloads_are_actor_owned(): void
    {
        $adminStore = Route::getRoutes()->getByName('admin.withdraw_exports.store');
        $adminDownload = Route::getRoutes()->getByName('admin.withdraw_exports.download');
        $organizerStore = Route::getRoutes()->getByName('organizer.withdraw_exports.store');
        $organizerDownload = Route::getRoutes()->getByName('organizer.withdraw_exports.download');
        $this->assertSame(['POST'], $adminStore->methods());
        $this->assertSame(['POST'], $organizerStore->methods());
        $this->assertContains('permission:Withdraw Method', $adminStore->gatherMiddleware());
        $this->assertContains('permission:Transaction', $adminStore->gatherMiddleware());
        $this->assertContains('signed', $adminDownload->gatherMiddleware());
        $this->assertContains('signed', $organizerDownload->gatherMiddleware());

        Storage::fake('local');
        DB::table('admins')->insert(['id' => 7, 'username' => 'finance', 'status' => 1]);
        DB::table('admins')->insert(['id' => 8, 'username' => 'other', 'status' => 1]);
        $adminExport = $this->readyExport('admin', 7, null, 'exports/withdrawals/admin.csv');
        Storage::disk('local')->put($adminExport->path, 'private-admin');

        Auth::guard('admin')->setUser(Admin::findOrFail(8));
        try {
            (new AdminDataExportController())->downloadWithdrawal(Request::create('/'), $adminExport);
            $this->fail('Another administrator must not download the withdrawal export.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        Auth::guard('admin')->setUser(Admin::findOrFail(7));
        $adminResponse = (new AdminDataExportController())->downloadWithdrawal(Request::create('/'), $adminExport);
        $this->assertStringContainsString('no-store', (string) $adminResponse->headers->get('Cache-Control'));
        $this->assertNotNull($adminExport->fresh()->downloaded_at);
        try {
            (new AdminDataExportController())->download(Request::create('/'), $adminExport);
            $this->fail('The booking-export route must not serve a withdrawal export.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(404, $exception->getStatusCode());
        }

        Auth::guard('admin')->forgetUser();
        $organizerExport = $this->readyExport('organizer', 10, 10, 'exports/withdrawals/organizer.csv');
        Storage::disk('local')->put($organizerExport->path, 'private-organizer');
        Auth::guard('organizer')->setUser(Organizer::findOrFail(20));
        try {
            (new OrganizerDataExportController())->downloadWithdrawal(Request::create('/'), $organizerExport);
            $this->fail('Another organizer must not download the withdrawal export.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        Auth::guard('organizer')->setUser(Organizer::findOrFail(10));
        $organizerResponse = (new OrganizerDataExportController())->downloadWithdrawal(Request::create('/'), $organizerExport);
        $this->assertStringContainsString('no-store', (string) $organizerResponse->headers->get('Cache-Control'));
        $this->assertNotNull($organizerExport->fresh()->downloaded_at);
        try {
            (new OrganizerDataExportController())->download(Request::create('/'), $organizerExport);
            $this->fail('The general organizer-export route must not serve a withdrawal export.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(404, $exception->getStatusCode());
        }
    }

    private function readyExport(string $actorType, int $actorId, ?int $organizerId, string $path): DataExport
    {
        return DataExport::create([
            'requested_by_type' => $actorType, 'requested_by_id' => $actorId,
            'organizer_id' => $organizerId, 'export_type' => 'withdrawals',
            'format' => 'csv', 'status' => 'ready', 'filters' => [],
            'disk' => 'local', 'path' => $path, 'filename' => basename($path),
            'row_count' => 1, 'expires_at' => now()->addHour(),
        ]);
    }

    private function insertOrganizer(int $id, string $username): void
    {
        DB::table('organizers')->insert([
            'id' => $id, 'username' => $username, 'email' => $username.'@example.test',
            'password' => 'test', 'status' => 1, 'amount' => 100000,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function insertWithdrawal(
        int $id,
        int $organizerId,
        string $reference,
        int $status,
        string $createdAt,
        ?int $payoutAccountId = null,
        ?string $payoutReference = null
    ): void {
        DB::table('withdraws')->insert([
            'id' => $id, 'withdraw_id' => $reference, 'organizer_id' => $organizerId,
            'method_id' => 1, 'payout_account_id' => $payoutAccountId,
            'amount' => 10000, 'total_charge' => 250, 'payable_amount' => 9750,
            'status' => $status, 'feilds' => '{"routing":"private-bank-routing"}',
            'additional_reference' => 'private-bank-routing', 'payout_reference' => $payoutReference,
            'decision_reason' => 'decision-only-secret',
            'reviewed_at' => $status === 1 ? $createdAt : null,
            'paid_at' => $status === 1 ? $createdAt : null,
            'created_at' => $createdAt, 'updated_at' => $createdAt,
        ]);
    }

    private function createSchema(): void
    {
        Schema::create('admins', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('role_id')->nullable(); $table->string('username');
            $table->string('status')->default('1'); $table->timestamps();
        });
        Schema::create('organizers', function (Blueprint $table): void {
            $table->id(); $table->string('username'); $table->string('email'); $table->string('password');
            $table->string('status')->default('1'); $table->decimal('amount', 14, 2)->default(0); $table->timestamps();
        });
        Schema::create('withdraw_payment_methods', function (Blueprint $table): void {
            $table->id(); $table->string('name'); $table->timestamps();
        });
        Schema::create('organizer_payout_accounts', function (Blueprint $table): void {
            $table->id(); $table->string('public_id'); $table->unsignedBigInteger('organizer_id');
            $table->string('currency')->nullable(); $table->string('bank_name')->nullable();
            $table->text('account_number')->nullable(); $table->string('account_last4', 4)->nullable();
            $table->timestamps();
        });
        Schema::create('withdraws', function (Blueprint $table): void {
            $table->id(); $table->string('withdraw_id'); $table->unsignedBigInteger('organizer_id');
            $table->unsignedBigInteger('method_id')->nullable(); $table->unsignedBigInteger('payout_account_id')->nullable();
            $table->decimal('amount', 14, 2); $table->decimal('total_charge', 14, 2); $table->decimal('payable_amount', 14, 2);
            $table->unsignedTinyInteger('status')->default(0); $table->text('feilds')->nullable();
            $table->text('additional_reference')->nullable(); $table->string('payout_reference')->nullable();
            $table->text('decision_reason')->nullable(); $table->unsignedBigInteger('reviewed_by_admin_id')->nullable();
            $table->timestamp('reviewed_at')->nullable(); $table->timestamp('paid_at')->nullable(); $table->timestamps();
        });
        Schema::create('basic_settings', function (Blueprint $table): void {
            $table->id(); $table->string('base_currency_text')->nullable(); $table->timestamp('updated_at')->nullable();
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
