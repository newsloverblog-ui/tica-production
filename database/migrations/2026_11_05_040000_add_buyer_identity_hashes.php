<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->char('buyer_email_hash', 64)->nullable()->after('checkout_submission_hash');
            $table->unsignedSmallInteger('buyer_email_hash_version')->nullable()->after('buyer_email_hash');
            $table->index(['event_id', 'buyer_email_hash'], 'bookings_event_buyer_email_idx');
        });
        Schema::table('ticket_reservations', function (Blueprint $table): void {
            $table->char('buyer_email_hash', 64)->nullable()->after('customer_id');
            $table->unsignedSmallInteger('buyer_email_hash_version')->nullable()->after('buyer_email_hash');
            $table->index(
                ['event_id', 'ticket_id', 'buyer_email_hash', 'status'],
                'ticket_res_event_buyer_status_idx',
            );
        });

        $key = (string) (config('tickets.buyer_identity_hash_key') ?: config('app.key'));
        $version = max(1, (int) config('tickets.buyer_identity_hash_version', 1));
        if ($key === '') {
            throw new RuntimeException('APP_KEY is required to backfill booking buyer identities.');
        }

        DB::table('bookings')
            ->select(['id', 'email'])
            ->where(static fn ($query) => $query
                ->whereNull('customer_id')
                ->orWhere('customer_id', ''))
            ->whereNull('buyer_email_hash')
            ->orderBy('id')
            ->chunkById(500, function ($bookings) use ($key, $version): void {
                foreach ($bookings as $booking) {
                    $email = mb_strtolower(trim((string) $booking->email));
                    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        continue;
                    }
                    DB::table('bookings')->where('id', $booking->id)->update([
                        'buyer_email_hash' => hash_hmac('sha256', $email, $key),
                        'buyer_email_hash_version' => $version,
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('ticket_reservations', function (Blueprint $table): void {
            $table->dropIndex('ticket_res_event_buyer_status_idx');
            $table->dropColumn(['buyer_email_hash', 'buyer_email_hash_version']);
        });
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropIndex('bookings_event_buyer_email_idx');
            $table->dropColumn(['buyer_email_hash', 'buyer_email_hash_version']);
        });
    }
};
