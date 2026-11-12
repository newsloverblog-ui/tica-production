<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->string('registration_mode', 24)->default('ticketing')->after('event_type')->index();
        });

        Schema::create('event_registration_forms', function (Blueprint $table): void {
            $table->id();
            $table->string('public_id', 26)->unique();
            $table->unsignedBigInteger('event_id')->unique();
            $table->unsignedBigInteger('organizer_id')->index();
            $table->string('name', 150);
            $table->string('status', 24)->default('draft')->index();
            $table->string('approval_mode', 24)->default('manual');
            $table->unsignedInteger('version')->default(1);
            $table->text('intro')->nullable();
            $table->text('privacy_notice');
            $table->string('consent_version', 32)->default('registration-v1');
            $table->timestamp('published_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
        });

        Schema::create('event_registration_questions', function (Blueprint $table): void {
            $table->id();
            $table->string('public_id', 26)->unique();
            $table->unsignedBigInteger('form_id')->index();
            $table->string('question_key', 80);
            $table->string('label', 191);
            $table->text('help_text')->nullable();
            $table->string('type', 24);
            $table->string('scope', 24)->default('registrant');
            $table->boolean('is_required')->default(false);
            $table->json('options')->nullable();
            $table->json('condition')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['form_id', 'question_key']);
            $table->index(['form_id', 'scope', 'position'], 'registration_question_order');
        });

        Schema::create('event_registration_submissions', function (Blueprint $table): void {
            $table->id();
            $table->string('public_id', 26)->unique();
            $table->unsignedBigInteger('form_id')->index();
            $table->unsignedBigInteger('event_id')->index();
            $table->unsignedBigInteger('organizer_id')->index();
            $table->unsignedBigInteger('customer_id')->nullable()->index();
            $table->unsignedBigInteger('booking_id')->nullable()->index();
            $table->unsignedInteger('form_version');
            $table->string('status', 24)->default('submitted')->index();
            $table->text('primary_name');
            $table->text('primary_email');
            $table->char('primary_email_hash', 64)->index();
            $table->unsignedSmallInteger('attendee_count')->default(1);
            $table->string('consent_version', 32);
            $table->timestamp('consented_at');
            $table->char('source_ip_hash', 64)->nullable();
            $table->timestamp('submitted_at');
            $table->unsignedBigInteger('reviewed_by_organizer_id')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'status', 'submitted_at'], 'registration_submission_event_status');
        });

        Schema::create('event_registration_attendees', function (Blueprint $table): void {
            $table->id();
            $table->string('public_id', 26)->unique();
            $table->unsignedBigInteger('submission_id')->index();
            $table->unsignedBigInteger('ticket_id')->nullable()->index();
            $table->unsignedBigInteger('booking_ticket_id')->nullable()->index();
            $table->unsignedSmallInteger('sequence');
            $table->text('name');
            $table->text('email')->nullable();
            $table->char('email_hash', 64)->nullable();
            $table->string('status', 24)->default('registered');
            $table->timestamps();

            $table->unique(['submission_id', 'sequence']);
        });

        Schema::create('event_registration_answers', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('submission_id')->index();
            $table->unsignedBigInteger('attendee_id')->nullable()->index();
            $table->unsignedBigInteger('question_id')->index();
            $table->string('question_key', 80);
            $table->string('question_label', 191);
            $table->string('question_type', 24);
            $table->text('value')->nullable();
            $table->timestamps();

            $table->unique(['submission_id', 'attendee_id', 'question_id'], 'registration_answer_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_registration_answers');
        Schema::dropIfExists('event_registration_attendees');
        Schema::dropIfExists('event_registration_submissions');
        Schema::dropIfExists('event_registration_questions');
        Schema::dropIfExists('event_registration_forms');
        Schema::table('events', function (Blueprint $table): void {
            $table->dropIndex(['registration_mode']);
            $table->dropColumn('registration_mode');
        });
    }
};
