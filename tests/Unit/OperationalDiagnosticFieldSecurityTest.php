<?php

namespace Tests\Unit;

use App\Casts\PrefixedEncryptedString;
use App\Models\BookingDelivery;
use App\Models\BookingTicketDeliveryRequest;
use App\Models\CustomerEmailVerificationDelivery;
use App\Models\DataExport;
use App\Models\DataExportSchedule;
use App\Models\Event\BookingTicketTransfer;
use App\Models\EventCancellationNotification;
use App\Models\EventRegistrationImport;
use App\Models\EventRegistrationNotification;
use App\Models\EventWaitlistEntry;
use App\Models\OrganizerEmailVerificationDelivery;
use App\Models\ProductOrderDelivery;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OperationalDiagnosticFieldSecurityTest extends TestCase
{
    private const MODELS = [
        'booking_deliveries' => BookingDelivery::class,
        'booking_ticket_delivery_requests' => BookingTicketDeliveryRequest::class,
        'customer_email_verification_deliveries' => CustomerEmailVerificationDelivery::class,
        'data_exports' => DataExport::class,
        'data_export_schedules' => DataExportSchedule::class,
        'booking_ticket_transfers' => BookingTicketTransfer::class,
        'event_cancellation_notifications' => EventCancellationNotification::class,
        'event_registration_imports' => EventRegistrationImport::class,
        'event_registration_notifications' => EventRegistrationNotification::class,
        'event_waitlist_entries' => EventWaitlistEntry::class,
        'organizer_email_verification_deliveries' => OrganizerEmailVerificationDelivery::class,
        'product_order_deliveries' => ProductOrderDelivery::class,
    ];

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

        foreach (array_keys(self::MODELS) as $table) {
            Schema::create($table, function (Blueprint $blueprint): void {
                $blueprint->id();
                $blueprint->text('last_error')->nullable();
                $blueprint->timestamps();
            });
        }
    }

    public function test_migration_and_models_encrypt_operational_diagnostics(): void
    {
        foreach (array_keys(self::MODELS) as $table) {
            DB::table($table)->insert([
                'last_error' => "private operational diagnostic for {$table}",
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $migration = require database_path(
            'migrations/2026_11_06_190000_encrypt_operational_diagnostic_fields.php'
        );
        $migration->up();

        foreach (self::MODELS as $table => $modelClass) {
            $raw = DB::table($table)->value('last_error');
            $this->assertStringStartsWith(PrefixedEncryptedString::PREFIX, $raw, $table);
            $this->assertStringNotContainsString('private operational diagnostic', $raw, $table);
            $this->assertSame(
                "private operational diagnostic for {$table}",
                $modelClass::query()->firstOrFail()->last_error,
                $table
            );
        }

        $delivery = ProductOrderDelivery::query()->firstOrFail();
        $delivery->last_error = 'replacement private delivery diagnostic';
        $delivery->save();
        $this->assertStringStartsWith(
            PrefixedEncryptedString::PREFIX,
            DB::table('product_order_deliveries')->value('last_error')
        );

        $migration->down();
        $this->assertSame(
            'replacement private delivery diagnostic',
            DB::table('product_order_deliveries')->value('last_error')
        );
        foreach (array_keys(self::MODELS) as $table) {
            $this->assertFalse(
                str_starts_with(
                    (string) DB::table($table)->value('last_error'),
                    PrefixedEncryptedString::PREFIX
                ),
                $table
            );
        }
    }
}
