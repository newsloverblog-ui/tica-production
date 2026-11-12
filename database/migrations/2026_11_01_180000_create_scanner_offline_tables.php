<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('scanner_offline_manifests', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->unsignedBigInteger('organizer_id')->index();
            $table->unsignedBigInteger('event_id')->index();
            $table->unsignedBigInteger('scanner_device_id')->index();
            $table->unsignedBigInteger('scanner_staff_id')->nullable()->index();
            $table->string('status', 20)->default('active')->index();
            $table->char('snapshot_hash', 64);
            $table->unsignedInteger('ticket_count')->default(0);
            $table->timestamp('generated_at');
            $table->timestamp('expires_at')->index();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(
                ['organizer_id', 'scanner_device_id', 'event_id', 'status'],
                'scanner_offline_manifest_scope'
            );
        });

        Schema::create('scanner_offline_manifest_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('scanner_offline_manifest_id')->index();
            $table->unsignedBigInteger('booking_ticket_id')->index();
            $table->char('payload_hash', 64);
            $table->timestamps();

            $table->unique(
                ['scanner_offline_manifest_id', 'payload_hash'],
                'scanner_offline_manifest_payload_unique'
            );
        });

        Schema::create('scanner_offline_sync_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('client_scan_id');
            $table->unsignedBigInteger('scanner_offline_manifest_id')->index();
            $table->unsignedBigInteger('organizer_id')->index();
            $table->unsignedBigInteger('scanner_device_id')->index();
            $table->unsignedBigInteger('scanner_staff_id')->nullable()->index();
            $table->unsignedBigInteger('booking_ticket_id')->nullable()->index();
            $table->char('payload_hash', 64);
            $table->string('result', 20)->default('processing')->index();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->json('response')->nullable();
            $table->timestamp('client_scanned_at');
            $table->timestamp('received_at');
            $table->timestamps();

            $table->unique(
                ['scanner_device_id', 'client_scan_id'],
                'scanner_offline_sync_device_scan_unique'
            );
        });
    }

    public function down()
    {
        Schema::dropIfExists('scanner_offline_sync_items');
        Schema::dropIfExists('scanner_offline_manifest_items');
        Schema::dropIfExists('scanner_offline_manifests');
    }
};
