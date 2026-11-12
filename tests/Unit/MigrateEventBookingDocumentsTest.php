<?php

namespace Tests\Unit;

use App\Models\Event\Booking;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class MigrateEventBookingDocumentsTest extends TestCase
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
        config(['filesystems.event_booking_documents_disk' => 'local']);
        Storage::fake('local');
        $this->createSchema();
    }

    protected function tearDown(): void
    {
        foreach ($this->legacyFiles as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
        parent::tearDown();
    }

    public function test_it_corrects_the_historical_duplicate_evidence_reference(): void
    {
        $filename = 'legacy-evidence-'.bin2hex(random_bytes(6)).'.png';
        $source = $this->legacyFile('attachments', $filename, $this->imageContents('png'));
        $booking = Booking::create([
            'booking_id' => 'EVENT-101',
            'invoice' => $filename,
            'attachmentFile' => $filename,
        ]);

        $this->artisan('event-bookings:migrate-private-documents', ['--apply' => true, '--delete-public' => true])
            ->assertSuccessful();

        $booking->refresh();
        $this->assertNull($booking->invoice);
        $this->assertStringStartsWith('private/event-bookings/'.$booking->id.'/attachments/', $booking->attachmentFile);
        Storage::disk('local')->assertExists($booking->attachmentFile);
        $this->assertFileDoesNotExist($source);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'event_booking.documents_privatized',
            'subject_id' => $booking->id,
        ]);
    }

    public function test_it_imports_invoice_and_evidence_without_deleting_sources_by_default(): void
    {
        $suffix = bin2hex(random_bytes(6));
        $invoiceName = 'legacy-invoice-'.$suffix.'.pdf';
        $evidenceName = 'legacy-evidence-'.$suffix.'.jpg';
        $invoiceSource = $this->legacyFile('invoices', $invoiceName, '%PDF-legacy');
        $evidenceSource = $this->legacyFile('attachments', $evidenceName, $this->imageContents('jpg'));
        $booking = Booking::create([
            'booking_id' => 'EVENT-202',
            'invoice' => $invoiceName,
            'attachmentFile' => $evidenceName,
        ]);

        $this->artisan('event-bookings:migrate-private-documents')->assertSuccessful();
        $this->assertSame([], Storage::disk('local')->allFiles());

        $this->artisan('event-bookings:migrate-private-documents', ['--apply' => true])
            ->assertSuccessful();

        $booking->refresh();
        $this->assertSame('private/event-bookings/'.$booking->id.'/invoices/EVENT-202.pdf', $booking->invoice);
        $this->assertStringStartsWith('private/event-bookings/'.$booking->id.'/attachments/', $booking->attachmentFile);
        Storage::disk('local')->assertExists($booking->invoice);
        Storage::disk('local')->assertExists($booking->attachmentFile);
        $this->assertFileExists($invoiceSource);
        $this->assertFileExists($evidenceSource);
    }

    public function test_it_rejects_disguised_legacy_evidence_without_changing_the_booking(): void
    {
        $filename = 'legacy-script-'.bin2hex(random_bytes(6)).'.jpg';
        $source = $this->legacyFile('attachments', $filename, '<?php echo "unsafe";');
        $booking = Booking::create([
            'booking_id' => 'EVENT-UNSAFE',
            'attachmentFile' => $filename,
        ]);

        $this->artisan('event-bookings:migrate-private-documents')->assertFailed();
        $this->assertSame($filename, $booking->fresh()->attachmentFile);
        $this->assertSame([], Storage::disk('local')->allFiles());

        $this->artisan('event-bookings:migrate-private-documents', ['--apply' => true])
            ->assertFailed();

        $this->assertSame($filename, $booking->fresh()->attachmentFile);
        $this->assertFileExists($source);
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertDatabaseCount('audit_logs', 0);
    }

    private function legacyFile(string $directory, string $filename, string $contents): string
    {
        $path = public_path('assets/admin/file/'.$directory.'/'.$filename);
        File::ensureDirectoryExists(dirname($path));
        File::put($path, $contents);
        $this->legacyFiles[] = $path;

        return $path;
    }

    private function imageContents(string $extension): string
    {
        $file = UploadedFile::fake()->image('evidence.'.$extension, 20, 20);

        return (string) file_get_contents($file->getRealPath());
    }

    private function createSchema(): void
    {
        Schema::create('bookings', function (Blueprint $table): void {
            $table->id();
            $table->string('booking_id');
            $table->string('invoice')->nullable();
            $table->string('attachmentFile')->nullable();
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
