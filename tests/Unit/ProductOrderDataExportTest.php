<?php

namespace Tests\Unit;

use App\Http\Controllers\BackEnd\ShopManagement\ProductOrderExportController;
use App\Jobs\GenerateProductOrderExport;
use App\Models\Admin;
use App\Models\DataExport;
use App\Services\DataExportService;
use App\Services\ProductOrderExportQueryService;
use DomainException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductOrderDataExportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        config()->set('app.key', 'base64:'.base64_encode(str_repeat('p', 32)));
        config()->set('exports.disk', 'local');
        config()->set('exports.retention_days', 7);
        config()->set('exports.max_rows', 250000);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        $this->createSchema();
    }

    public function test_product_order_export_is_private_encrypted_filtered_and_queued(): void
    {
        Queue::fake();
        $this->insertOrder(1, '=ORDER-ONE', '2026-11-02 10:00:00');
        $this->insertOrder(2, 'OLD-ORDER', '2026-10-01 10:00:00');

        $export = app(DataExportService::class)->queueProductOrders(7, [
            'from_date' => '2026-11-01', 'to_date' => '2026-11-30',
            'payment_method' => 'paystack', 'payment_status' => 'completed',
        ], 'csv');

        $this->assertSame('product_orders', $export->export_type);
        $this->assertSame(1, $export->row_count);
        $this->assertSame(2, $export->filters['max_order_id']);
        $this->assertStringNotContainsString('paystack', (string) DB::table('data_exports')->value('filters'));
        Queue::assertPushed(GenerateProductOrderExport::class, fn ($job) => $job->exportId === $export->id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'data_export.queued', 'subject_id' => $export->id]);
    }

    public function test_generator_is_snapshotted_formula_safe_analysis_ready_and_idempotent(): void
    {
        Queue::fake();
        Storage::fake('local');
        $this->insertOrder(1, '=ORDER-ONE', '2026-11-02 10:00:00');
        DB::table('order_items')->insert([
            'product_order_id' => 1, 'quantity' => 3, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $export = app(DataExportService::class)->queueProductOrders(7, [
            'from_date' => '2026-11-01', 'to_date' => '2026-11-30',
        ], 'csv');
        $this->insertOrder(2, 'LATE-ORDER', '2026-11-03 10:00:00');

        $job = new GenerateProductOrderExport($export->id);
        $job->handle(app(ProductOrderExportQueryService::class));
        $ready = $export->fresh();
        $csv = Storage::disk('local')->get($ready->path);

        $this->assertSame('ready', $ready->status);
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('"order_id","order_number","customer_id","customer_name","customer_email"', $csv);
        $this->assertStringContainsString("'=ORDER-ONE", $csv);
        $this->assertStringContainsString('2026-11-02T10:00:00', $csv);
        $this->assertStringNotContainsString('LATE-ORDER', $csv);
        $this->assertMatchesRegularExpression('/"1","3","2026-11-02T10:00:00/', $csv);

        $job->handle(app(ProductOrderExportQueryService::class));
        $this->assertSame(1, $ready->fresh()->attempts);
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'data_export.ready')->count());
    }

    public function test_invalid_or_empty_export_fails_without_history_or_job(): void
    {
        Queue::fake();
        foreach ([
            [['from_date' => '2026-11-01', 'to_date' => '2026-11-02'], 'pdf'],
            [['from_date' => '2026-11-01', 'to_date' => '2026-11-02'], 'xlsx'],
        ] as [$filters, $format]) {
            try {
                app(DataExportService::class)->queueProductOrders(7, $filters, $format);
                $this->fail('The invalid or empty export should have failed.');
            } catch (DomainException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertDatabaseCount('data_exports', 0);
        Queue::assertNothingPushed();
    }

    public function test_export_routes_require_post_finance_permission_and_download_is_actor_owned(): void
    {
        $exportRoute = Route::getRoutes()->getByName('admin.product_order.export');
        $downloadRoute = Route::getRoutes()->getByName('admin.product_order_exports.download');
        $this->assertSame(['POST'], $exportRoute->methods());
        $this->assertContains('permission:Transaction', $exportRoute->gatherMiddleware());
        $this->assertContains('signed', $downloadRoute->gatherMiddleware());

        Storage::fake('local');
        DB::table('admins')->insert(['id' => 7, 'username' => 'finance', 'status' => 1]);
        DB::table('admins')->insert(['id' => 8, 'username' => 'other', 'status' => 1]);
        $export = DataExport::create([
            'requested_by_type' => 'admin', 'requested_by_id' => 7,
            'export_type' => 'product_orders', 'format' => 'csv', 'status' => 'ready',
            'filters' => [], 'disk' => 'local', 'path' => 'exports/product-orders/private.csv',
            'filename' => 'private.csv', 'row_count' => 1, 'expires_at' => now()->addHour(),
        ]);
        Storage::disk('local')->put($export->path, 'private');

        Auth::guard('admin')->setUser(Admin::findOrFail(8));
        try {
            (new ProductOrderExportController())->download($export);
            $this->fail('Another administrator must not download the export.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        Auth::guard('admin')->setUser(Admin::findOrFail(7));
        $response = (new ProductOrderExportController())->download($export);
        $cacheControl = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('private', $cacheControl);
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertNotNull($export->fresh()->downloaded_at);
    }

    private function insertOrder(int $id, string $number, string $createdAt): void
    {
        DB::table('product_orders')->insert([
            'id' => $id, 'user_id' => 12, 'billing_fname' => '=Ada', 'billing_lname' => 'Lovelace',
            'billing_email' => 'ada@example.test', 'billing_phone' => '08000000000',
            'cart_total' => 10000, 'discount' => 500, 'tax' => 250, 'shipping_charge' => 1000,
            'total' => 10750, 'refunded_amount' => 0, 'currency_text' => 'NGN',
            'currency_symbol' => '₦', 'method' => 'paystack', 'payment_status' => 'completed',
            'order_status' => 'processing', 'order_number' => $number,
            'created_at' => $createdAt, 'updated_at' => $createdAt,
        ]);
    }

    private function createSchema(): void
    {
        Schema::create('admins', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('role_id')->nullable(); $table->string('username');
            $table->string('status')->default('1'); $table->timestamps();
        });
        Schema::create('product_orders', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('user_id')->nullable();
            $table->string('billing_fname')->nullable(); $table->string('billing_lname')->nullable();
            $table->string('billing_email')->nullable(); $table->string('billing_phone')->nullable();
            $table->decimal('cart_total', 14, 2)->default(0); $table->decimal('discount', 14, 2)->default(0);
            $table->decimal('tax', 14, 2)->default(0); $table->decimal('shipping_charge', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0); $table->decimal('refunded_amount', 14, 2)->default(0);
            $table->string('currency_text')->nullable(); $table->string('currency_symbol')->nullable();
            $table->string('method')->nullable(); $table->string('payment_status')->nullable();
            $table->string('order_status')->nullable(); $table->string('order_number')->nullable();
            $table->timestamp('archived_at')->nullable(); $table->timestamps();
        });
        Schema::create('order_items', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('product_order_id'); $table->unsignedInteger('quantity')->default(1);
            $table->timestamps();
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
