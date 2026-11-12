<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('product_orders')) {
            return;
        }

        $addInventoryReservedAt = !Schema::hasColumn('product_orders', 'inventory_reserved_at');
        $addInventoryReleasedAt = !Schema::hasColumn('product_orders', 'inventory_released_at');
        $addArchivedAt = !Schema::hasColumn('product_orders', 'archived_at');
        $addArchivedBy = !Schema::hasColumn('product_orders', 'archived_by');

        Schema::table('product_orders', function (Blueprint $table) use (
            $addInventoryReservedAt,
            $addInventoryReleasedAt,
            $addArchivedAt,
            $addArchivedBy
        ): void {
            if ($addInventoryReservedAt) {
                $table->timestamp('inventory_reserved_at')->nullable()->after('conversation_id');
            }
            if ($addInventoryReleasedAt) {
                $table->timestamp('inventory_released_at')->nullable()->after('inventory_reserved_at');
            }
            if ($addArchivedAt) {
                $table->timestamp('archived_at')->nullable()->index()->after('inventory_released_at');
            }
            if ($addArchivedBy) {
                $table->unsignedBigInteger('archived_by')->nullable()->after('archived_at');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('product_orders')) {
            return;
        }

        $columns = array_values(array_filter([
            Schema::hasColumn('product_orders', 'archived_by') ? 'archived_by' : null,
            Schema::hasColumn('product_orders', 'archived_at') ? 'archived_at' : null,
            Schema::hasColumn('product_orders', 'inventory_released_at') ? 'inventory_released_at' : null,
            Schema::hasColumn('product_orders', 'inventory_reserved_at') ? 'inventory_reserved_at' : null,
        ]));

        Schema::table('product_orders', function (Blueprint $table) use ($columns): void {
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
