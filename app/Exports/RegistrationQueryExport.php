<?php

namespace App\Exports;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class RegistrationQueryExport implements FromQuery, WithHeadings, WithMapping
{
    private ?CarbonImmutable $admissionSnapshotAt;

    public function __construct(
        private Builder $submissionQuery,
        private array $questionColumns,
        ?string $admissionSnapshotAt = null
    ) {
        $this->admissionSnapshotAt = $admissionSnapshotAt
            ? CarbonImmutable::parse($admissionSnapshotAt)
            : null;
    }

    public function query(): Builder
    {
        return $this->submissionQuery;
    }

    public function map($submission): array
    {
        $registrantAnswers = $submission->answers->whereNull('attendee_id')->keyBy('question_id');
        $attendees = $submission->attendees->isNotEmpty()
            ? $submission->attendees
            : collect([null]);

        return $attendees->map(function ($attendee) use ($submission, $registrantAnswers): array {
            $attendeeAnswers = $attendee ? $attendee->answers->keyBy('question_id') : collect();
            $sessionSelections = $attendee && $attendee->relationLoaded('sessionSelections')
                ? $attendee->sessionSelections->whereNull('cancelled_at') : collect();
            $admission = $attendee?->bookingTicket;
            $checkedInAt = $admission?->checked_in_at;
            if ($checkedInAt && $this->admissionSnapshotAt && $checkedInAt->gt($this->admissionSnapshotAt)) {
                $checkedInAt = null;
            }
            $revokedAt = $admission?->revoked_at;
            $wasRevoked = $revokedAt && (!$this->admissionSnapshotAt || $revokedAt->lte($this->admissionSnapshotAt));
            $admissionStatus = !$admission ? 'Unissued' : ($wasRevoked ? 'Revoked' : ($checkedInAt ? 'Checked in' : 'Issued'));
            $row = [
                $this->safeCell($submission->public_id),
                $this->safeCell($submission->event?->information?->title ?: 'Event #'.$submission->event_id),
                (int) $submission->form_version,
                $this->safeCell($submission->status),
                optional($submission->submitted_at)->toIso8601String(),
                $this->safeCell($submission->primary_name),
                $this->safeCell($submission->primary_email),
                $attendee ? (int) $attendee->sequence : null,
                $attendee?->ticket_id ? (int) $attendee->ticket_id : null,
                $this->safeCell($attendee?->ticket?->title),
                $this->safeCell($attendee?->variation_name),
                $this->safeCell($attendee?->name),
                $this->safeCell($attendee?->email),
                $this->safeCell($admission?->public_id),
                $admissionStatus,
                $checkedInAt?->toIso8601String(),
                $this->safeCell($checkedInAt ? $admission?->checkedInScannerDevice?->name : null),
                $sessionSelections->pluck('session_id')->implode(' | '),
                $this->safeCell($sessionSelections->pluck('session.title')->filter()->implode(' | ')),
            ];
            foreach ($this->questionColumns as $column) {
                $answers = $column['scope'] === 'registrant' ? $registrantAnswers : $attendeeAnswers;
                $row[] = $this->safeCell($this->answerValue($answers, (int) $column['question_id']));
            }

            return $row;
        })->all();
    }

    public function headings(): array
    {
        return array_merge([
            'Registration Reference', 'Event', 'Form Version', 'Status', 'Submitted At',
            'Primary Contact Name', 'Primary Contact Email', 'Attendee Number', 'Registration Type ID',
            'Registration Type', 'Ticket Option', 'Attendee Name', 'Attendee Email', 'Ticket Unit',
            'Admission Status', 'Checked In At', 'Check-in Gate', 'Session IDs', 'Sessions',
        ], array_map(
            fn (array $column): string => sprintf('[%s] %s', ucfirst($column['scope']), $column['label']),
            $this->questionColumns
        ));
    }

    private function answerValue(Collection $answers, int $questionId): mixed
    {
        $payload = $answers->get($questionId)?->value;
        $value = is_array($payload) && array_key_exists('value', $payload) ? $payload['value'] : $payload;
        if (is_array($value) && isset($value['file_public_id'])) {
            return sprintf(
                '%s (private file %s)',
                (string) ($value['original_name'] ?? 'Uploaded file'),
                (string) $value['file_public_id']
            );
        }
        if (is_array($value)) {
            return implode(' | ', array_map(fn ($item) => (string) $item, $value));
        }
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        return $value;
    }

    private function safeCell(mixed $value): mixed
    {
        if (!is_string($value)) {
            return $value;
        }
        $value = str_replace(["\r\n", "\r", "\n"], ' ', $value);

        return preg_match('/^[=+\-@]/u', $value) === 1 ? "'".$value : $value;
    }
}
