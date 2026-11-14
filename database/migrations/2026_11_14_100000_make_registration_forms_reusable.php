<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registration forms become reusable: a form can exist on its own (no event)
 * in the organiser's form library, and each event's form remembers the
 * library form it was copied from so changes can be pushed to every event.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_registration_forms', function (Blueprint $table): void {
            $table->unsignedBigInteger('event_id')->nullable()->change();
        });
        if (!Schema::hasColumn('event_registration_forms', 'source_form_id')) {
            Schema::table('event_registration_forms', function (Blueprint $table): void {
                $table->unsignedBigInteger('source_form_id')->nullable()->after('event_id')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('event_registration_forms', 'source_form_id')) {
            Schema::table('event_registration_forms', function (Blueprint $table): void {
                $table->dropIndex(['source_form_id']);
                $table->dropColumn('source_form_id');
            });
        }
    }
};
