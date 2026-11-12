<?php

namespace Tests\Unit;

use App\Http\Controllers\FrontEnd\CustomerDataExportController;
use App\Jobs\GenerateCustomerDataExport;
use App\Models\Customer;
use App\Models\DataExport;
use App\Services\DataExportService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;
use ZipArchive;

class CustomerDataExportTest extends TestCase
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
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        $this->createSchema();
    }

    public function test_customer_export_is_private_encrypted_queued_and_idempotent(): void
    {
        Queue::fake();
        $customer = $this->customer(1, 'owner@example.test');
        $this->seedTransactionData($customer->id);

        $export = app(DataExportService::class)->queueCustomerData($customer->id);
        $same = app(DataExportService::class)->queueCustomerData($customer->id);

        $this->assertSame($export->id, $same->id);
        $this->assertSame('customer', $export->requested_by_type);
        $this->assertSame('customer_personal_data', $export->export_type);
        $this->assertSame('zip', $export->format);
        $this->assertSame(4, $export->row_count);
        $this->assertSame(1, $export->filters['max_booking_id']);
        $this->assertSame(1, $export->filters['max_booking_ticket_id']);
        $this->assertSame(1, $export->filters['max_product_order_id']);
        $raw = (string) DB::table('data_exports')->where('id', $export->id)->value('filters');
        $this->assertStringNotContainsString('max_booking_id', $raw);
        Queue::assertPushed(GenerateCustomerDataExport::class, 1);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'customer_data_export.queued', 'subject_id' => $export->id,
        ]);
    }

    public function test_generator_includes_owned_transactions_and_held_tickets_without_secrets(): void
    {
        Storage::fake('local');
        Queue::fake();
        $customer = $this->customer(1, 'owner@example.test');
        $this->seedTransactionData($customer->id);
        $export = app(DataExportService::class)->queueCustomerData($customer->id);

        (new GenerateCustomerDataExport($export->id))->handle();

        $ready = $export->fresh();
        $this->assertSame('ready', $ready->status);
        Storage::disk('local')->assertExists($ready->path);
        $entries = $this->archiveEntries(Storage::disk('local')->get($ready->path));
        $this->assertArrayHasKey('README.txt', $entries);
        $this->assertArrayHasKey('account.csv', $entries);
        $this->assertArrayHasKey('event_bookings.csv', $entries);
        $this->assertArrayHasKey('tickets.csv', $entries);
        $this->assertArrayHasKey('product_orders.csv', $entries);
        $this->assertStringContainsString('tica.customer-data.v1', $entries['README.txt']);
        $this->assertStringContainsString('owner@example.test', $entries['account.csv']);
        $this->assertStringContainsString('BOOK-1', $entries['event_bookings.csv']);
        $this->assertStringContainsString('ticket-public-1', $entries['tickets.csv']);
        $this->assertStringContainsString('ORDER-1', $entries['product_orders.csv']);
        $this->assertStringContainsString("'=Customer message", $entries['support_messages.csv']);
        $archiveText = implode("\n", $entries);
        $this->assertStringNotContainsString('password-hash', $archiveText);
        $this->assertStringNotContainsString('transfer-secret', $archiveText);
        $this->assertStringNotContainsString('provider-charge-secret', $archiveText);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'customer_data_export.ready', 'subject_id' => $export->id,
        ]);

        (new GenerateCustomerDataExport($export->id))->handle();
        $this->assertSame(1, $ready->fresh()->attempts);
    }

    public function test_download_is_owner_only_and_non_cacheable(): void
    {
        Storage::fake('local');
        $owner = $this->customer(1, 'owner@example.test');
        $other = $this->customer(2, 'other@example.test');
        $export = DataExport::create([
            'requested_by_type' => 'customer', 'requested_by_id' => $owner->id,
            'export_type' => 'customer_personal_data', 'format' => 'zip', 'status' => 'ready',
            'filters' => [], 'disk' => 'local', 'path' => 'exports/customers/private.zip',
            'filename' => 'tica-my-data.zip', 'row_count' => 1, 'expires_at' => now()->addDay(),
        ]);
        Storage::disk('local')->put($export->path, '{"private":true}');
        $controller = app(CustomerDataExportController::class);

        Auth::guard('customer')->setUser($other);
        try {
            $controller->download(Request::create('/customer/my-data/file/download'), $export);
            $this->fail('Another customer must not download this export.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        Auth::guard('customer')->setUser($owner);
        $response = $controller->download(Request::create('/customer/my-data/file/download'), $export);
        $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertNotNull($export->fresh()->downloaded_at);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'customer_data_export.downloaded', 'subject_id' => $export->id,
        ]);
    }

    public function test_routes_require_active_verified_customer_and_signed_throttled_download(): void
    {
        $routes = app('router')->getRoutes();
        $index = $routes->getByName('customer.data-export.index');
        $store = $routes->getByName('customer.data-export.store');
        $download = $routes->getByName('customer.data-export.download');

        foreach ([$index, $store, $download] as $route) {
            $this->assertNotNull($route);
            $this->assertContains('auth:customer', $route->gatherMiddleware());
            $this->assertContains('Deactive:customer', $route->gatherMiddleware());
            $this->assertContains('EmailStatus:customer', $route->gatherMiddleware());
        }
        $this->assertContains('throttle:2,60', $store->gatherMiddleware());
        $this->assertContains('signed', $download->gatherMiddleware());
        $this->assertContains('throttle:6,1', $download->gatherMiddleware());
    }

    private function customer(int $id, string $email): Customer
    {
        DB::table('customers')->insert([
            'id' => $id, 'fname' => 'Test', 'lname' => 'Customer', 'username' => 'customer'.$id,
            'email' => $email, 'password' => 'password-hash', 'status' => 1,
            'email_verified_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        return Customer::findOrFail($id);
    }

    private function seedTransactionData(int $customerId): void
    {
        DB::table('event_contents')->insert([
            'id' => 1, 'event_id' => 10, 'language_id' => 1, 'title' => 'A Great Event',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('bookings')->insert([
            'id' => 1, 'customer_id' => $customerId, 'booking_id' => 'BOOK-1', 'event_id' => 10,
            'fname' => 'Test', 'lname' => 'Customer', 'email' => 'owner@example.test',
            'quantity' => 1, 'price' => 5000, 'paymentStatus' => 'completed',
            'event_date' => now()->addWeek(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('booking_tickets')->insert([
            'id' => 1, 'public_id' => 'ticket-public-1', 'booking_id' => 1, 'event_id' => 10,
            'sequence' => 1, 'holder_customer_id' => $customerId, 'holder_name' => 'Test Customer',
            'holder_email' => 'owner@example.test', 'status' => 'issued',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('booking_ticket_transfers')->insert([
            'id' => 1, 'booking_ticket_id' => 1, 'recipient_name' => 'Friend',
            'recipient_email' => 'friend@example.test', 'token_hash' => 'transfer-secret',
            'status' => 'cancelled', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('product_orders')->insert([
            'id' => 1, 'user_id' => $customerId, 'order_number' => 'ORDER-1', 'total' => 9000,
            'payment_status' => 'completed', 'order_status' => 'processing',
            'charge_id' => 'provider-charge-secret', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('order_items')->insert([
            'id' => 1, 'product_order_id' => 1, 'product_id' => 20, 'user_id' => $customerId,
            'title' => 'Tica shirt', 'qty' => 1, 'price' => 9000,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('wishlists')->insert([
            'id' => 1, 'customer_id' => $customerId, 'event_id' => 10,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('support_tickets')->insert([
            'id' => 1, 'user_id' => $customerId, 'user_type' => 'customer',
            'email' => 'owner@example.test', 'subject' => 'Help', 'description' => 'Question',
            'status' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('conversations')->insert([
            'id' => 1, 'user_id' => $customerId, 'type' => 'customer', 'support_ticket_id' => 1,
            'reply' => '=Customer message', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('product_reviews')->insert([
            'id' => 1, 'user_id' => $customerId, 'product_id' => 20, 'review' => 5,
            'comment' => 'Great', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function createSchema(): void
    {
        Schema::create('customers', function (Blueprint $table): void {
            $table->id(); $table->string('fname')->nullable(); $table->string('lname')->nullable();
            $table->string('username')->nullable(); $table->string('email')->nullable(); $table->string('phone')->nullable();
            $table->string('address')->nullable(); $table->string('country')->nullable(); $table->string('state')->nullable();
            $table->string('city')->nullable(); $table->string('zip_code')->nullable(); $table->string('gender')->nullable();
            $table->string('password')->nullable(); $table->boolean('status')->default(true);
            $table->timestamp('email_verified_at')->nullable(); $table->timestamps();
        });
        Schema::create('data_exports', function (Blueprint $table): void {
            $table->id(); $table->uuid('public_id')->unique(); $table->string('requested_by_type');
            $table->unsignedBigInteger('requested_by_id'); $table->unsignedBigInteger('organizer_id')->nullable();
            $table->string('export_type'); $table->string('format'); $table->string('status');
            $table->longText('filters')->nullable(); $table->string('disk'); $table->string('path')->nullable();
            $table->string('filename'); $table->unsignedBigInteger('row_count')->default(0);
            $table->unsignedSmallInteger('attempts')->default(0); $table->text('last_error')->nullable();
            $table->timestamp('started_at')->nullable(); $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at'); $table->timestamp('downloaded_at')->nullable(); $table->timestamps();
        });
        Schema::create('event_contents', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('event_id'); $table->unsignedBigInteger('language_id');
            $table->string('title'); $table->timestamps();
        });
        Schema::create('bookings', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('customer_id'); $table->string('booking_id');
            $table->unsignedBigInteger('event_id'); $table->unsignedBigInteger('organizer_id')->nullable();
            $table->unsignedBigInteger('ticket_id')->nullable(); $table->string('fname')->nullable();
            $table->string('lname')->nullable(); $table->string('email')->nullable(); $table->string('phone')->nullable();
            $table->string('country')->nullable(); $table->string('state')->nullable(); $table->string('city')->nullable();
            $table->string('zip_code')->nullable(); $table->string('address')->nullable(); $table->string('variation')->nullable();
            $table->decimal('price', 18, 2)->nullable(); $table->decimal('tax', 18, 2)->nullable();
            $table->unsignedInteger('quantity')->default(1); $table->decimal('discount', 18, 2)->nullable();
            $table->string('currencyText')->nullable(); $table->string('paymentMethod')->nullable();
            $table->string('paymentStatus')->nullable(); $table->decimal('refunded_amount', 18, 2)->nullable();
            $table->string('refund_status')->nullable(); $table->timestamp('event_date')->nullable(); $table->timestamps();
        });
        Schema::create('booking_tickets', function (Blueprint $table): void {
            $table->id(); $table->string('public_id')->unique(); $table->unsignedBigInteger('booking_id');
            $table->unsignedBigInteger('event_id'); $table->unsignedBigInteger('organizer_id')->nullable();
            $table->unsignedBigInteger('ticket_id')->nullable(); $table->unsignedInteger('sequence');
            $table->string('variation_name')->nullable(); $table->string('holder_name')->nullable();
            $table->string('holder_email')->nullable(); $table->unsignedBigInteger('holder_customer_id')->nullable();
            $table->string('status'); $table->timestamp('checked_in_at')->nullable();
            $table->timestamp('revoked_at')->nullable(); $table->string('revoked_reason')->nullable(); $table->timestamps();
        });
        Schema::create('booking_ticket_events', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('booking_ticket_id'); $table->timestamps();
        });
        Schema::create('booking_ticket_transfers', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('booking_ticket_id'); $table->unsignedBigInteger('from_customer_id')->nullable();
            $table->string('recipient_name'); $table->string('recipient_email'); $table->string('token_hash');
            $table->string('status'); $table->timestamp('expires_at')->nullable(); $table->timestamp('accepted_at')->nullable();
            $table->timestamp('cancelled_at')->nullable(); $table->timestamps();
        });
        Schema::create('product_orders', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('user_id');
            foreach (['billing_fname','billing_lname','billing_email','billing_phone','billing_country','billing_state','billing_city','billing_zip_code','billing_address','shipping_fname','shipping_lname','shipping_email','shipping_phone','shipping_country','shipping_state','shipping_city','shipping_zip_code','shipping_address'] as $column) $table->string($column)->nullable();
            $table->decimal('cart_total', 18, 2)->nullable(); $table->decimal('discount', 18, 2)->nullable();
            $table->decimal('tax', 18, 2)->nullable(); $table->decimal('total', 18, 2)->nullable();
            $table->string('method')->nullable(); $table->string('currency_text')->nullable();
            $table->string('order_number'); $table->decimal('shipping_charge', 18, 2)->nullable();
            $table->string('payment_status')->nullable(); $table->decimal('refunded_amount', 18, 2)->nullable();
            $table->string('refund_status')->nullable(); $table->string('order_status')->nullable();
            $table->string('charge_id')->nullable(); $table->string('invoice_number')->nullable(); $table->timestamps();
        });
        Schema::create('order_items', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('product_order_id'); $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('user_id'); $table->string('title'); $table->string('sku')->nullable();
            $table->unsignedInteger('qty'); $table->decimal('price', 18, 2); $table->timestamps();
        });
        Schema::create('wishlists', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('customer_id'); $table->unsignedBigInteger('event_id'); $table->timestamps();
        });
        Schema::create('support_tickets', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('user_id'); $table->string('user_type');
            $table->string('email'); $table->string('subject'); $table->text('description');
            $table->unsignedTinyInteger('status'); $table->timestamps();
        });
        Schema::create('conversations', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('user_id'); $table->string('type');
            $table->unsignedBigInteger('support_ticket_id'); $table->text('reply'); $table->timestamps();
        });
        Schema::create('product_reviews', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('user_id'); $table->unsignedBigInteger('product_id');
            $table->unsignedTinyInteger('review'); $table->text('comment')->nullable(); $table->timestamps();
        });
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id(); $table->string('actor_type'); $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('action'); $table->string('subject_type')->nullable(); $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('before')->nullable(); $table->json('after')->nullable(); $table->json('metadata')->nullable();
            $table->string('request_id')->nullable(); $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable(); $table->timestamp('created_at')->nullable();
        });
    }

    private function archiveEntries(string $archive): array
    {
        $path = tempnam(sys_get_temp_dir(), 'tica-test-export-');
        if ($path === false) $this->fail('Unable to create archive fixture.');
        file_put_contents($path, $archive);
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($path) === true);
        $entries = [];
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = $zip->getNameIndex($index);
            if ($name !== false) $entries[$name] = (string) $zip->getFromIndex($index);
        }
        $zip->close();
        @unlink($path);

        return $entries;
    }
}
