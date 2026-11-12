<?php

namespace Tests\Unit;

use App\Models\ShopManagement\ProductOrder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MigrateProductOrderDocumentsTest extends TestCase
{
    private array $legacyFiles = [];

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
        config(['filesystems.product_order_documents_disk' => 'local']);
        Storage::fake('local');
        $this->createSchema();
    }

    protected function tearDown(): void
    {
        foreach ($this->legacyFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        parent::tearDown();
    }

    public function test_it_imports_genuine_invoice_and_receipt_into_order_scoped_private_storage(): void
    {
        $suffix = bin2hex(random_bytes(6));
        $invoiceName = 'legacy-invoice-'.$suffix.'.pdf';
        $receiptName = 'legacy-receipt-'.$suffix.'.png';
        $invoiceSource = $this->legacyFile('invoices', $invoiceName, "%PDF-1.4\n%%EOF");
        $receiptSource = $this->legacyFile('attachments', $receiptName, $this->imageContents());
        $order = ProductOrder::create([
            'order_number' => 'ORDER-202',
            'invoice_number' => $invoiceName,
            'receipt' => $receiptName,
        ]);

        $this->artisan('product-orders:migrate-private-documents')->assertSuccessful();
        $this->assertSame([], Storage::disk('local')->allFiles());

        $this->artisan('product-orders:migrate-private-documents', ['--apply' => true])
            ->assertSuccessful();

        $order->refresh();
        $this->assertSame('private/product-orders/'.$order->id.'/invoices/ORDER-202.pdf', $order->invoice_number);
        $this->assertStringStartsWith('private/product-orders/'.$order->id.'/receipts/', $order->receipt);
        Storage::disk('local')->assertExists($order->invoice_number);
        Storage::disk('local')->assertExists($order->receipt);
        $this->assertFileExists($invoiceSource);
        $this->assertFileExists($receiptSource);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'product_order.documents_privatized',
            'subject_id' => $order->id,
        ]);
    }

    public function test_invalid_receipt_rolls_back_database_and_cleans_imported_invoice(): void
    {
        $suffix = bin2hex(random_bytes(6));
        $invoiceName = 'legacy-invoice-'.$suffix.'.pdf';
        $receiptName = 'legacy-receipt-'.$suffix.'.jpg';
        $invoiceSource = $this->legacyFile('invoices', $invoiceName, "%PDF-1.4\n%%EOF");
        $receiptSource = $this->legacyFile('attachments', $receiptName, '<?php echo "unsafe";');
        $order = ProductOrder::create([
            'order_number' => 'ORDER-UNSAFE',
            'invoice_number' => $invoiceName,
            'receipt' => $receiptName,
        ]);

        $this->artisan('product-orders:migrate-private-documents')->assertFailed();
        $this->assertSame($invoiceName, $order->fresh()->invoice_number);
        $this->assertSame([], Storage::disk('local')->allFiles());

        $this->artisan('product-orders:migrate-private-documents', ['--apply' => true])
            ->assertFailed();

        $order->refresh();
        $this->assertSame($invoiceName, $order->invoice_number);
        $this->assertSame($receiptName, $order->receipt);
        $this->assertFileExists($invoiceSource);
        $this->assertFileExists($receiptSource);
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertDatabaseCount('audit_logs', 0);
    }

    private function legacyFile(string $directory, string $filename, string $contents): string
    {
        $path = public_path('assets/admin/file/order/'.$directory.'/'.$filename);
        File::ensureDirectoryExists(dirname($path));
        File::put($path, $contents);
        $this->legacyFiles[] = $path;

        return $path;
    }

    private function imageContents(): string
    {
        $file = UploadedFile::fake()->image('receipt.png', 20, 20);

        return (string) file_get_contents($file->getRealPath());
    }

    private function createSchema(): void
    {
        Schema::create('product_orders', function (Blueprint $table): void {
            $table->id();
            $table->string('order_number');
            $table->string('invoice_number')->nullable();
            $table->string('receipt')->nullable();
            $table->timestamps();
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
