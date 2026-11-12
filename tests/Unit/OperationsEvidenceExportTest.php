<?php

namespace Tests\Unit;

use App\Http\Controllers\BackEnd\OperationsEvidenceExportController;
use App\Jobs\GenerateOperationsEvidenceExport;
use App\Models\Admin;
use App\Models\DataExport;
use App\Services\DataExportService;
use App\Services\OperationsEvidenceExportQueryService;
use DomainException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OperationsEvidenceExportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', ['driver'=>'sqlite','database'=>':memory:','prefix'=>'','foreign_key_constraints'=>true]);
        config()->set('app.key', 'base64:'.base64_encode(str_repeat('o', 32)));
        config()->set('exports.disk', 'local');
        config()->set('exports.retention_days', 7);
        config()->set('exports.max_rows', 250000);
        DB::purge('sqlite'); DB::setDefaultConnection('sqlite');
        $this->createSchema();
    }

    public function test_incident_export_is_filtered_encrypted_snapshotted_and_queued(): void
    {
        Queue::fake();
        $this->insertIncident(1, 'critical', 'open', '=queue stalled');
        $this->insertIncident(2, 'warning', 'resolved', 'mail delayed');
        $export = app(DataExportService::class)->queueOperationsEvidence(7, 'operational_incidents', [
            'status'=>'open', 'severity'=>'critical',
        ], 'csv');
        $this->assertSame('operational_incidents', $export->export_type);
        $this->assertSame(1, $export->row_count);
        $this->assertSame(2, $export->filters['max_incident_id']);
        $this->assertStringNotContainsString('critical', (string) DB::table('data_exports')->value('filters'));
        Queue::assertPushed(GenerateOperationsEvidenceExport::class, fn($job) => $job->exportId === $export->id);
        $this->assertDatabaseHas('audit_logs', ['action'=>'data_export.queued','subject_id'=>$export->id]);
    }

    public function test_generators_minimize_sensitive_fields_are_formula_safe_and_snapshot_bound(): void
    {
        Queue::fake(); Storage::fake('local');
        $this->insertIncident(1, 'critical', 'open', '=queue stalled');
        $incidentExport = app(DataExportService::class)->queueOperationsEvidence(7, 'operational_incidents', [], 'csv');
        $this->insertIncident(2, 'warning', 'open', 'late incident');
        $job = new GenerateOperationsEvidenceExport($incidentExport->id);
        $job->handle(app(OperationsEvidenceExportQueryService::class));
        $incidentCsv = Storage::disk('local')->get($incidentExport->fresh()->path);
        $this->assertStringStartsWith("\xEF\xBB\xBF", $incidentCsv);
        $this->assertStringContainsString("'=queue stalled", $incidentCsv);
        $this->assertStringNotContainsString('late incident', $incidentCsv);
        $this->assertStringNotContainsString('context-secret', $incidentCsv);
        $this->assertStringNotContainsString('private resolution', $incidentCsv);
        $this->assertStringNotContainsString('key-hash-secret', $incidentCsv);
        $job->handle(app(OperationsEvidenceExportQueryService::class));
        $this->assertSame(1, $incidentExport->fresh()->attempts);

        $this->insertWebhook(1, 'paystack', '+charge.success', 'processed', '2026-11-03 10:00:00');
        $webhookExport = app(DataExportService::class)->queueOperationsEvidence(7, 'webhook_receipts', [
            'from'=>'2026-11-03','to'=>'2026-11-03','gateway'=>'paystack','outcome'=>'processed',
        ], 'csv');
        $this->insertWebhook(2, 'paystack', 'late', 'processed', '2026-11-04 10:00:00');
        $webhookJob = new GenerateOperationsEvidenceExport($webhookExport->id);
        $webhookJob->handle(app(OperationsEvidenceExportQueryService::class));
        $webhookCsv = Storage::disk('local')->get($webhookExport->fresh()->path);
        $this->assertStringContainsString('paystack', $webhookCsv);
        $this->assertStringContainsString("'+charge.success", $webhookCsv);
        $this->assertStringContainsString('body-sha256', $webhookCsv);
        $this->assertStringNotContainsString('reference-hash-secret', $webhookCsv);
        $this->assertStringNotContainsString('late', $webhookCsv);
        $this->assertSame(2, DB::table('audit_logs')->where('action','data_export.ready')->count());
    }

    public function test_invalid_empty_or_overscoped_requests_fail_closed(): void
    {
        Queue::fake();
        foreach ([
            [0,'operational_incidents',[],'csv'], [7,'other',[],'csv'],
            [7,'operational_incidents',[],'pdf'], [7,'operational_incidents',['severity'=>'notice'],'csv'],
            [7,'webhook_receipts',[],'csv'],
            [7,'webhook_receipts',['from'=>'2026-10-01','to'=>'2026-11-03'],'csv'],
            [7,'webhook_receipts',['from'=>'2026-11-03','to'=>'2026-11-03','gateway'=>'other'],'csv'],
        ] as [$admin,$type,$filters,$format]) {
            try { app(DataExportService::class)->queueOperationsEvidence($admin,$type,$filters,$format); $this->fail('Expected rejection.'); }
            catch (DomainException) { $this->addToAssertionCount(1); }
        }
        $this->assertDatabaseCount('data_exports',0); Queue::assertNothingPushed();
    }

    public function test_routes_are_transaction_protected_post_signed_and_downloads_are_actor_owned(): void
    {
        foreach (['admin.operational_incidents.export','admin.webhook_operations.export'] as $name) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertSame(['POST'], $route->methods());
            $this->assertContains('permission:Transaction', $route->gatherMiddleware());
        }
        $this->assertContains('signed', Route::getRoutes()->getByName('admin.operations_exports.download')->gatherMiddleware());
        Storage::fake('local');
        DB::table('admins')->insert([['id'=>7,'username'=>'ops','status'=>1],['id'=>8,'username'=>'other','status'=>1]]);
        $export = DataExport::create(['requested_by_type'=>'admin','requested_by_id'=>7,'export_type'=>'operational_incidents','format'=>'csv','status'=>'ready','filters'=>[],'disk'=>'local','path'=>'exports/operational-incidents/test.csv','filename'=>'test.csv','row_count'=>1,'expires_at'=>now()->addHour()]);
        Storage::disk('local')->put($export->path,'evidence');
        Auth::guard('admin')->setUser(Admin::findOrFail(8));
        try { (new OperationsEvidenceExportController())->download($export); $this->fail('Cross-admin download must fail.'); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { $this->assertSame(403,$e->getStatusCode()); }
        Auth::guard('admin')->setUser(Admin::findOrFail(7));
        $response = (new OperationsEvidenceExportController())->download($export);
        $this->assertStringContainsString('no-store',(string)$response->headers->get('Cache-Control'));
        $this->assertNotNull($export->fresh()->downloaded_at);
    }

    private function insertIncident(int $id,string $severity,string $status,string $title): void
    {
        DB::table('operational_incidents')->insert(['id'=>$id,'public_id'=>'10000000-0000-4000-8000-'.str_pad((string)$id,12,'0',STR_PAD_LEFT),'key_hash'=>'key-hash-secret'.$id,'incident_key'=>'=incident-'.$id,'severity'=>$severity,'status'=>$status,'title'=>$title,'occurrence_count'=>3,'context'=>encrypt(['secret'=>'context-secret']),'delivery_status'=>json_encode(['email'=>'sent','webhook'=>'failed']),'first_seen_at'=>'2026-11-03 08:00:00','last_seen_at'=>'2026-11-03 09:00:00','last_alerted_at'=>'2026-11-03 09:01:00','resolved_at'=>$status==='resolved'?'2026-11-03 10:00:00':null,'resolution_note'=>'private resolution','resolved_by_admin_id'=>$status==='resolved'?7:null,'created_at'=>'2026-11-03 08:00:00','updated_at'=>'2026-11-03 10:00:00']);
    }

    private function insertWebhook(int $id,string $gateway,string $event,string $outcome,string $received): void
    {
        DB::table('webhook_receipts')->insert(['id'=>$id,'public_id'=>'20000000-0000-4000-8000-'.str_pad((string)$id,12,'0',STR_PAD_LEFT),'gateway'=>$gateway,'event_type'=>$event,'body_hash'=>'body-sha256-'.$id,'reference_hash'=>'reference-hash-secret','request_id'=>'request-'.$id,'signature_present'=>1,'signature_valid'=>1,'outcome'=>$outcome,'http_status'=>200,'processing_ms'=>42,'error_code'=>null,'received_at'=>$received,'completed_at'=>$received,'created_at'=>$received,'updated_at'=>$received]);
    }

    private function createSchema(): void
    {
        Schema::create('admins',fn(Blueprint $t)=>[$t->id(),$t->unsignedBigInteger('role_id')->nullable(),$t->string('username'),$t->string('status')->default('1'),$t->timestamps()]);
        Schema::create('operational_incidents',function(Blueprint $t){$t->id();$t->uuid('public_id');$t->string('key_hash');$t->string('incident_key');$t->string('severity');$t->string('status');$t->string('title');$t->unsignedBigInteger('occurrence_count');$t->longText('context')->nullable();$t->json('delivery_status')->nullable();$t->timestamp('first_seen_at');$t->timestamp('last_seen_at');$t->timestamp('last_alerted_at')->nullable();$t->timestamp('resolved_at')->nullable();$t->text('resolution_note')->nullable();$t->unsignedBigInteger('resolved_by_admin_id')->nullable();$t->timestamps();});
        Schema::create('webhook_receipts',function(Blueprint $t){$t->id();$t->uuid('public_id');$t->string('gateway');$t->string('event_type')->nullable();$t->string('body_hash');$t->string('reference_hash')->nullable();$t->string('request_id')->nullable();$t->boolean('signature_present');$t->boolean('signature_valid')->nullable();$t->string('outcome');$t->unsignedSmallInteger('http_status')->nullable();$t->unsignedInteger('processing_ms')->nullable();$t->string('error_code')->nullable();$t->timestamp('received_at');$t->timestamp('completed_at')->nullable();$t->timestamps();});
        Schema::create('data_exports',function(Blueprint $t){$t->id();$t->uuid('public_id')->unique();$t->string('requested_by_type',20);$t->unsignedBigInteger('requested_by_id');$t->unsignedBigInteger('organizer_id')->nullable();$t->unsignedBigInteger('schedule_id')->nullable();$t->string('export_type',40);$t->string('format',8);$t->string('status',20);$t->longText('filters')->nullable();$t->string('disk',40);$t->string('path',500)->nullable();$t->string('filename',191);$t->unsignedBigInteger('row_count')->default(0);$t->unsignedSmallInteger('attempts')->default(0);$t->text('last_error')->nullable();$t->timestamp('started_at')->nullable();$t->timestamp('completed_at')->nullable();$t->timestamp('expires_at')->nullable();$t->timestamp('downloaded_at')->nullable();$t->timestamps();});
        Schema::create('audit_logs',function(Blueprint $t){$t->id();$t->string('actor_type');$t->unsignedBigInteger('actor_id')->nullable();$t->string('action');$t->string('subject_type')->nullable();$t->unsignedBigInteger('subject_id')->nullable();$t->json('before')->nullable();$t->json('after')->nullable();$t->json('metadata')->nullable();$t->string('request_id')->nullable();$t->string('ip_address')->nullable();$t->text('user_agent')->nullable();$t->timestamp('created_at')->nullable();});
    }
}
