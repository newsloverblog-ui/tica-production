<?php

namespace App\Jobs;

use App\Models\EventGuestImport;
use App\Models\EventGuestImportRow;
use App\Models\EventGuestInvitation;
use App\Services\AuditLogger;
use App\Services\EventGuestImportService;
use App\Services\EventGuestListService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class ProcessEventGuestImport implements ShouldQueue, ShouldBeEncrypted, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 4;
    public int $timeout = 900;
    public int $uniqueFor = 3600;

    public function __construct(public int $importId) { $this->onQueue('default'); }
    public function uniqueId(): string { return 'event-guest-import:'.$this->importId; }
    public function backoff(): array { return [60, 300, 900]; }
    public function middleware(): array
    {
        return [(new WithoutOverlapping($this->uniqueId()))->expireAfter($this->timeout + 60)];
    }

    public function handle(EventGuestListService $guests): void
    {
        $import = EventGuestImport::with('event')->findOrFail($this->importId);
        if (in_array($import->status, ['completed', 'completed_with_errors'], true)) return;
        if ($import->expires_at && $import->expires_at->isPast()) {
            $this->deleteSource($import);
            $import->forceFill(['status' => 'expired', 'last_error' => null])->save();
            return;
        }
        if (!$import->event) throw new RuntimeException('The event is no longer available.');

        $import->forceFill([
            'status' => 'processing', 'started_at' => $import->started_at ?: now(),
            'attempts' => (int) $import->attempts + 1, 'last_error' => null,
        ])->save();

        try {
            $stream = Storage::disk($import->disk)->readStream($import->path);
            if (!is_resource($stream)) throw new RuntimeException('The private guest import source is unavailable.');
            try {
                $headers = $this->headers($stream);
                $seen = [];
                $rowNumber = 1;
                $nonBlank = 0;
                while (($values = fgetcsv($stream, null, ',', '"', '')) !== false) {
                    $rowNumber++;
                    if (collect($values)->every(fn ($value) => trim((string) $value) === '')) continue;
                    if (++$nonBlank > max(1, (int) config('tickets.guest_import_max_rows', 5000))) {
                        throw new RuntimeException('The CSV exceeds the configured row limit.');
                    }
                    if (count($values) !== count($headers)) {
                        $this->reject($import, $rowNumber, 'malformed_row', 'The row does not match the template column count.', $values);
                        continue;
                    }
                    $row = array_combine($headers, array_map(static fn ($value) => trim((string) $value), $values));
                    $email = mb_strtolower((string) ($row['email'] ?? ''));
                    $emailFingerprint = $this->fingerprint(['email' => $email]);
                    if (isset($seen[$emailFingerprint])) {
                        $this->reject($import, $rowNumber, 'duplicate_email', 'Each email address may appear only once per file.', $row, $emailFingerprint);
                        continue;
                    }
                    $seen[$emailFingerprint] = true;

                    $ledger = EventGuestImportRow::updateOrCreate(
                        ['import_id' => $import->id, 'row_number' => $rowNumber],
                        ['email_fingerprint' => $emailFingerprint, 'status' => 'pending', 'invitation_id' => null,
                            'error_code' => null, 'error_message' => null, 'row_fingerprint' => $this->fingerprint($row)]
                    );
                    if ($ledger->wasRecentlyCreated === false && $ledger->status === 'accepted') continue;
                    $existing = EventGuestInvitation::query()
                        ->where('source_import_id', $import->id)->where('source_import_row', $rowNumber)->first();
                    if ($existing) {
                        $this->accept($ledger, $existing->id);
                        continue;
                    }

                    try {
                        $invitation = $guests->invite($import->event, (int) $import->organizer_id, [
                            'name' => $row['name'] ?? '', 'email' => $email,
                            'party_size' => $row['party_size'] ?? 1, 'source_attested' => true,
                            'source_import_id' => $import->id, 'source_import_row' => $rowNumber,
                        ]);
                        $this->accept($ledger, $invitation->id);
                    } catch (Throwable $exception) {
                        $message = $exception instanceof ValidationException
                            ? (string) (collect($exception->errors())->flatten()->first() ?: 'The guest data is invalid.')
                            : 'The guest could not be imported. Review the row and try again.';
                        $ledger->forceFill(['status' => 'rejected', 'invitation_id' => null,
                            'error_code' => 'validation_failed', 'error_message' => mb_substr($message, 0, 1000)])->save();
                    }
                }
            } finally {
                fclose($stream);
            }

            $counts = EventGuestImportRow::where('import_id', $import->id)
                ->selectRaw('status, count(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');
            $accepted = (int) ($counts['accepted'] ?? 0);
            $rejected = (int) ($counts['rejected'] ?? 0);
            $this->deleteSource($import);
            $before = $import->only(['status', 'attempts']);
            $import->forceFill([
                'status' => $rejected ? 'completed_with_errors' : 'completed',
                'total_rows' => $accepted + $rejected, 'accepted_rows' => $accepted,
                'rejected_rows' => $rejected, 'completed_at' => now(), 'last_error' => null,
            ])->save();
            AuditLogger::record('event.guest_import_completed', $import, $before, [
                'status' => $import->status, 'total_rows' => $import->total_rows,
                'accepted_rows' => $accepted, 'rejected_rows' => $rejected, 'source_deleted' => true,
            ], ['event_id' => $import->event_id]);
        } catch (Throwable $exception) {
            $import->forceFill(['status' => 'failed', 'last_error' => mb_substr($this->safeFatal($exception), 0, 2000)])->save();
            throw $exception;
        }
    }

    private function headers($stream): array
    {
        $headers = fgetcsv($stream, null, ',', '"', '');
        if (!is_array($headers) || $headers === []) throw new RuntimeException('The CSV header row is missing.');
        $headers = array_map(fn ($value) => strtolower(ltrim(trim((string) $value), "\xEF\xBB\xBF")), $headers);
        if (count($headers) > max(3, (int) config('tickets.guest_import_max_columns', 10))) {
            throw new RuntimeException('The CSV has too many columns.');
        }
        if (count($headers) !== count(array_unique($headers))) throw new RuntimeException('The CSV contains duplicate column names.');
        if (array_diff(EventGuestImportService::HEADERS, $headers) !== [] || array_diff($headers, EventGuestImportService::HEADERS) !== []) {
            throw new RuntimeException('Use the current guest import template with name, email and party_size columns.');
        }
        return $headers;
    }

    private function accept(EventGuestImportRow $row, int $invitationId): void
    {
        $row->forceFill(['status' => 'accepted', 'invitation_id' => $invitationId,
            'error_code' => null, 'error_message' => null])->save();
    }

    private function reject(EventGuestImport $import, int $rowNumber, string $code, string $message, array $row, ?string $emailFingerprint = null): void
    {
        EventGuestImportRow::updateOrCreate(
            ['import_id' => $import->id, 'row_number' => $rowNumber],
            ['email_fingerprint' => $emailFingerprint ?: $this->fingerprint(['row' => $rowNumber]),
                'status' => 'rejected', 'invitation_id' => null, 'error_code' => $code,
                'error_message' => $message, 'row_fingerprint' => $this->fingerprint($row)]
        );
    }

    private function fingerprint(array $value): string
    {
        return hash_hmac('sha256', json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), (string) config('app.key'));
    }

    private function deleteSource(EventGuestImport $import): void
    {
        if (!$import->source_deleted_at) {
            Storage::disk($import->disk)->delete($import->path);
            $import->forceFill(['source_deleted_at' => now()])->save();
        }
    }

    private function safeFatal(Throwable $exception): string
    {
        return $exception instanceof RuntimeException ? $exception->getMessage()
            : 'The guest import could not be processed. Retry it or upload a corrected template.';
    }

    public function failed(Throwable $exception): void
    {
        $import = EventGuestImport::find($this->importId);
        if ($import && !in_array($import->status, ['completed', 'completed_with_errors'], true)) {
            $import->forceFill(['status' => 'failed', 'last_error' => mb_substr($this->safeFatal($exception), 0, 2000)])->save();
        }
    }
}
