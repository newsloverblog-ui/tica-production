<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->unsignedSmallInteger('seat_reservation_minutes')->default(15);
        });

        Schema::table('tickets', function (Blueprint $table): void {
            $table->string('currency', 3)->nullable();
            $table->unsignedInteger('min_buy_ticket')->default(1);
            $table->timestamp('sales_start_at')->nullable()->index();
            $table->timestamp('sales_end_at')->nullable()->index();
            $table->string('visibility', 24)->default('public')->index();
            $table->string('sales_status', 24)->default('on_sale')->index();
            $table->boolean('numbering_enabled')->default(true);
            $table->string('numbering_scope', 16)->default('category');
            $table->string('number_prefix', 20)->nullable();
            $table->unsignedBigInteger('number_start')->default(1);
            $table->unsignedTinyInteger('number_padding')->default(3);
            $table->string('number_format', 80)->default('{PREFIX}-{NUMBER}');
            $table->string('seating_mode', 24)->default('general_admission')->index();
            $table->json('design_settings')->nullable();
        });

        Schema::create('ticket_number_sequences', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('event_id')->index();
            $table->unsignedBigInteger('ticket_id')->nullable()->index();
            $table->string('scope_key', 64);
            $table->unsignedBigInteger('next_number');
            $table->timestamps();
            $table->unique(['event_id', 'scope_key']);
        });

        Schema::create('seating_sections', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('event_id')->index();
            $table->string('name', 120);
            $table->string('kind', 24)->default('seated');
            $table->unsignedBigInteger('ticket_id')->nullable()->index();
            $table->decimal('price_override', 18, 2)->nullable();
            $table->unsignedInteger('capacity')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('layout')->nullable();
            $table->timestamps();
            $table->unique(['event_id', 'name']);
        });

        Schema::create('seating_rows', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('event_id')->index();
            $table->unsignedBigInteger('section_id')->index();
            $table->string('label', 40);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['section_id', 'label']);
        });

        Schema::create('event_seats', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->unsignedBigInteger('event_id')->index();
            $table->unsignedBigInteger('section_id')->index();
            $table->unsignedBigInteger('row_id')->nullable()->index();
            $table->unsignedBigInteger('ticket_id')->nullable()->index();
            $table->string('label', 40);
            $table->string('status', 24)->default('available')->index();
            $table->boolean('is_accessible')->default(false);
            $table->decimal('price_override', 18, 2)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['event_id', 'section_id', 'row_id', 'label'], 'event_seats_identity_unique');
        });

        Schema::create('seat_reservations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('token')->index();
            $table->unsignedBigInteger('event_id')->index();
            $table->unsignedBigInteger('ticket_id')->index();
            $table->unsignedBigInteger('seat_id')->index();
            $table->string('status', 24)->default('active')->index();
            $table->string('session_hash', 64)->nullable();
            $table->unsignedBigInteger('customer_id')->nullable()->index();
            $table->char('buyer_email_hash', 64)->nullable()->index();
            $table->unsignedBigInteger('booking_id')->nullable()->index();
            $table->unsignedBigInteger('booking_ticket_id')->nullable()->unique();
            $table->timestamp('expires_at')->index();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'expires_at']);
            $table->index(['event_id', 'ticket_id', 'status']);
            $table->index(['seat_id', 'status', 'expires_at']);
        });

        Schema::table('booking_tickets', function (Blueprint $table): void {
            $table->string('ticket_number', 80)->nullable();
            $table->unsignedBigInteger('ticket_number_value')->nullable();
            $table->unsignedBigInteger('seat_id')->nullable()->unique();
            $table->string('seat_label', 40)->nullable();
            $table->string('row_label', 40)->nullable();
            $table->string('section_label', 120)->nullable();
            $table->unique(['event_id', 'ticket_number'], 'booking_tickets_event_number_unique');
        });

        Schema::table('bookings', function (Blueprint $table): void {
            $table->uuid('ticket_reservation_token')->nullable()->index();
        });

        Schema::create('ticket_number_allocations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('event_id')->index();
            $table->unsignedBigInteger('ticket_id')->index();
            $table->unsignedBigInteger('booking_ticket_id')->unique();
            $table->unsignedBigInteger('number_value');
            $table->string('ticket_number', 80);
            $table->string('status', 24)->default('allocated')->index();
            $table->timestamp('allocated_at');
            $table->timestamp('invalidated_at')->nullable();
            $table->timestamps();
            $table->unique(['event_id', 'ticket_number']);
            $table->unique(['ticket_id', 'number_value']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_number_allocations');
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropIndex(['ticket_reservation_token']);
            $table->dropColumn('ticket_reservation_token');
        });
        Schema::table('booking_tickets', function (Blueprint $table): void {
            $table->dropUnique('booking_tickets_event_number_unique');
            $table->dropUnique(['seat_id']);
            $table->dropColumn(['ticket_number', 'ticket_number_value', 'seat_id', 'seat_label', 'row_label', 'section_label']);
        });
        Schema::dropIfExists('seat_reservations');
        Schema::dropIfExists('event_seats');
        Schema::dropIfExists('seating_rows');
        Schema::dropIfExists('seating_sections');
        Schema::dropIfExists('ticket_number_sequences');
        Schema::table('tickets', function (Blueprint $table): void {
            $table->dropColumn([
                'currency', 'min_buy_ticket', 'sales_start_at', 'sales_end_at', 'visibility',
                'sales_status', 'numbering_enabled', 'numbering_scope', 'number_prefix', 'number_start',
                'number_padding', 'number_format', 'seating_mode', 'design_settings',
            ]);
        });
        Schema::table('events', fn (Blueprint $table) => $table->dropColumn('seat_reservation_minutes'));
    }
};
