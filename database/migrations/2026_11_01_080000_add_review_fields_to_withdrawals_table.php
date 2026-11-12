<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('withdraws', function (Blueprint $table) {
            $table->string('payout_reference')->nullable()->unique()->after('additional_reference');
            $table->text('decision_reason')->nullable()->after('payout_reference');
            $table->unsignedBigInteger('reviewed_by_admin_id')->nullable()->after('decision_reason');
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by_admin_id');
            $table->timestamp('paid_at')->nullable()->after('reviewed_at');
        });
    }

    public function down()
    {
        Schema::table('withdraws', function (Blueprint $table) {
            $table->dropUnique(['payout_reference']);
            $table->dropColumn([
                'payout_reference', 'decision_reason', 'reviewed_by_admin_id', 'reviewed_at', 'paid_at',
            ]);
        });
    }
};
