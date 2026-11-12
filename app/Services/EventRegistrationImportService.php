<?php

namespace App\Services;

use App\Jobs\ProcessEventRegistrationImport;
use App\Models\Event;
use App\Models\EventRegistrationForm;
use App\Models\EventRegistrationImport;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class EventRegistrationImportService
{
    public const BASE_HEADERS = [
        'registration_key', 'primary_name', 'primary_email', 'consent_confirmed',
        'attendee_name', 'attendee_email',
    ];

    public function queue(
        Event $event,
        int $organizerId,
        UploadedFile $file,
        bool $attested,
    ): EventRegistrationImport {
        if (!$attested) {
            throw new DomainException('Confirm that you are authorized to register and contact every person in this file.');
        }
        if ((int) $event->organizer_id !== $organizerId
            || ($event->registration_mode ?: 'ticketing') !== 'corporate'
            || (int) $event->status !== 1
            || ($event->lifecycle_status ?: 'scheduled') !== 'scheduled'
            || $event->cancelled_at
        ) {
            throw new DomainException('This event is not available for registration imports.');
        }
        $form = EventRegistrationForm::with('questions')
            ->where('event_id', $event->id)
            ->where('organizer_id', $organizerId)
            ->where('status', 'published')
            ->first();
        if (!$form) {
            throw new DomainException('Publish the registration form before importing registrations.');
        }
        if ($form->questions->contains(fn ($question): bool => $question->type === 'file' && $question->is_required)) {
            throw new DomainException('This form requires a file upload, so registrations must be completed through the secure online form.');
        }

        $disk = (string) config('registration.import_disk', 'local');
        $diskConfig = (array) config('filesystems.disks.'.$disk, []);
        if ($disk === 'public' || $diskConfig === [] || ($diskConfig['visibility'] ?? 'private') === 'public') {
            throw new DomainException('Registration import storage must be private.');
        }
        $publicId = (string) Str::ulid();
        $path = 'registration-imports/'.$event->id.'/'.$publicId.'.csv';
        $stored = $file->storeAs(dirname($path), basename($path), $disk);
        if (!$stored || !Storage::disk($disk)->exists($path)) {
            throw new DomainException('The private import file could not be stored.');
        }

        try {
            $import = DB::transaction(function () use ($publicId, $form, $event, $organizerId, $disk, $path, $file): EventRegistrationImport {
                $import = EventRegistrationImport::create([
                    'public_id' => $publicId,
                    'form_id' => $form->id,
                    'event_id' => $event->id,
                    'organizer_id' => $organizerId,
                    'status' => 'queued',
                    'disk' => $disk,
                    'path' => $path,
                    // Never persist the client filename: it may itself contain PII.
                    'original_filename' => 'registration-import.csv',
                    'attestation_version' => (string) config('registration.import_attestation_version'),
                    'attested_at' => now(),
                    'expires_at' => now()->addDays((int) config('registration.import_retention_days', 7)),
                ]);
                AuditLogger::record('event.registration_import_queued', $import, [], [
                    'status' => 'queued', 'attestation_version' => $import->attestation_version,
                ], ['event_id' => $event->id, 'form_id' => $form->id]);
                DB::afterCommit(fn () => $this->dispatchQueued($import->id));

                return $import;
            }, 3);
        } catch (Throwable $exception) {
            Storage::disk($disk)->delete($path);
            throw $exception;
        }

        return $import;
    }

    public function dispatchQueued(int $importId): bool
    {
        $import = EventRegistrationImport::query()->find($importId);
        if (!$import || $import->status !== 'queued') {
            return true;
        }
        $hasMetadata = Schema::hasColumn('event_registration_imports', 'dispatch_attempts');
        if ($hasMetadata) {
            $import->forceFill([
                'dispatch_attempts' => (int) $import->dispatch_attempts + 1,
                'dispatch_last_attempt_at' => now(),
                'dispatch_last_error' => null,
            ])->save();
        }
        try {
            $this->pushImportJob($import->id);
            return true;
        } catch (Throwable $exception) {
            if (!$hasMetadata) {
                throw $exception;
            }
            $import->forceFill([
                'dispatch_last_error' => mb_substr($exception->getMessage(), 0, 2000),
            ])->save();
            return false;
        }
    }

    protected function pushImportJob(int $importId): void
    {
        ProcessEventRegistrationImport::dispatch($importId);
    }

    public function headers(EventRegistrationForm $form, int $organizerId): array
    {
        if ((int) $form->organizer_id !== $organizerId) {
            throw new DomainException('You cannot download another organizer’s import template.');
        }

        $base = self::BASE_HEADERS;
        if (Schema::hasTable('event_registration_sessions')) {
            $form->loadMissing('sessions');
        }
        $publishedSessions = $form->relationLoaded('sessions')
            ? $form->sessions->where('status', 'published')
            : collect();
        $hasTickets = \App\Models\Event\Ticket::query()->where('event_id', $form->event_id)->exists();
        if ($hasTickets || $form->questions->contains(fn ($question): bool => (int) ($question->ticket_id ?? 0) > 0)
            || $publishedSessions->contains(fn ($session): bool => (int) ($session->ticket_id ?? 0) > 0)) {
            $base[] = 'attendee_ticket_id';
        }
        if (\App\Models\Event\Ticket::query()->where('event_id', $form->event_id)->where('pricing_type', 'variation')->exists()) {
            $base[] = 'attendee_variation_name';
        }
        if ($publishedSessions->isNotEmpty()) {
            $base[] = 'attendee_session_ids';
        }

        return array_merge($base, $form->questions->whereNotIn('type', ['terms', 'file'])->map(
            static fn ($question): string => $question->scope.'.'.$question->question_key
        )->all());
    }
}
