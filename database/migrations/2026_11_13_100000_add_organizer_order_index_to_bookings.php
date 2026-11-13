<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEX = 'bookings_organizer_orders_idx';

    public function up(): void
    {
        if (! Schema::hasIndex('bookings', self::INDEX)) {
            Schema::table('bookings', function (Blueprint $table): void {
                $table->index(['organizer_id', 'id'], self::INDEX);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('bookings', self::INDEX)) {
            Schema::table('bookings', function (Blueprint $table): void {
                $table->dropIndex(self::INDEX);
            });
        }
    }
};
