<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_registration_invoices', function (Blueprint $table): void {
            $table->id();
            $table->string('public_id', 26)->unique();
            $table->string('invoice_number', 40)->unique();
            $table->unsignedBigInteger('submission_id')->unique();
            $table->unsignedBigInteger('form_id')->index();
            $table->unsignedBigInteger('event_id')->index();
            $table->unsignedBigInteger('organizer_id')->index();
            $table->unsignedBigInteger('booking_id')->nullable()->unique();
            $table->uuid('reservation_token')->nullable()->unique();
            $table->string('status', 24)->default('issued')->index();
            $table->string('currency', 12)->default('NGN');
            $table->string('currency_symbol', 12)->nullable();
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('tax', 14, 2)->default(0);
            $table->decimal('commission', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->text('line_items');
            $table->text('purchase_order_number')->nullable();
            $table->text('payment_reference')->nullable();
            $table->text('payment_note')->nullable();
            $table->string('invoice_path')->nullable();
            $table->timestamp('issued_at');
            $table->timestamp('due_at');
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->unsignedBigInteger('marked_paid_by_organizer_id')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'status', 'due_at'], 'registration_invoice_event_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_registration_invoices');
    }
};
