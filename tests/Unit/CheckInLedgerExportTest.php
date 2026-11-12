<?php

namespace Tests\Unit;

use App\Http\Controllers\BackEnd\Organizer\CheckInLedgerExportController;
use App\Jobs\GenerateCheckInLedgerExport;
use App\Models\DataExport;
use App\Models\Organizer;
use App\Models\ScannerStaff;
use App\Services\CheckInLedgerExportQueryService;
use App\Services\DataExportService;
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

class CheckInLedgerExportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', ['driver'=>'sqlite','database'=>':memory:','prefix'=>'','foreign_key_constraints'=>true]);
        config()->set('app.key', 'base64:'.base64_encode(str_repeat('l', 32)));
        config()->set('exports.disk', 'local');
        config()->set('exports.retention_days', 7);
        config()->set('exports.max_rows', 250000);
        DB::purge('sqlite'); DB::setDefaultConnection('sqlite');
        $this->createSchema();
        $this->organizer(10, 'alpha'); $this->organizer(20, 'beta');
        DB::table('events')->insert([
            ['id'=>100,'organizer_id'=>10,'status'=>1], ['id'=>200,'organizer_id'=>20,'status'=>1],
        ]);
    }

    public function test_export_is_event_scoped_encrypted_snapshotted_and_queued(): void
    {
        Queue::fake();
        $this->accepted(1, 10, 100, '=BOOK-ONE');
        $this->accepted(2, 20, 200, 'OTHER-BOOKING');

        $export = app(DataExportService::class)->queueCheckInLedger('organizer', 10, 10, 100, 'csv');

        $this->assertSame('check_in_ledger', $export->export_type);
        $this->assertSame(10, $export->organizer_id);
        $this->assertSame(1, $export->row_count);
        $this->assertSame(1, $export->filters['max_ticket_id']);
        $this->assertStringNotContainsString('100', (string) DB::table('data_exports')->value('filters'));
        Queue::assertPushed(GenerateCheckInLedgerExport::class, fn ($job) => $job->exportId === $export->id);
    }

    public function test_generator_contains_accepted_and_duplicate_evidence_without_customer_contact_data(): void
    {
        Queue::fake(); Storage::fake('local');
        $this->accepted(1, 10, 100, '=BOOK-ONE');
        $this->duplicate(1, 1, 10, 100);
        $export = app(DataExportService::class)->queueCheckInLedger('scanner', 5, 10, 100, 'csv');
        $this->accepted(3, 10, 100, 'LATE-BOOKING');

        $job = new GenerateCheckInLedgerExport($export->id);
        $job->handle(app(CheckInLedgerExportQueryService::class));
        $csv = Storage::disk('local')->get($export->fresh()->path);

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('accepted', $csv);
        $this->assertStringContainsString('duplicate_attempt', $csv);
        $this->assertStringContainsString("'=BOOK-ONE", $csv);
        $this->assertStringContainsString('Main gate', $csv);
        $this->assertStringContainsString('VIP gate', $csv);
        $this->assertStringContainsString('Gate attendant', $csv);
        $this->assertStringContainsString('Gate supervisor', $csv);
        $this->assertStringContainsString('manual', $csv);
        $this->assertStringNotContainsString('customer@example.test', $csv);
        $this->assertStringNotContainsString('Private Customer', $csv);
        $this->assertStringNotContainsString('LATE-BOOKING', $csv);
        $job->handle(app(CheckInLedgerExportQueryService::class));
        $this->assertSame(1, $export->fresh()->attempts);
    }

    public function test_invalid_cross_organizer_unknown_event_and_empty_ledger_fail_closed(): void
    {
        Queue::fake();
        foreach ([
            ['admin',1,10,100,'csv'], ['organizer',10,20,200,'csv'],
            ['organizer',10,10,100,'pdf'], ['organizer',10,10,999,'csv'],
            ['scanner',0,10,100,'csv'],
        ] as [$type,$actor,$organizer,$event,$format]) {
            try {
                app(DataExportService::class)->queueCheckInLedger($type,$actor,$organizer,$event,$format);
                $this->fail('Expected check-in export rejection.');
            } catch (DomainException) {
                $this->addToAssertionCount(1);
            }
        }
        try {
            app(DataExportService::class)->queueCheckInLedger('organizer',10,10,100,'csv');
            $this->fail('Empty ledger must fail.');
        } catch (DomainException) {
            $this->addToAssertionCount(1);
        }
        $this->assertDatabaseCount('data_exports', 0);
        Queue::assertNothingPushed();
    }

    public function test_routes_protect_supervisor_access_and_downloads_are_signed_actor_owned(): void
    {
        $this->assertSame(['POST'], Route::getRoutes()->getByName('organizer.check_in_ledger_exports.store')->methods());
        $this->assertContains('signed', Route::getRoutes()->getByName('organizer.check_in_ledger_exports.download')->gatherMiddleware());
        $scannerStore = Route::getRoutes()->getByName('organizer.scanner.check_in_ledger_exports.store');
        $this->assertContains('scanner.supervisor', $scannerStore->gatherMiddleware());
        $this->assertContains('signed', Route::getRoutes()->getByName('organizer.scanner.check_in_ledger_exports.download')->gatherMiddleware());

        Storage::fake('local');
        $export = $this->readyExport('organizer', 10, 10);
        Storage::disk('local')->put($export->path, 'ledger');
        Auth::guard('organizer')->setUser(Organizer::findOrFail(20));
        try {
            (new CheckInLedgerExportController())->download(Request::create('/'), $export);
            $this->fail('Cross-organizer download must fail.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        Auth::guard('organizer')->setUser(Organizer::findOrFail(10));
        $response = (new CheckInLedgerExportController())->download(Request::create('/'), $export);
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    private function organizer(int $id, string $name): void
    {
        DB::table('organizers')->insert(['id'=>$id,'username'=>$name,'email'=>$name.'@example.test','password'=>'test','status'=>1,'amount'=>0]);
    }

    private function accepted(int $id, int $organizer, int $event, string $reference): void
    {
        DB::table('bookings')->insert(['id'=>$id,'booking_id'=>$reference,'email'=>'customer@example.test']);
        DB::table('booking_tickets')->insert([
            'id'=>$id,'public_id'=>sprintf('00000000-0000-4000-8000-%012d',$id),
            'booking_id'=>$id,'event_id'=>$event,'organizer_id'=>$organizer,'ticket_id'=>50,
            'variation_name'=>'Regular','holder_name'=>'Private Customer','holder_email'=>'customer@example.test',
            'status'=>'checked_in','checked_in_at'=>'2026-11-05 12:00:00',
            'checked_in_scanner_device_id'=>1,'checked_in_scanner_staff_id'=>1,'checked_in_source'=>'manual',
        ]);
        if (!DB::table('scanner_devices')->where('id',1)->exists()) {
            DB::table('scanner_devices')->insert(['id'=>1,'public_id'=>'device-main','organizer_id'=>10,'name'=>'Main gate']);
            DB::table('scanner_devices')->insert(['id'=>2,'public_id'=>'device-vip','organizer_id'=>10,'name'=>'VIP gate']);
            DB::table('scanner_staff')->insert(['id'=>1,'organizer_id'=>10,'name'=>'Gate attendant','role'=>'check_in']);
            DB::table('scanner_staff')->insert(['id'=>2,'organizer_id'=>10,'name'=>'Gate supervisor','role'=>'supervisor']);
        }
    }

    private function duplicate(int $id, int $ticket, int $organizer, int $event): void
    {
        DB::table('scanner_check_in_conflicts')->insert([
            'id'=>$id,'organizer_id'=>$organizer,'event_id'=>$event,'booking_ticket_id'=>$ticket,
            'original_scanner_device_id'=>1,'attempted_scanner_device_id'=>2,
            'original_scanner_staff_id'=>1,'attempted_scanner_staff_id'=>2,
            'source'=>'scanner','occurrences'=>2,'first_detected_at'=>'2026-11-05 12:05:00',
            'last_detected_at'=>'2026-11-05 12:06:00','resolved_at'=>null,
        ]);
    }

    private function readyExport(string $type, int $actor, int $organizer): DataExport
    {
        return DataExport::create([
            'requested_by_type'=>$type,'requested_by_id'=>$actor,'organizer_id'=>$organizer,
            'export_type'=>'check_in_ledger','format'=>'csv','status'=>'ready',
            'filters'=>['event_id'=>100],'disk'=>'local','path'=>'exports/check-in-ledgers/test.csv',
            'filename'=>'test.csv','row_count'=>1,'expires_at'=>now()->addHour(),
        ]);
    }

    private function createSchema(): void
    {
        Schema::create('organizers',fn(Blueprint $t)=>[$t->id(),$t->string('username'),$t->string('email'),$t->string('password'),$t->integer('status'),$t->decimal('amount',18,2)]);
        Schema::create('events',fn(Blueprint $t)=>[$t->id(),$t->unsignedBigInteger('organizer_id'),$t->integer('status')]);
        Schema::create('bookings',fn(Blueprint $t)=>[$t->id(),$t->string('booking_id'),$t->string('email')->nullable()]);
        Schema::create('booking_tickets',function(Blueprint $t){$t->id();$t->uuid('public_id');$t->unsignedBigInteger('booking_id');$t->unsignedBigInteger('event_id');$t->unsignedBigInteger('organizer_id');$t->unsignedBigInteger('ticket_id')->nullable();$t->string('variation_name')->nullable();$t->string('holder_name')->nullable();$t->string('holder_email')->nullable();$t->string('status');$t->timestamp('checked_in_at')->nullable();$t->unsignedBigInteger('checked_in_scanner_device_id')->nullable();$t->unsignedBigInteger('checked_in_scanner_staff_id')->nullable();$t->string('checked_in_source',32)->nullable();});
        Schema::create('scanner_devices',fn(Blueprint $t)=>[$t->id(),$t->string('public_id'),$t->unsignedBigInteger('organizer_id'),$t->string('name')]);
        Schema::create('scanner_staff',fn(Blueprint $t)=>[$t->id(),$t->unsignedBigInteger('organizer_id'),$t->string('name'),$t->string('role')]);
        Schema::create('scanner_check_in_conflicts',function(Blueprint $t){$t->id();$t->unsignedBigInteger('organizer_id');$t->unsignedBigInteger('event_id');$t->unsignedBigInteger('booking_ticket_id');$t->unsignedBigInteger('original_scanner_device_id');$t->unsignedBigInteger('attempted_scanner_device_id');$t->unsignedBigInteger('original_scanner_staff_id')->nullable();$t->unsignedBigInteger('attempted_scanner_staff_id')->nullable();$t->string('source');$t->unsignedInteger('occurrences');$t->timestamp('first_detected_at');$t->timestamp('last_detected_at');$t->timestamp('resolved_at')->nullable();});
        Schema::create('data_exports',function(Blueprint $t){$t->id();$t->uuid('public_id')->unique();$t->string('requested_by_type',20);$t->unsignedBigInteger('requested_by_id');$t->unsignedBigInteger('organizer_id')->nullable();$t->unsignedBigInteger('schedule_id')->nullable();$t->string('export_type',40);$t->string('format',8);$t->string('status',20);$t->longText('filters')->nullable();$t->string('disk',40);$t->string('path',500)->nullable();$t->string('filename',191);$t->unsignedBigInteger('row_count')->default(0);$t->unsignedSmallInteger('attempts')->default(0);$t->text('last_error')->nullable();$t->timestamp('started_at')->nullable();$t->timestamp('completed_at')->nullable();$t->timestamp('expires_at')->nullable();$t->timestamp('downloaded_at')->nullable();$t->timestamps();});
        Schema::create('audit_logs',function(Blueprint $t){$t->id();$t->string('actor_type');$t->unsignedBigInteger('actor_id')->nullable();$t->string('action');$t->string('subject_type')->nullable();$t->unsignedBigInteger('subject_id')->nullable();$t->json('before')->nullable();$t->json('after')->nullable();$t->json('metadata')->nullable();$t->string('request_id')->nullable();$t->string('ip_address')->nullable();$t->text('user_agent')->nullable();$t->timestamp('created_at')->nullable();});
    }
}
