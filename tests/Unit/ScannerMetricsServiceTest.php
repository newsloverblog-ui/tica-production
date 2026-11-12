<?php

namespace Tests\Unit;

use App\Services\ScannerMetricsService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ScannerMetricsServiceTest extends TestCase
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
        $this->schema();
    }

    public function test_metrics_count_only_valid_admissions_and_group_scans_by_gate(): void
    {
        $eventId = DB::table('events')->insertGetId([
            'organizer_id' => 7, 'status' => 1, 'start_date' => '2026-11-03',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('event_contents')->insert([
            'event_id' => $eventId, 'title' => 'Launch Night',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $mainGate = DB::table('scanner_devices')->insertGetId([
            'organizer_id' => 7, 'name' => 'Main gate', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $vipGate = DB::table('scanner_devices')->insertGetId([
            'organizer_id' => 7, 'name' => 'VIP gate', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $paid = $this->booking(7, $eventId, 'completed');
        $free = $this->booking(7, $eventId, 'free');
        $refunded = $this->booking(7, $eventId, 'completed', 'refunded');
        $unpaid = $this->booking(7, $eventId, 'pending');

        $this->ticket($paid, 7, $eventId, 'checked_in', $mainGate);
        $this->ticket($paid, 7, $eventId, 'checked_in', $mainGate);
        $this->ticket($free, 7, $eventId, 'checked_in', $vipGate);
        $this->ticket($free, 7, $eventId, 'issued');
        $this->ticket($refunded, 7, $eventId, 'issued');
        $this->ticket($unpaid, 7, $eventId, 'issued');
        $this->ticket($paid, 7, $eventId, 'revoked');

        $metrics = (new ScannerMetricsService())->forEvent(7, $eventId);

        $this->assertSame('Launch Night', $metrics['event']['title']);
        $this->assertSame(4, $metrics['total']);
        $this->assertSame(3, $metrics['scanned']);
        $this->assertSame(1, $metrics['remaining']);
        $this->assertSame([
            ['gate' => 'Main gate', 'scanned' => 2],
            ['gate' => 'VIP gate', 'scanned' => 1],
        ], $metrics['per_gate']);
    }

    public function test_metrics_are_organizer_scoped(): void
    {
        $eventId = DB::table('events')->insertGetId([
            'organizer_id' => 7, 'status' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->expectException(ValidationException::class);
        (new ScannerMetricsService())->forEvent(8, $eventId);
    }

    private function booking(int $organizerId, int $eventId, string $payment, ?string $refund = null): int
    {
        return DB::table('bookings')->insertGetId([
            'organizer_id' => $organizerId, 'event_id' => $eventId,
            'paymentStatus' => $payment, 'refund_status' => $refund,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function ticket(int $bookingId, int $organizerId, int $eventId, string $status, ?int $gate = null): void
    {
        DB::table('booking_tickets')->insert([
            'booking_id' => $bookingId, 'organizer_id' => $organizerId, 'event_id' => $eventId,
            'status' => $status, 'checked_in_at' => $status === 'checked_in' ? now() : null,
            'checked_in_scanner_device_id' => $gate,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function schema(): void
    {
        Schema::create('events', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('organizer_id'); $table->integer('status')->default(1);
            $table->date('start_date')->nullable(); $table->timestamps();
        });
        Schema::create('event_contents', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('event_id'); $table->string('title')->nullable(); $table->timestamps();
        });
        Schema::create('bookings', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('event_id'); $table->unsignedBigInteger('organizer_id');
            $table->string('paymentStatus')->nullable(); $table->string('refund_status')->nullable(); $table->timestamps();
        });
        Schema::create('booking_tickets', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('booking_id'); $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('organizer_id'); $table->string('status');
            $table->timestamp('checked_in_at')->nullable(); $table->unsignedBigInteger('checked_in_scanner_device_id')->nullable();
            $table->timestamps();
        });
        Schema::create('scanner_devices', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('organizer_id'); $table->string('name'); $table->timestamps();
        });
    }
}
