<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('tica_promotion_events')
            || !Schema::hasTable('tica_promotion_campaigns')
            || !Schema::hasTable('bookings')
            || !Schema::hasColumn('tica_promotion_events', 'booking_id')
            || !Schema::hasColumn('tica_promotion_campaigns', 'attributed_revenue')) {
            return;
        }

        DB::table('tica_promotion_events as promotion_events')
            ->join('bookings', 'bookings.id', '=', 'promotion_events.booking_id')
            ->where('promotion_events.event_type', 'conversion')
            ->select([
                'promotion_events.id',
                'bookings.price',
                'bookings.commission',
            ])
            ->orderBy('promotion_events.id')
            ->chunkById(500, function ($rows): void {
                foreach ($rows as $row) {
                    DB::table('tica_promotion_events')->where('id', $row->id)->update([
                        'amount' => round(max(0, (float) $row->price - (float) $row->commission), 2),
                    ]);
                }
            }, 'promotion_events.id', 'id');

        DB::table('tica_promotion_campaigns')->orderBy('id')->chunkById(500, function ($campaigns): void {
            foreach ($campaigns as $campaign) {
                $revenue = DB::table('tica_promotion_events')
                    ->where('campaign_id', $campaign->id)
                    ->where('event_type', 'conversion')
                    ->sum('amount');
                DB::table('tica_promotion_campaigns')->where('id', $campaign->id)->update([
                    'attributed_revenue' => round((float) $revenue, 2),
                ]);
            }
        });
    }

    public function down(): void
    {
        // This corrects derived aggregates only. Source bookings and conversion
        // events are retained, so reverting application code must not re-add
        // checkout fees to organizer revenue.
    }
};
