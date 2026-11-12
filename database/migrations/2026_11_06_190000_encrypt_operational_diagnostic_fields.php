<?php

use App\Casts\PrefixedEncryptedString;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLES = [
        'booking_deliveries',
        'booking_ticket_delivery_requests',
        'customer_email_verification_deliveries',
        'data_exports',
        'data_export_schedules',
        'booking_ticket_transfers',
        'event_cancellation_notifications',
        'event_registration_imports',
        'event_registration_notifications',
        'event_waitlist_entries',
        'organizer_email_verification_deliveries',
        'product_order_deliveries',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            $this->transform($table, static fn (string $value): string => PrefixedEncryptedString::encrypt($value));
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            $this->transform(
                $table,
                static fn (string $value): string => PrefixedEncryptedString::decryptForRollback($value) ?? ''
            );
        }
    }

    /**
     * Process raw values in bounded transactions so model casts do not
     * double-encrypt existing diagnostics during deployment or rollback.
     */
    private function transform(string $table, callable $transform): void
    {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'last_error')) {
            return;
        }

        DB::transaction(function () use ($table, $transform): void {
            DB::table($table)
                ->select(['id', 'last_error'])
                ->whereNotNull('last_error')
                ->where('last_error', '<>', '')
                ->orderBy('id')
                ->chunkById(200, function ($rows) use ($table, $transform): void {
                    foreach ($rows as $row) {
                        DB::table($table)->where('id', $row->id)->update([
                            'last_error' => $transform((string) $row->last_error),
                        ]);
                    }
                });
        });
    }
};
