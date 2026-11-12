<?php

namespace Tests\Unit;

use App\Http\Controllers\BackEnd\Organizer\TransactionExportController as OrganizerExportController;
use App\Http\Controllers\BackEnd\TransactionExportController as AdminExportController;
use App\Jobs\GenerateTransactionExport;
use App\Models\Admin;
use App\Models\DataExport;
use App\Models\Organizer;
use App\Services\DataExportService;
use App\Services\TransactionExportQueryService;
use DomainException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TransactionDataExportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', ['driver'=>'sqlite','database'=>':memory:','prefix'=>'','foreign_key_constraints'=>true]);
        config()->set('app.key', 'base64:'.base64_encode(str_repeat('t', 32)));
        config()->set('exports.disk', 'local');
        config()->set('exports.retention_days', 7);
        config()->set('exports.max_rows', 250000);
        DB::purge('sqlite'); DB::setDefaultConnection('sqlite');
        $this->createSchema();
        $this->insertOrganizer(10, 'alpha'); $this->insertOrganizer(20, 'beta');
        DB::table('withdraw_payment_methods')->insert(['id'=>1,'name'=>'Bank transfer']);
    }

    public function test_organizer_transaction_export_is_scoped_encrypted_snapshotted_and_queued(): void
    {
        Queue::fake();
        $this->insertTransaction(1, 10, '=ALPHA-REF', 1, 1, '2026-11-02 10:00:00');
        $this->insertTransaction(2, 20, 'BETA-REF', 1, 1, '2026-11-02 11:00:00');
        $export = app(DataExportService::class)->queueTransactions('organizer', 10, 10, [
            'transaction_reference'=>'=ALPHA','transaction_type'=>1,'payment_status'=>1,
            'from_date'=>'2026-11-01','to_date'=>'2026-11-30',
        ], 'csv');
        $this->assertSame('transactions', $export->export_type);
        $this->assertSame(10, $export->organizer_id);
        $this->assertSame(1, $export->row_count);
        $this->assertSame(1, $export->filters['max_transaction_id']);
        $this->assertStringNotContainsString('ALPHA', (string) DB::table('data_exports')->value('filters'));
        Queue::assertPushed(GenerateTransactionExport::class, fn($job) => $job->exportId === $export->id);
        $this->assertDatabaseHas('audit_logs', ['action'=>'data_export.queued','subject_id'=>$export->id]);
    }

    public function test_generator_is_formula_safe_analysis_ready_snapshot_bound_and_idempotent(): void
    {
        Queue::fake(); Storage::fake('local');
        $this->insertTransaction(1, 10, '=TX-ONE', 1, 1, '2026-11-02 10:00:00');
        $export = app(DataExportService::class)->queueTransactions('admin', 7, null, [], 'csv');
        $this->insertTransaction(2, 10, 'LATE-TX', 2, 0, '2026-11-03 10:00:00');
        $job = new GenerateTransactionExport($export->id);
        $job->handle(app(TransactionExportQueryService::class));
        $ready = $export->fresh(); $csv = Storage::disk('local')->get($ready->path);
        $this->assertSame('ready', $ready->status);
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('"ledger_id","transaction_reference","gateway","gateway_reference"', $csv);
        $this->assertStringContainsString("'=TX-ONE", $csv);
        $this->assertStringContainsString("'+GATEWAY-REF", $csv);
        $this->assertStringContainsString('event_booking', $csv);
        $this->assertStringContainsString('2026-11-02T10:00:00', $csv);
        $this->assertStringNotContainsString('LATE-TX', $csv);
        $job->handle(app(TransactionExportQueryService::class));
        $this->assertSame(1, $ready->fresh()->attempts);
        $this->assertSame(1, DB::table('audit_logs')->where('action','data_export.ready')->count());
    }

    public function test_invalid_cross_organizer_or_empty_export_fails_closed(): void
    {
        Queue::fake();
        foreach ([
            ['admin',7,null,[],'pdf'], ['organizer',10,20,[],'csv'], ['organizer',10,10,[],'xlsx'],
        ] as [$type,$actor,$organizer,$filters,$format]) {
            try { app(DataExportService::class)->queueTransactions($type,$actor,$organizer,$filters,$format); $this->fail('Expected rejection.'); }
            catch (DomainException) { $this->addToAssertionCount(1); }
        }
        $this->assertDatabaseCount('data_exports',0); Queue::assertNothingPushed();
    }

    public function test_routes_are_post_signed_and_downloads_are_actor_owned_and_no_store(): void
    {
        $this->assertSame(['POST'], Route::getRoutes()->getByName('admin.transaction_exports.store')->methods());
        $this->assertContains('permission:Transaction', Route::getRoutes()->getByName('admin.transaction_exports.store')->gatherMiddleware());
        $this->assertContains('signed', Route::getRoutes()->getByName('admin.transaction_exports.download')->gatherMiddleware());
        $this->assertSame(['POST'], Route::getRoutes()->getByName('organizer.transaction_exports.store')->methods());
        $this->assertContains('signed', Route::getRoutes()->getByName('organizer.transaction_exports.download')->gatherMiddleware());
        Storage::fake('local');
        DB::table('admins')->insert([['id'=>7,'username'=>'finance','status'=>1],['id'=>8,'username'=>'other','status'=>1]]);
        $adminExport = $this->readyExport('admin',7,null,'exports/transactions/admin.csv'); Storage::disk('local')->put($adminExport->path,'admin');
        Auth::guard('admin')->setUser(Admin::findOrFail(8));
        try { (new AdminExportController())->download($adminExport); $this->fail('Cross-admin download must fail.'); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { $this->assertSame(403,$e->getStatusCode()); }
        Auth::guard('admin')->setUser(Admin::findOrFail(7));
        $this->assertStringContainsString('no-store',(string)(new AdminExportController())->download($adminExport)->headers->get('Cache-Control'));
        Auth::guard('admin')->forgetUser();
        $orgExport = $this->readyExport('organizer',10,10,'exports/transactions/organizer.csv'); Storage::disk('local')->put($orgExport->path,'organizer');
        Auth::guard('organizer')->setUser(Organizer::findOrFail(20));
        try { (new OrganizerExportController())->download($orgExport); $this->fail('Cross-organizer download must fail.'); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { $this->assertSame(403,$e->getStatusCode()); }
        Auth::guard('organizer')->setUser(Organizer::findOrFail(10));
        $this->assertStringContainsString('no-store',(string)(new OrganizerExportController())->download($orgExport)->headers->get('Cache-Control'));
    }

    private function readyExport(string $type,int $actor,?int $organizer,string $path): DataExport
    {
        return DataExport::create(['requested_by_type'=>$type,'requested_by_id'=>$actor,'organizer_id'=>$organizer,'export_type'=>'transactions','format'=>'csv','status'=>'ready','filters'=>[],'disk'=>'local','path'=>$path,'filename'=>basename($path),'row_count'=>1,'expires_at'=>now()->addHour()]);
    }
    private function insertOrganizer(int $id,string $name): void
    {
        DB::table('organizers')->insert(['id'=>$id,'username'=>$name,'email'=>$name.'@example.test','password'=>'test','status'=>1,'amount'=>0,'created_at'=>now(),'updated_at'=>now()]);
    }
    private function insertTransaction(int $id,int $organizer,string $reference,int $type,int $status,string $created): void
    {
        DB::table('transactions')->insert(['id'=>$id,'transcation_id'=>$reference,'gateway'=>'=paystack','gateway_reference'=>'+GATEWAY-REF'.$id,'booking_id'=>'99','transcation_type'=>$type,'customer_id'=>5,'organizer_id'=>$organizer,'payment_status'=>$status,'payment_method'=>$type===3?'1':'@card','grand_total'=>10000,'commission'=>500,'tax'=>250,'pre_balance'=>1000,'after_balance'=>10500,'gateway_type'=>'online','currency_symbol'=>'₦','currency_symbol_position'=>'left','created_at'=>$created,'updated_at'=>$created]);
    }
    private function createSchema(): void
    {
        Schema::create('admins',fn(Blueprint $t)=>[$t->id(),$t->unsignedBigInteger('role_id')->nullable(),$t->string('username'),$t->string('status')->default('1'),$t->timestamps()]);
        Schema::create('organizers',fn(Blueprint $t)=>[$t->id(),$t->string('username'),$t->string('email'),$t->string('password'),$t->integer('status')->default(1),$t->decimal('amount',18,2)->default(0),$t->timestamps()]);
        Schema::create('withdraw_payment_methods',fn(Blueprint $t)=>[$t->id(),$t->string('name'),$t->timestamps()]);
        Schema::create('transactions',function(Blueprint $t){$t->id();$t->string('transcation_id')->nullable();$t->string('gateway',40)->nullable();$t->string('gateway_reference')->nullable();$t->string('booking_id')->nullable();$t->integer('transcation_type');$t->unsignedBigInteger('customer_id')->nullable();$t->unsignedBigInteger('organizer_id')->nullable();$t->string('payment_status');$t->string('payment_method')->nullable();$t->decimal('grand_total',18,2);$t->decimal('commission',18,2)->default(0);$t->decimal('tax',18,2)->default(0);$t->decimal('pre_balance',18,2)->default(0);$t->decimal('after_balance',18,2)->nullable();$t->string('gateway_type')->nullable();$t->string('currency_symbol')->nullable();$t->string('currency_symbol_position')->nullable();$t->timestamps();});
        Schema::create('data_exports',function(Blueprint $t){$t->id();$t->uuid('public_id')->unique();$t->string('requested_by_type',20);$t->unsignedBigInteger('requested_by_id');$t->unsignedBigInteger('organizer_id')->nullable();$t->unsignedBigInteger('schedule_id')->nullable();$t->string('export_type',40);$t->string('format',8);$t->string('status',20);$t->longText('filters')->nullable();$t->string('disk',40);$t->string('path',500)->nullable();$t->string('filename',191);$t->unsignedBigInteger('row_count')->default(0);$t->unsignedSmallInteger('attempts')->default(0);$t->text('last_error')->nullable();$t->timestamp('started_at')->nullable();$t->timestamp('completed_at')->nullable();$t->timestamp('expires_at')->nullable();$t->timestamp('downloaded_at')->nullable();$t->timestamps();});
        Schema::create('audit_logs',function(Blueprint $t){$t->id();$t->string('actor_type');$t->unsignedBigInteger('actor_id')->nullable();$t->string('action');$t->string('subject_type')->nullable();$t->unsignedBigInteger('subject_id')->nullable();$t->json('before')->nullable();$t->json('after')->nullable();$t->json('metadata')->nullable();$t->string('request_id')->nullable();$t->string('ip_address')->nullable();$t->text('user_agent')->nullable();$t->timestamp('created_at')->nullable();});
    }
}
