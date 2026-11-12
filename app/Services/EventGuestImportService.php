<?php

namespace App\Services;

use App\Jobs\ProcessEventGuestImport;
use App\Models\Event;
use App\Models\EventGuestImport;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class EventGuestImportService
{
    public const HEADERS = ['name', 'email', 'party_size'];

    public function queue(Event $event, int $organizerId, UploadedFile $file, bool $attested): EventGuestImport
    {
        if (!$attested) {
            throw new DomainException('Confirm that you are permitted to contact every guest in this file about this event.');
        }
        if ((int) $event->organizer_id !== $organizerId || (int) $event->status !== 1
            || ($event->lifecycle_status ?: 'scheduled') !== 'scheduled' || $event->cancelled_at) {
            throw new DomainException('This event is not available for guest imports.');
        }
        $maxBytes = max(64, (int) config('tickets.guest_import_max_file_kb', 2048)) * 1024;
        $size = $file->getSize();
        if ($size === false || $size < 1 || $size > $maxBytes) {
            throw new DomainException('The guest import must be a non-empty CSV within the configured file limit.');
        }

        $disk = (string) config('tickets.guest_import_disk', 'local');
        $diskConfig = (array) config('filesystems.disks.'.$disk, []);
        if ($disk === 'public' || $diskConfig === [] || ($diskConfig['visibility'] ?? 'private') === 'public') {
            throw new DomainException('Guest import storage must be private.');
        }
        $publicId = (string) Str::ulid();
        $path = 'guest-imports/'.$event->id.'/'.$publicId.'.csv';
        $stored = $file->storeAs(dirname($path), basename($path), $disk);
        if (!$stored || !Storage::disk($disk)->exists($path)) {
            throw new DomainException('The private guest import file could not be stored.');
        }

        try {
            return DB::transaction(function () use ($publicId, $event, $organizerId, $disk, $path): EventGuestImport {
                $import = EventGuestImport::create([
                    'public_id' => $publicId, 'event_id' => $event->id, 'organizer_id' => $organizerId,
                    'status' => 'queued', 'disk' => $disk, 'path' => $path,
                    'attestation_version' => (string) config('tickets.guest_invitation_attestation_version', 'v1'),
                    'attested_at' => now(),
                    'expires_at' => now()->addDays(max(1, (int) config('tickets.guest_import_retention_days', 7))),
                ]);
                AuditLogger::record('event.guest_import_queued', $import, [], [
                    'status' => 'queued', 'attestation_version' => $import->attestation_version,
                ], ['event_id' => $event->id]);
                DB::afterCommit(fn () => $this->dispatchQueued($import->id));
                return $import;
            }, 3);
        } catch (Throwable $exception) {
            Storage::disk($disk)->delete($path);
            throw $exception;
        }
    }

    public function dispatchQueued(int $importId): bool
    {
        $import = EventGuestImport::query()->find($importId);
        if (!$import || $import->status !== 'queued') {
            return true;
        }
        $hasMetadata = Schema::hasColumn('event_guest_imports', 'dispatch_attempts');
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
        ProcessEventGuestImport::dispatch($importId);
    }
}
