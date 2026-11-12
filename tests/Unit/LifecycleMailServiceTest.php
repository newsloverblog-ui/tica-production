<?php

namespace Tests\Unit;

use App\Jobs\SendApplicationMailDelivery;
use App\Models\ApplicationMailDelivery;
use App\Models\Event;
use App\Services\LifecycleMailService;
use App\Support\LifecycleMailCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class LifecycleMailServiceTest extends TestCase
{
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
        (require database_path('migrations/2026_11_03_150000_create_application_mail_deliveries_table.php'))->up();
        (require database_path('migrations/2026_11_05_160000_add_application_mail_dispatch_metadata.php'))->up();
        Queue::fake();
    }

    public function test_catalog_contains_every_requested_buyer_and_organizer_flow(): void
    {
        $this->assertCount(19, LifecycleMailCatalog::kinds());
        $this->assertSame(10, collect(LifecycleMailCatalog::kinds())->filter(fn ($kind) => str_starts_with($kind, 'buyer.'))->count());
        $this->assertSame(9, collect(LifecycleMailCatalog::kinds())->filter(fn ($kind) => str_starts_with($kind, 'organizer.'))->count());

        foreach (LifecycleMailCatalog::kinds() as $kind) {
            $message = LifecycleMailCatalog::compose($kind, [
                'first_name' => 'Ada', 'event_title' => 'Tica Live',
                'event_url' => 'https://tica.test/events/tica-live',
            ]);
            $this->assertSame($kind, $message['kind']);
            $this->assertNotSame('', trim($message['subject']));
            $this->assertNotSame('', trim($message['title']));
            $this->assertNotSame('', trim($message['preheader']));
        }
    }

    public function test_branded_renderer_contains_order_fee_tickets_actions_and_safe_copy(): void
    {
        $message = LifecycleMailCatalog::compose(LifecycleMailCatalog::BUYER_ORDER_CONFIRMATION, [
            'first_name' => 'Ada', 'event_title' => 'Tica Live', 'order_reference' => 'TIC-1042',
            'ticket_count' => 2, 'subtotal' => '₦20,000', 'booking_fee' => '₦1,200',
            'total' => '₦21,200', 'tickets_url' => 'https://tica.test/tickets',
            'calendar_url' => 'https://tica.test/calendar',
            'tickets' => [
                ['label' => 'General admission', 'holder' => 'Ada Okafor', 'reference' => 'TIC-1042-1', 'qr' => 'data:image/svg+xml;base64,PHN2Zy8+'],
                ['label' => 'General admission', 'holder' => 'Nneka Okafor', 'reference' => 'TIC-1042-2', 'qr' => 'data:image/svg+xml;base64,PHN2Zy8+'],
            ],
        ]);
        $html = view('emails.lifecycle', compact('message'))->render();

        $this->assertStringContainsString('tica', $html);
        $this->assertStringContainsString('Booking fee (6%)', $html);
        $this->assertSame(2, substr_count($html, 'Ticket QR code'));
        $this->assertStringContainsString('Open tickets', $html);
        $this->assertStringContainsString('Add to calendar', $html);
        $this->assertStringContainsString('before leaving home', $html);
    }

    public function test_queue_once_is_encrypted_durable_and_idempotent_for_a_source(): void
    {
        $source = new Event();
        $source->forceFill(['id' => 42]);
        $source->exists = true;
        $service = app(LifecycleMailService::class);
        $data = ['event_title' => 'Tica Live', 'event_url' => 'https://tica.test/events/42'];

        $first = $service->queueOnce(
            LifecycleMailCatalog::ORGANIZER_EVENT_PUBLISHED,
            'Organizer@Example.com',
            $data,
            $source
        );
        $second = $service->queueOnce(
            LifecycleMailCatalog::ORGANIZER_EVENT_PUBLISHED,
            'organizer@example.com',
            $data,
            $source
        );

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, ApplicationMailDelivery::query()->count());
        $raw = DB::table('application_mail_deliveries')->first();
        $this->assertStringNotContainsString('organizer@example.com', (string) $raw->recipient_email);
        $this->assertStringNotContainsString('Tica Live', (string) $raw->body);
        Queue::assertPushed(SendApplicationMailDelivery::class, 1);
    }
}
