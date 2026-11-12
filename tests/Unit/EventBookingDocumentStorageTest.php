<?php

namespace Tests\Unit;

use App\Models\Event\Booking;
use App\Services\EventBookingDocumentStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class EventBookingDocumentStorageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['filesystems.event_booking_documents_disk' => 'local']);
        Storage::fake('local');
    }

    public function test_new_evidence_and_invoices_are_written_to_private_booking_scoped_paths(): void
    {
        $booking = new Booking(['booking_id' => 'TICA-EVENT-42']);
        $booking->id = 42;
        $storage = app(EventBookingDocumentStorage::class);

        $evidence = $storage->storeAttachment(
            $booking,
            UploadedFile::fake()->image('evidence.png', 800, 600)
        );
        $invoice = $storage->storeInvoice($booking, '%PDF-private-invoice');

        $this->assertStringStartsWith('private/event-bookings/42/attachments/', $evidence);
        $this->assertSame('private/event-bookings/42/invoices/TICA-EVENT-42.pdf', $invoice);
        Storage::disk('local')->assertExists($evidence);
        Storage::disk('local')->assertExists($invoice);
        $this->assertSame('%PDF-private-invoice', Storage::disk('local')->get($invoice));
    }

    public function test_current_ticket_document_is_stored_separately_from_financial_invoice(): void
    {
        $booking = new Booking(['booking_id' => 'TICA-EVENT-42']);
        $booking->id = 42;
        $storage = app(EventBookingDocumentStorage::class);

        $invoice = $storage->storeInvoice($booking, '%PDF-financial-record');
        $tickets = $storage->storeCurrentTickets($booking, '%PDF-current-tickets');

        $this->assertSame('private/event-bookings/42/invoices/TICA-EVENT-42.pdf', $invoice);
        $this->assertSame('private/event-bookings/42/tickets/TICA-EVENT-42-tickets.pdf', $tickets);
        $this->assertSame('%PDF-financial-record', Storage::disk('local')->get($invoice));
        $this->assertSame('%PDF-current-tickets', Storage::disk('local')->get($tickets));
    }

    public function test_private_path_must_belong_to_the_same_booking(): void
    {
        $booking = new Booking(['booking_id' => 'TICA-EVENT-42']);
        $booking->id = 42;
        $storage = app(EventBookingDocumentStorage::class);

        $this->assertTrue($storage->isPrivatePath('private/event-bookings/99/invoices/invoice.pdf'));
        $this->assertTrue($storage->isPrivatePathForBooking($booking, 'private/event-bookings/42/invoices/invoice.pdf'));
        $this->assertFalse($storage->isPrivatePathForBooking($booking, 'private/event-bookings/99/invoices/invoice.pdf'));
        $this->assertFalse($storage->isPrivatePath('../private/event-bookings/42/invoices/invoice.pdf'));
    }

    public function test_cross_booking_invoice_reference_is_rejected(): void
    {
        $booking = new Booking([
            'booking_id' => 'TICA-EVENT-42',
            'invoice' => 'private/event-bookings/99/invoices/other.pdf',
        ]);
        $booking->id = 42;

        $this->expectException(RuntimeException::class);
        app(EventBookingDocumentStorage::class)->invoiceAttachmentPath($booking);
    }

    public function test_private_cleanup_cannot_delete_another_bookings_document(): void
    {
        $booking = new Booking(['booking_id' => 'TICA-EVENT-42']);
        $booking->id = 42;

        $this->expectException(RuntimeException::class);
        app(EventBookingDocumentStorage::class)->deletePrivatePath(
            $booking,
            'private/event-bookings/99/attachments/evidence.png'
        );
    }

    public function test_disguised_payment_evidence_is_rejected_before_storage(): void
    {
        $booking = new Booking(['booking_id' => 'TICA-EVENT-42']);
        $booking->id = 42;

        $this->expectException(RuntimeException::class);
        app(EventBookingDocumentStorage::class)->storeAttachment(
            $booking,
            UploadedFile::fake()->createWithContent('evidence.png', '<?php echo "unsafe";')
        );
    }
}
