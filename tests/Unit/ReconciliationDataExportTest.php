<?php

namespace Tests\Unit;

use App\Http\Controllers\BackEnd\ReconciliationExportController;
use App\Jobs\GenerateReconciliationExport;
use App\Models\Admin;
use App\Models\DataExport;
use App\Services\DataExportService;
use App\Services\ReconciliationExportQueryService;
use DomainException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReconciliationDataExportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', ['driver'=>'sqlite','database'=>':memory:','prefix'=>'','foreign_key_constraints'=>true]);
        config()->set('app.key', 'base64:'.base64_encode(str_repeat('r', 32)));
        config()->set('exports.disk', 'local');
        config()->set('exports.retention_days', 7);
        config()->set('exports.max_rows', 250000);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        $this->createSchema();
    }

    public function test_run_export_is_filtered_encrypted_snapshotted_and_queued(): void
    {
        Queue::fake();
        $this->insertRun(1, 'paystack', 'mismatched', '2026-11-03');
        $this->insertRun(2, 'flutterwave', 'matched', '2026-11-03');

        $export = app(DataExportService::class)->queueReconciliation(7, 'payment_reconciliation_runs', [
            'gateway'=>'paystack', 'status'=>'mismatched', 'date'=>'2026-11-03',
        ], 'csv');

        $this->assertSame('payment_reconciliation_runs', $export->export_type);
        $this->assertSame(1, $export->row_count);
        $this->assertSame(2, $export->filters['max_run_id']);
        $this->assertStringNotContainsString('paystack', (string) DB::table('data_exports')->value('filters'));
        Queue::assertPushed(GenerateReconciliationExport::class, fn ($job) => $job->exportId === $export->id);
        $this->assertDatabaseHas('audit_logs', ['action'=>'data_export.queued','subject_id'=>$export->id]);
    }

    public function test_generators_are_formula_safe_snapshot_bound_private_and_idempotent(): void
    {
        Queue::fake();
        Storage::fake('local');
        $this->insertRun(1, '=paystack', 'mismatched', '2026-11-03');
        $runExport = app(DataExportService::class)->queueReconciliation(7, 'payment_reconciliation_runs', [], 'csv');
        $this->insertRun(2, 'flutterwave', 'matched', '2026-11-04');

        $job = new GenerateReconciliationExport($runExport->id);
        $job->handle(app(ReconciliationExportQueryService::class));
        $runCsv = Storage::disk('local')->get($runExport->fresh()->path);
        $this->assertStringStartsWith("\xEF\xBB\xBF", $runCsv);
        $this->assertStringContainsString("'=paystack", $runCsv);
        $this->assertStringNotContainsString('flutterwave', $runCsv);
        $job->handle(app(ReconciliationExportQueryService::class));
        $this->assertSame(1, $runExport->fresh()->attempts);

        $this->insertSettlement(1, '=paystack', '+SETTLEMENT', 'mismatched', '2026-11-03 09:00:00');
        $settlementExport = app(DataExportService::class)->queueReconciliation(7, 'gateway_settlements', [], 'csv');
        $settlementJob = new GenerateReconciliationExport($settlementExport->id);
        $settlementJob->handle(app(ReconciliationExportQueryService::class));
        $settlementCsv = Storage::disk('local')->get($settlementExport->fresh()->path);
        $this->assertStringContainsString("'=paystack", $settlementCsv);
        $this->assertStringContainsString("'+SETTLEMENT", $settlementCsv);
        $this->assertStringContainsString('"10000","200","9800","9800"', $settlementCsv);
        $this->assertStringNotContainsString('provider-secret', $settlementCsv);
        $this->assertStringNotContainsString('sensitive diagnostic', $settlementCsv);
        $this->assertSame(2, DB::table('audit_logs')->where('action','data_export.ready')->count());
    }

    public function test_detail_exports_are_parent_scoped_snapshotted_and_exclude_encrypted_details(): void
    {
        Queue::fake(); Storage::fake('local');
        $this->insertRun(1,'paystack','mismatched','2026-11-03');
        $this->insertRunEntry(1,1,'=PAY-REF','amount_mismatch');
        $runExport = app(DataExportService::class)->queueReconciliationEntries(7,'payment_reconciliation_entries',1,['result'=>'amount_mismatch'],'csv');
        $this->assertSame(1,$runExport->row_count);
        $this->assertSame(1,$runExport->filters['max_entry_id']);
        $this->insertRunEntry(2,1,'LATE-REF','amount_mismatch');
        $runJob = new GenerateReconciliationExport($runExport->id);
        $runJob->handle(app(ReconciliationExportQueryService::class));
        $runCsv = Storage::disk('local')->get($runExport->fresh()->path);
        $this->assertStringContainsString("'=PAY-REF",$runCsv);
        $this->assertStringNotContainsString('LATE-REF',$runCsv);
        $this->assertStringNotContainsString('encrypted-entry-secret',$runCsv);

        $this->insertSettlement(1,'paystack','SET-1','mismatched','2026-11-03 09:00:00');
        $this->insertSettlementEntry(1,1,'+SET-REF','currency_mismatch');
        $settlementExport = app(DataExportService::class)->queueReconciliationEntries(7,'gateway_settlement_entries',1,['result'=>'currency_mismatch'],'csv');
        $settlementJob = new GenerateReconciliationExport($settlementExport->id);
        $settlementJob->handle(app(ReconciliationExportQueryService::class));
        $settlementCsv = Storage::disk('local')->get($settlementExport->fresh()->path);
        $this->assertStringContainsString("'+SET-REF",$settlementCsv);
        $this->assertStringContainsString('"10000","9800","200"',$settlementCsv);
        $this->assertStringNotContainsString('encrypted-entry-secret',$settlementCsv);
        Queue::assertPushed(GenerateReconciliationExport::class,2);
    }

    public function test_invalid_empty_or_unsupported_requests_fail_closed(): void
    {
        Queue::fake();
        foreach ([
            [0,'payment_reconciliation_runs',[],'csv'],
            [7,'other',[],'csv'],
            [7,'payment_reconciliation_runs',[],'pdf'],
            [7,'payment_reconciliation_runs',['gateway'=>'other'],'csv'],
            [7,'gateway_settlements',['status'=>'running'],'csv'],
            [7,'gateway_settlements',['date'=>'2026-99-99'],'csv'],
            [7,'gateway_settlements',[],'csv'],
        ] as [$admin,$type,$filters,$format]) {
            try {
                app(DataExportService::class)->queueReconciliation($admin,$type,$filters,$format);
                $this->fail('Expected reconciliation export rejection.');
            } catch (DomainException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertDatabaseCount('data_exports', 0);
        Queue::assertNothingPushed();
    }

    public function test_routes_are_transaction_protected_post_signed_and_downloads_are_actor_owned(): void
    {
        foreach (['admin.payment_reconciliation.export','admin.settlement_reconciliation.export'] as $name) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertSame(['POST'], $route->methods());
            $this->assertContains('permission:Transaction', $route->gatherMiddleware());
        }
        foreach (['admin.payment_reconciliation.entries.export','admin.settlement_reconciliation.entries.export'] as $name) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertSame(['POST'],$route->methods());
            $this->assertContains('permission:Transaction',$route->gatherMiddleware());
        }
        $this->assertContains('signed', Route::getRoutes()->getByName('admin.reconciliation_exports.download')->gatherMiddleware());

        Storage::fake('local');
        DB::table('admins')->insert([['id'=>7,'username'=>'finance','status'=>1],['id'=>8,'username'=>'other','status'=>1]]);
        $export = DataExport::create([
            'requested_by_type'=>'admin','requested_by_id'=>7,'export_type'=>'gateway_settlements',
            'format'=>'csv','status'=>'ready','filters'=>[],'disk'=>'local',
            'path'=>'exports/gateway-settlements/test.csv','filename'=>'test.csv','row_count'=>1,
            'expires_at'=>now()->addHour(),
        ]);
        Storage::disk('local')->put($export->path, 'evidence');

        Auth::guard('admin')->setUser(Admin::findOrFail(8));
        try {
            (new ReconciliationExportController())->download($export);
            $this->fail('Cross-admin download must fail.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        Auth::guard('admin')->setUser(Admin::findOrFail(7));
        $response = (new ReconciliationExportController())->download($export);
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertNotNull($export->fresh()->downloaded_at);
    }

    private function insertRun(int $id, string $gateway, string $status, string $date): void
    {
        DB::table('payment_reconciliation_runs')->insert([
            'id'=>$id,'public_id'=>'00000000-0000-4000-8000-'.str_pad((string)$id,12,'0',STR_PAD_LEFT),
            'gateway'=>$gateway,'reconciliation_date'=>$date,'status'=>$status,
            'gateway_count'=>10,'local_count'=>9,'matched_count'=>8,'mismatch_count'=>1,
            'last_error'=>'sensitive diagnostic','started_at'=>$date.' 01:00:00','completed_at'=>$date.' 01:05:00',
            'created_at'=>$date.' 01:00:00','updated_at'=>$date.' 01:05:00',
        ]);
    }

    private function insertSettlement(int $id, string $gateway, string $reference, string $status, string $date): void
    {
        DB::table('gateway_settlements')->insert([
            'id'=>$id,'gateway'=>$gateway,'gateway_settlement_id'=>$reference,'status'=>'success','currency'=>'NGN',
            'total_processed'=>10000,'total_fees'=>200,'total_amount'=>9800,'effective_amount'=>9800,
            'transaction_count'=>4,'matched_count'=>3,'mismatch_count'=>1,'reconciliation_status'=>$status,
            'gateway_payload'=>encrypt(['secret'=>'provider-secret']),'last_error'=>'sensitive diagnostic',
            'settlement_date'=>$date,'fetched_at'=>$date,'reconciled_at'=>$date,
            'created_at'=>$date,'updated_at'=>$date,
        ]);
    }

    private function insertRunEntry(int $id,int $run,string $reference,string $result): void
    {
        DB::table('payment_reconciliation_entries')->insert(['id'=>$id,'run_id'=>$run,'payment_intent_id'=>11,'booking_id'=>22,'gateway'=>'paystack','reference'=>$reference,'result'=>$result,'expected_amount'=>10000,'gateway_amount'=>9800,'expected_currency'=>'NGN','gateway_currency'=>'NGN','local_status'=>'completed','gateway_status'=>'success','details'=>encrypt(['secret'=>'encrypted-entry-secret']),'checked_at'=>'2026-11-03 09:00:00','created_at'=>'2026-11-03 09:00:00','updated_at'=>'2026-11-03 09:00:00']);
    }

    private function insertSettlementEntry(int $id,int $settlement,string $reference,string $result): void
    {
        DB::table('gateway_settlement_entries')->insert(['id'=>$id,'gateway_settlement_id'=>$settlement,'payment_intent_id'=>11,'booking_id'=>22,'reference'=>$reference,'result'=>$result,'expected_amount'=>10000,'gateway_amount'=>9800,'gateway_fee'=>200,'expected_currency'=>'NGN','gateway_currency'=>'NGN','local_status'=>'completed','gateway_status'=>'success','details'=>encrypt(['secret'=>'encrypted-entry-secret']),'checked_at'=>'2026-11-03 09:00:00','created_at'=>'2026-11-03 09:00:00','updated_at'=>'2026-11-03 09:00:00']);
    }

    private function createSchema(): void
    {
        Schema::create('admins', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('role_id')->nullable(); $table->string('username');
            $table->string('status')->default('1'); $table->timestamps();
        });
        Schema::create('payment_reconciliation_runs', function (Blueprint $table) {
            $table->id(); $table->uuid('public_id')->unique(); $table->string('gateway'); $table->date('reconciliation_date');
            $table->string('status'); $table->unsignedInteger('gateway_count'); $table->unsignedInteger('local_count');
            $table->unsignedInteger('matched_count'); $table->unsignedInteger('mismatch_count'); $table->text('last_error')->nullable();
            $table->timestamp('started_at'); $table->timestamp('completed_at')->nullable(); $table->timestamps();
        });
        Schema::create('gateway_settlements', function (Blueprint $table) {
            $table->id(); $table->string('gateway'); $table->string('gateway_settlement_id'); $table->string('status');
            $table->string('currency')->nullable(); $table->decimal('total_processed',18,2); $table->decimal('total_fees',18,2);
            $table->decimal('total_amount',18,2); $table->decimal('effective_amount',18,2); $table->unsignedInteger('transaction_count');
            $table->unsignedInteger('matched_count'); $table->unsignedInteger('mismatch_count'); $table->string('reconciliation_status');
            $table->longText('gateway_payload')->nullable(); $table->text('last_error')->nullable(); $table->timestamp('settlement_date')->nullable();
            $table->timestamp('fetched_at'); $table->timestamp('reconciled_at')->nullable(); $table->timestamps();
        });
        Schema::create('payment_reconciliation_entries',function(Blueprint $table){$table->id();$table->unsignedBigInteger('run_id');$table->unsignedBigInteger('payment_intent_id')->nullable();$table->unsignedBigInteger('booking_id')->nullable();$table->string('gateway');$table->string('reference');$table->string('result');$table->decimal('expected_amount',18,2)->nullable();$table->decimal('gateway_amount',18,2)->nullable();$table->string('expected_currency')->nullable();$table->string('gateway_currency')->nullable();$table->string('local_status')->nullable();$table->string('gateway_status')->nullable();$table->longText('details')->nullable();$table->timestamp('checked_at');$table->timestamps();});
        Schema::create('gateway_settlement_entries',function(Blueprint $table){$table->id();$table->unsignedBigInteger('gateway_settlement_id');$table->unsignedBigInteger('payment_intent_id')->nullable();$table->unsignedBigInteger('booking_id')->nullable();$table->string('reference');$table->string('result');$table->decimal('expected_amount',18,2)->nullable();$table->decimal('gateway_amount',18,2);$table->decimal('gateway_fee',18,2);$table->string('expected_currency')->nullable();$table->string('gateway_currency')->nullable();$table->string('local_status')->nullable();$table->string('gateway_status')->nullable();$table->longText('details')->nullable();$table->timestamp('checked_at');$table->timestamps();});
        Schema::create('data_exports', function (Blueprint $table) {
            $table->id(); $table->uuid('public_id')->unique(); $table->string('requested_by_type',20); $table->unsignedBigInteger('requested_by_id');
            $table->unsignedBigInteger('organizer_id')->nullable(); $table->unsignedBigInteger('schedule_id')->nullable(); $table->string('export_type',40);
            $table->string('format',8); $table->string('status',20); $table->longText('filters')->nullable(); $table->string('disk',40);
            $table->string('path',500)->nullable(); $table->string('filename',191); $table->unsignedBigInteger('row_count')->default(0);
            $table->unsignedSmallInteger('attempts')->default(0); $table->text('last_error')->nullable(); $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable(); $table->timestamp('expires_at')->nullable(); $table->timestamp('downloaded_at')->nullable(); $table->timestamps();
        });
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id(); $table->string('actor_type'); $table->unsignedBigInteger('actor_id')->nullable(); $table->string('action');
            $table->string('subject_type')->nullable(); $table->unsignedBigInteger('subject_id')->nullable(); $table->json('before')->nullable();
            $table->json('after')->nullable(); $table->json('metadata')->nullable(); $table->string('request_id')->nullable();
            $table->string('ip_address')->nullable(); $table->text('user_agent')->nullable(); $table->timestamp('created_at')->nullable();
        });
    }
}
