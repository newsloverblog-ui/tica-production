<?php

namespace App\Jobs;

use App\Models\EventRegistrationImport;
use App\Models\EventRegistrationImportRow;
use App\Models\EventRegistrationSubmission;
use App\Services\AuditLogger;
use App\Services\EventRegistrationImportService;
use App\Services\EventRegistrationService;
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

class ProcessEventRegistrationImport implements ShouldQueue, ShouldBeEncrypted, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 4;
    public int $timeout = 900;
    public int $uniqueFor = 3600;

    public function __construct(public int $importId)
    {
        $this->onQueue('default');
    }

    public function uniqueId(): string
    {
        return 'event-registration-import:'.$this->importId;
    }

    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping($this->uniqueId()))->expireAfter($this->timeout + 60)];
    }

    public function handle(EventRegistrationService $registrations): void
    {
        $import = EventRegistrationImport::with(['event', 'form.questions'])->findOrFail($this->importId);
        if (in_array($import->status, ['completed', 'completed_with_errors'], true)) {
            return;
        }
        if ($import->expires_at && $import->expires_at->isPast()) {
            $this->deleteSource($import);
            $import->forceFill(['status' => 'expired', 'last_error' => null])->save();
            return;
        }
        if (!$import->event || !$import->form || $import->form->status !== 'published') {
            throw new RuntimeException('The published registration form is no longer available.');
        }

        $import->forceFill([
            'status' => 'processing',
            'started_at' => $import->started_at ?: now(),
            'attempts' => (int) $import->attempts + 1,
            'last_error' => null,
        ])->save();

        try {
            [$headers, $groups] = $this->readRows($import);
            $questions = $import->form->questions->keyBy(fn ($question) => $question->scope.'.'.$question->question_key);
            $this->assertHeaders($headers, $questions->keys()->all());

            foreach ($groups as $keyHash => $rows) {
                $rowNumbers = array_column($rows, 'row_number');
                $existing = EventRegistrationSubmission::where('source_import_id', $import->id)
                    ->where('source_import_key', $keyHash)->first();
                if ($existing) {
                    $this->acceptRows($import, $rowNumbers, $existing->id);
                    continue;
                }

                try {
                    $payload = $this->payloadForGroup($rows, $headers, $questions);
                    $submission = $registrations->submit(
                        $import->event,
                        $payload,
                        null,
                        null,
                        ['id' => $import->id, 'key' => $keyHash],
                    );
                    $this->acceptRows($import, $rowNumbers, $submission->id);
                } catch (Throwable $exception) {
                    [$code, $message] = $this->safeGroupError($exception);
                    $this->rejectRows($import, $rowNumbers, $code, $message);
                }
            }

            $counts = EventRegistrationImportRow::where('import_id', $import->id)
                ->selectRaw('status, count(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');
            $accepted = (int) ($counts['accepted'] ?? 0);
            $rejected = (int) ($counts['rejected'] ?? 0);
            $this->deleteSource($import);
            $before = $import->only(['status', 'attempts']);
            $import->forceFill([
                'status' => $rejected > 0 ? 'completed_with_errors' : 'completed',
                'total_rows' => $accepted + $rejected,
                'accepted_rows' => $accepted,
                'rejected_rows' => $rejected,
                'completed_at' => now(),
                'last_error' => null,
            ])->save();
            AuditLogger::record('event.registration_import_completed', $import, $before, [
                'status' => $import->status,
                'total_rows' => $import->total_rows,
                'accepted_rows' => $accepted,
                'rejected_rows' => $rejected,
                'source_deleted' => (bool) $import->source_deleted_at,
            ], ['event_id' => $import->event_id, 'form_id' => $import->form_id]);
        } catch (Throwable $exception) {
            $import->forceFill([
                'status' => 'failed',
                'last_error' => mb_substr($this->safeFatalError($exception), 0, 2000),
            ])->save();
            throw $exception;
        }
    }

    private function readRows(EventRegistrationImport $import): array
    {
        $stream = Storage::disk($import->disk)->readStream($import->path);
        if (!is_resource($stream)) {
            throw new RuntimeException('The private import source is unavailable.');
        }

        try {
            $header = fgetcsv($stream, null, ',', '"', '');
            if (!is_array($header) || $header === []) {
                throw new RuntimeException('The CSV header row is missing.');
            }
            $header = array_map(function ($value): string {
                $value = trim((string) $value);
                return strtolower(ltrim($value, "\xEF\xBB\xBF"));
            }, $header);
            if (count($header) > (int) config('registration.import_max_columns', 250)) {
                throw new RuntimeException('The CSV has too many columns.');
            }
            if (count($header) !== count(array_unique($header))) {
                throw new RuntimeException('The CSV contains duplicate column names.');
            }

            $groups = [];
            $rowNumber = 1;
            $maxRows = (int) config('registration.import_max_rows', 5000);
            while (($values = fgetcsv($stream, null, ',', '"', '')) !== false) {
                $rowNumber++;
                if ($this->blankRow($values)) {
                    continue;
                }
                if ($rowNumber - 1 > $maxRows) {
                    throw new RuntimeException('The CSV exceeds the configured row limit.');
                }
                if (count($values) !== count($header)) {
                    $this->recordRejectedRow($import, $rowNumber, 'malformed_row', 'The row does not match the template column count.', $values);
                    continue;
                }
                $row = array_combine($header, array_map(static fn ($value) => trim((string) $value), $values));
                $rawKey = trim((string) ($row['registration_key'] ?? ''));
                if ($rawKey === '' || mb_strlen($rawKey) > 120) {
                    $this->recordRejectedRow($import, $rowNumber, 'invalid_registration_key', 'Add a registration key of 120 characters or fewer.', $row);
                    continue;
                }
                $keyHash = $this->fingerprint(['registration_key' => $rawKey]);
                EventRegistrationImportRow::updateOrCreate(
                    ['import_id' => $import->id, 'row_number' => $rowNumber],
                    ['registration_key' => $keyHash, 'status' => 'pending', 'submission_id' => null,
                        'error_code' => null, 'error_message' => null, 'row_fingerprint' => $this->fingerprint($row)]
                );
                $groups[$keyHash][] = ['row_number' => $rowNumber, 'values' => $row];
            }

            return [$header, $groups];
        } finally {
            fclose($stream);
        }
    }

    private function assertHeaders(array $headers, array $questionHeaders): void
    {
        $required = ['registration_key', 'primary_name', 'primary_email', 'consent_confirmed', 'attendee_name'];
        $missing = array_diff($required, $headers);
        if ($missing !== []) {
            throw new RuntimeException('The CSV is missing required template columns: '.implode(', ', $missing).'.');
        }
        $allowed = array_merge(EventRegistrationImportService::BASE_HEADERS, ['attendee_ticket_id', 'attendee_variation_name', 'attendee_session_ids'], $questionHeaders);
        $unknown = array_diff($headers, $allowed);
        if ($unknown !== []) {
            throw new RuntimeException('The CSV contains columns that are not in the published form. Download a fresh template and try again.');
        }
    }

    private function payloadForGroup(array $rows, array $headers, $questions): array
    {
        $first = $rows[0]['values'];
        $name = trim((string) ($first['primary_name'] ?? ''));
        $email = mb_strtolower(trim((string) ($first['primary_email'] ?? '')));
        $consent = $this->booleanValue($first['consent_confirmed'] ?? null, 'consent_confirmed');
        if (!$consent) {
            throw ValidationException::withMessages(['consent_confirmed' => 'Organizer attestation must be yes for every registration.']);
        }

        $registrantAnswers = $this->answersFor($first, 'registrant', $questions);
        $attendees = [];
        foreach ($rows as $row) {
            $values = $row['values'];
            if (trim((string) ($values['primary_name'] ?? '')) !== $name
                || mb_strtolower(trim((string) ($values['primary_email'] ?? ''))) !== $email
                || !$this->booleanValue($values['consent_confirmed'] ?? null, 'consent_confirmed')
                || $this->fingerprint($this->answersFor($values, 'registrant', $questions)) !== $this->fingerprint($registrantAnswers)
            ) {
                throw ValidationException::withMessages([
                    'registration_key' => 'Rows sharing a registration key must use the same primary contact, consent and registrant answers.',
                ]);
            }
            $attendees[] = [
                'name' => trim((string) ($values['attendee_name'] ?? '')),
                'email' => mb_strtolower(trim((string) ($values['attendee_email'] ?? ''))),
                'ticket_id' => trim((string) ($values['attendee_ticket_id'] ?? '')) !== ''
                    ? (int) $values['attendee_ticket_id'] : null,
                'variation_name' => trim((string) ($values['attendee_variation_name'] ?? '')),
                'session_ids' => array_values(array_filter(array_map(
                    fn ($id) => (int) trim($id), explode('|', (string) ($values['attendee_session_ids'] ?? ''))
                ))),
                'answers' => $this->answersFor($values, 'attendee', $questions),
            ];
        }

        return [
            'registrant' => ['name' => $name, 'email' => $email],
            'consent' => true,
            'answers' => $registrantAnswers,
            'attendees' => $attendees,
        ];
    }

    private function answersFor(array $row, string $scope, $questions): array
    {
        $answers = [];
        foreach ($questions as $header => $question) {
            if ($question->scope !== $scope || !array_key_exists($header, $row)) {
                continue;
            }
            $value = trim((string) $row[$header]);
            if ($value === '') {
                $answers[$question->question_key] = '';
                continue;
            }
            $answers[$question->question_key] = match ($question->type) {
                'multi_select' => array_values(array_filter(array_map('trim', explode('|', $value)), fn ($item) => $item !== '')),
                'checkbox', 'consent' => $this->booleanValue($value, $header),
                default => $value,
            };
        }

        return $answers;
    }

    private function booleanValue(mixed $value, string $field): bool
    {
        $normalized = strtolower(trim((string) $value));
        if (in_array($normalized, ['1', 'true', 'yes', 'y'], true)) {
            return true;
        }
        if (in_array($normalized, ['0', 'false', 'no', 'n'], true)) {
            return false;
        }
        throw ValidationException::withMessages([$field => 'Use yes or no for checkbox and consent columns.']);
    }

    private function acceptRows(EventRegistrationImport $import, array $rowNumbers, int $submissionId): void
    {
        EventRegistrationImportRow::where('import_id', $import->id)->whereIn('row_number', $rowNumbers)->update([
            'status' => 'accepted', 'submission_id' => $submissionId,
            'error_code' => null, 'error_message' => null, 'updated_at' => now(),
        ]);
    }

    private function rejectRows(EventRegistrationImport $import, array $rowNumbers, string $code, string $message): void
    {
        EventRegistrationImportRow::where('import_id', $import->id)->whereIn('row_number', $rowNumbers)->update([
            'status' => 'rejected', 'submission_id' => null, 'error_code' => $code,
            'error_message' => mb_substr($message, 0, 1000), 'updated_at' => now(),
        ]);
    }

    private function recordRejectedRow(EventRegistrationImport $import, int $rowNumber, string $code, string $message, array $row): void
    {
        EventRegistrationImportRow::updateOrCreate(
            ['import_id' => $import->id, 'row_number' => $rowNumber],
            ['registration_key' => $this->fingerprint(['row' => $rowNumber]), 'status' => 'rejected',
                'submission_id' => null, 'error_code' => $code, 'error_message' => $message,
                'row_fingerprint' => $this->fingerprint($row)]
        );
    }

    private function safeGroupError(Throwable $exception): array
    {
        if ($exception instanceof ValidationException) {
            $message = collect($exception->errors())->flatten()->first();
            return ['validation_failed', (string) ($message ?: 'The registration data is invalid.')];
        }

        return ['processing_failed', 'The registration could not be imported. Review the row and try again.'];
    }

    private function safeFatalError(Throwable $exception): string
    {
        return $exception instanceof RuntimeException
            ? $exception->getMessage()
            : 'The import could not be processed. Retry it or upload a corrected template.';
    }

    private function deleteSource(EventRegistrationImport $import): void
    {
        if (!$import->source_deleted_at) {
            Storage::disk($import->disk)->delete($import->path);
            $import->forceFill(['source_deleted_at' => now()])->save();
        }
    }

    private function fingerprint(array $value): string
    {
        return hash_hmac('sha256', json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), (string) config('app.key'));
    }

    private function blankRow(array $values): bool
    {
        return collect($values)->every(fn ($value) => trim((string) $value) === '');
    }

    public function failed(Throwable $exception): void
    {
        $import = EventRegistrationImport::find($this->importId);
        if ($import && !in_array($import->status, ['completed', 'completed_with_errors'], true)) {
            $import->forceFill(['status' => 'failed', 'last_error' => mb_substr($this->safeFatalError($exception), 0, 2000)])->save();
        }
    }
}
