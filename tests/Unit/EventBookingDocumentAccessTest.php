<?php

namespace Tests\Unit;

use App\Http\Controllers\BackEnd\Organizer\EventBookingController as OrganizerBookingController;
use App\Http\Controllers\FrontEnd\Event\CustomerBookingController;
use App\Models\Customer;
use App\Models\Event\Booking;
use App\Models\Organizer;
use App\Services\EventBookingDocumentStorage;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EventBookingDocumentAccessTest extends TestCase
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
        config(['filesystems.event_booking_documents_disk' => 'local']);
        Storage::fake('local');
        $this->createSchema();
    }

    protected function tearDown(): void
    {
        Auth::guard('customer')->logout();
        Auth::guard('organizer')->logout();
        parent::tearDown();
    }

    public function test_customer_can_download_only_their_own_private_invoice(): void
    {
        $customer = Customer::create([
            'email' => 'owner@example.com', 'password' => bcrypt('secret'),
            'status' => 1, 'email_verified_at' => now(),
        ]);
        $other = Customer::create([
            'email' => 'other@example.com', 'password' => bcrypt('secret'),
            'status' => 1, 'email_verified_at' => now(),
        ]);
        $owned = $this->booking(['customer_id' => $customer->id, 'booking_id' => 'OWN-1']);
        $foreign = $this->booking(['customer_id' => $other->id, 'booking_id' => 'OTHER-1']);
        $documents = app(EventBookingDocumentStorage::class);
        $owned->forceFill(['invoice' => $documents->storeInvoice($owned, '%PDF-owned')])->save();
        $foreign->forceFill(['invoice' => $documents->storeInvoice($foreign, '%PDF-other')])->save();
        Auth::guard('customer')->login($customer);

        $response = app(CustomerBookingController::class)->invoice($owned->id, $documents);
        $this->assertSame(200, $response->getStatusCode());
        $cacheControl = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('private', $cacheControl);
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('max-age=0', $cacheControl);
        $this->assertDatabaseHas('audit_logs', [
            'actor_type' => 'customer',
            'actor_id' => $customer->id,
            'action' => 'event_booking.invoice_downloaded',
            'subject_id' => $owned->id,
        ]);

        $this->expectException(ModelNotFoundException::class);
        app(CustomerBookingController::class)->invoice($foreign->id, $documents);
    }

    public function test_organizer_invoice_and_evidence_access_is_owner_scoped(): void
    {
        $organizer = Organizer::create([
            'email' => 'owner-organizer@example.com', 'password' => bcrypt('secret'),
            'status' => 1, 'email_verified_at' => now(),
        ]);
        $other = Organizer::create([
            'email' => 'other-organizer@example.com', 'password' => bcrypt('secret'),
            'status' => 1, 'email_verified_at' => now(),
        ]);
        $owned = $this->booking(['organizer_id' => $organizer->id, 'booking_id' => 'ORG-1']);
        $foreign = $this->booking(['organizer_id' => $other->id, 'booking_id' => 'ORG-2']);
        $documents = app(EventBookingDocumentStorage::class);
        $owned->forceFill([
            'invoice' => $documents->storeInvoice($owned, '%PDF-owned'),
            'attachmentFile' => $documents->storeAttachment(
                $owned,
                UploadedFile::fake()->image('evidence.png', 800, 600)
            ),
        ])->save();
        $foreign->forceFill(['invoice' => $documents->storeInvoice($foreign, '%PDF-other')])->save();
        Auth::guard('organizer')->login($organizer);

        $this->assertSame(200, app(OrganizerBookingController::class)->invoice($owned->id, $documents)->getStatusCode());
        $this->assertSame(200, app(OrganizerBookingController::class)->attachment($owned->id, $documents)->getStatusCode());
        $this->assertDatabaseCount('audit_logs', 2);

        $this->expectException(ModelNotFoundException::class);
        app(OrganizerBookingController::class)->invoice($foreign->id, $documents);
    }

    private function booking(array $attributes): Booking
    {
        return Booking::create(array_merge([
            'booking_id' => 'BOOKING-'.bin2hex(random_bytes(4)),
            'customer_id' => null,
            'organizer_id' => null,
        ], $attributes));
    }

    private function createSchema(): void
    {
        Schema::create('customers', function (Blueprint $table): void {
            $table->id(); $table->string('email')->unique(); $table->string('password');
            $table->boolean('status')->default(true); $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken(); $table->timestamps();
        });
        Schema::create('organizers', function (Blueprint $table): void {
            $table->id(); $table->string('email')->unique(); $table->string('password');
            $table->boolean('status')->default(true); $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken(); $table->timestamps();
        });
        Schema::create('bookings', function (Blueprint $table): void {
            $table->id(); $table->string('booking_id');
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('organizer_id')->nullable();
            $table->string('invoice')->nullable(); $table->string('attachmentFile')->nullable();
            $table->timestamps();
        });
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id(); $table->string('actor_type', 32)->default('system');
            $table->unsignedBigInteger('actor_id')->nullable(); $table->string('action', 120);
            $table->string('subject_type', 160)->nullable(); $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('before')->nullable(); $table->json('after')->nullable(); $table->json('metadata')->nullable();
            $table->string('request_id', 100)->nullable(); $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable(); $table->timestamp('created_at')->nullable();
        });
    }
}
