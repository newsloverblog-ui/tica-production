<?php

namespace Tests\Unit;

use App\Services\LegacyPublicCheckoutDocuments;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PurgeLegacyPublicCheckoutDocumentsTest extends TestCase
{
    private string $root;

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
        $this->createSchema();

        $this->root = storage_path('framework/testing/purge-legacy-public-documents-'.bin2hex(random_bytes(6)));
        foreach (['event/invoices', 'event/attachments', 'product/invoices', 'product/attachments'] as $directory) {
            File::ensureDirectoryExists($this->root.'/'.$directory);
        }

        $this->app->instance(LegacyPublicCheckoutDocuments::class, new LegacyPublicCheckoutDocuments([
            'event' => [$this->root.'/event/invoices', $this->root.'/event/attachments'],
            'product' => [$this->root.'/product/invoices', $this->root.'/product/attachments'],
        ]));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);

        parent::tearDown();
    }

    public function test_it_refuses_to_purge_while_database_rows_reference_public_files(): void
    {
        $file = $this->root.'/event/invoices/legacy.pdf';
        File::put($file, 'legacy');
        DB::table('bookings')->insert(['invoice' => 'legacy.pdf']);

        $this->artisan('documents:purge-legacy-public', ['--apply' => true])
            ->assertFailed();

        $this->assertFileExists($file);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_it_dry_runs_then_purges_orphans_and_records_an_audit_event(): void
    {
        $event = $this->root.'/event/attachments/evidence.jpg';
        $product = $this->root.'/product/invoices/invoice.pdf';
        File::put($event, 'event');
        File::put($product, 'product');

        $this->artisan('documents:purge-legacy-public')->assertSuccessful();
        $this->assertFileExists($event);
        $this->assertFileExists($product);

        $this->artisan('documents:purge-legacy-public', ['--apply' => true])
            ->assertSuccessful();

        $this->assertFileDoesNotExist($event);
        $this->assertFileDoesNotExist($product);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'checkout_documents.legacy_public_purge_started',
            'actor_type' => 'system',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'checkout_documents.legacy_public_purge_completed',
            'actor_type' => 'system',
        ]);
    }

    private function createSchema(): void
    {
        Schema::create('bookings', function (Blueprint $table): void {
            $table->id();
            $table->string('invoice')->nullable();
            $table->string('attachmentFile')->nullable();
        });
        Schema::create('product_orders', function (Blueprint $table): void {
            $table->id();
            $table->string('receipt')->nullable();
            $table->string('invoice_number')->nullable();
        });
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('actor_type', 32)->default('system');
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('action', 120);
            $table->string('subject_type', 160)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->json('metadata')->nullable();
            $table->string('request_id', 100)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }
}
