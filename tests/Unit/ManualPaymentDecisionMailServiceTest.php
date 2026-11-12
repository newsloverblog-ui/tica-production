<?php

namespace Tests\Unit;

use App\Models\Event\Booking;
use App\Models\ShopManagement\ProductOrder;
use App\Services\ManualPaymentDecisionMailService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class ManualPaymentDecisionMailServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        config()->set('mail.default', 'array');
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        $this->createSchema();
    }

    public function test_decision_mail_uses_the_configured_transport_and_authoritative_records(): void
    {
        $messages = [];
        Event::listen(MessageSending::class, function (MessageSending $event) use (&$messages): void {
            $messages[] = $event->message;
        });
        DB::table('basic_settings')->insert([
            'website_title' => 'Tica', 'from_mail' => 'support@tica.test', 'from_name' => 'Tica',
        ]);
        DB::table('mail_templates')->insert([
            [
                'mail_type' => 'event_booking_rejected',
                'mail_subject' => 'Booking rejected',
                'mail_body' => '<p>{customer_name}|{order_id}|{title}|{website_title}</p>',
            ],
            [
                'mail_type' => 'product_order',
                'mail_subject' => 'Order update',
                'mail_body' => '<p>{customer_name}|{order_id}|{website_title}</p>',
            ],
        ]);
        $languageId = DB::table('languages')->insertGetId([
            'name' => 'English', 'code' => 'en', 'direction' => 'ltr', 'is_default' => 1,
        ]);
        $eventId = DB::table('events')->insertGetId(['status' => 1]);
        DB::table('event_contents')->insert([
            'event_id' => $eventId,
            'language_id' => $languageId,
            'title' => 'Night & Light',
            'slug' => 'night-light',
        ]);
        $booking = Booking::create([
            'booking_id' => 'BOOK-REJECTED', 'event_id' => $eventId,
            'fname' => 'Ada <script>', 'lname' => 'Okafor', 'email' => 'ada@example.com',
            'paymentStatus' => 'rejected',
        ]);
        $order = ProductOrder::create([
            'order_number' => 'ORDER-REJECTED', 'billing_fname' => 'Chidi',
            'billing_lname' => 'Eze', 'billing_email' => 'chidi@example.com',
            'payment_status' => 'rejected', 'order_status' => 'pending', 'total' => 5000,
        ]);

        $service = app(ManualPaymentDecisionMailService::class);
        $service->sendBookingRejection($booking);
        $service->sendProductOrderRejection($order);

        $this->assertCount(2, $messages);
        $this->assertInstanceOf(Email::class, $messages[0]);
        $this->assertSame('ada@example.com', $messages[0]->getTo()[0]->getAddress());
        $this->assertSame('Booking rejected', $messages[0]->getSubject());
        $this->assertStringContainsString('Ada &lt;script&gt; Okafor|BOOK-REJECTED', $messages[0]->getHtmlBody());
        $this->assertStringNotContainsString('<script>', $messages[0]->getHtmlBody());
        $this->assertStringContainsString('Night &amp; Light', $messages[0]->getHtmlBody());
        $this->assertSame('chidi@example.com', $messages[1]->getTo()[0]->getAddress());
        $this->assertSame('Order update', $messages[1]->getSubject());
        $this->assertStringContainsString('Chidi Eze|ORDER-REJECTED|Tica', $messages[1]->getHtmlBody());
    }

    private function createSchema(): void
    {
        Schema::create('basic_settings', function (Blueprint $table): void {
            $table->id(); $table->string('website_title')->nullable();
            $table->string('from_mail')->nullable(); $table->string('from_name')->nullable();
        });
        Schema::create('mail_templates', function (Blueprint $table): void {
            $table->id(); $table->string('mail_type')->unique();
            $table->string('mail_subject'); $table->longText('mail_body');
        });
        Schema::create('languages', function (Blueprint $table): void {
            $table->id(); $table->string('name'); $table->string('code');
            $table->string('direction'); $table->boolean('is_default')->default(false);
            $table->timestamps();
        });
        Schema::create('events', function (Blueprint $table): void {
            $table->id(); $table->integer('status')->default(1); $table->timestamps();
        });
        Schema::create('event_contents', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('event_id'); $table->unsignedBigInteger('language_id');
            $table->string('title'); $table->string('slug'); $table->timestamps();
        });
        Schema::create('bookings', function (Blueprint $table): void {
            $table->id(); $table->string('booking_id'); $table->unsignedBigInteger('event_id');
            $table->string('fname')->nullable(); $table->string('lname')->nullable();
            $table->string('email')->nullable(); $table->string('paymentStatus')->nullable();
            $table->timestamps();
        });
        Schema::create('product_orders', function (Blueprint $table): void {
            $table->id(); $table->string('order_number'); $table->string('billing_fname')->nullable();
            $table->string('billing_lname')->nullable(); $table->string('billing_email')->nullable();
            $table->string('payment_status'); $table->string('order_status');
            $table->decimal('total', 18, 2); $table->timestamp('archived_at')->nullable();
            $table->timestamps();
        });
    }
}
