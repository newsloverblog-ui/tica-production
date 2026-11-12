<?php

namespace App\Jobs;

use App\Mail\EventRegistrationStatusMail;
use App\Models\EventRegistrationNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

class SendEventRegistrationNotification implements ShouldQueue, ShouldBeEncrypted, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public int $uniqueFor = 3600;

    public function __construct(public int $notificationId)
    {
        $this->onQueue('default');
    }

    public function uniqueId(): string
    {
        return 'event-registration-notification:'.$this->notificationId;
    }

    public function backoff(): array
    {
        return [60, 300, 900, 1800];
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping($this->uniqueId()))->expireAfter(900)];
    }

    public function handle(): void
    {
        $notification = EventRegistrationNotification::with([
            'submission.form',
            'submission.event.information',
            'submission.answers',
            'submission.attendees.answers',
        ])
            ->findOrFail($this->notificationId);
        if (in_array($notification->status, ['sent', 'suppressed'], true)) {
            return;
        }

        $submission = $notification->submission;
        if (!$submission || !$this->isStillRelevant($notification->kind, $submission->status)) {
            $notification->forceFill([
                'status' => 'suppressed',
                'processing_at' => null,
                'last_error' => null,
            ])->save();

            return;
        }

        $notification->forceFill([
            'status' => 'processing',
            'processing_at' => now(),
            'attempts' => (int) $notification->attempts + 1,
            'last_error' => null,
            'alerted_at' => null,
        ])->save();

        try {
            if (!filter_var($submission->primary_email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('The registration does not have a deliverable email address.');
            }
            [$label, $message] = $this->copyFor($notification->kind);
            [$answerSummary, $omittedAnswerCount] = in_array($notification->kind, ['submitted', 'approved'], true)
                ? $this->answerSummary($submission)
                : [[], 0];
            $eventTitle = (string) ($submission->event?->information?->title
                ?: $submission->form?->name
                ?: 'Event registration');
            Mail::to($submission->primary_email)->send(new EventRegistrationStatusMail(
                (string) $submission->primary_name,
                $eventTitle,
                (string) $submission->public_id,
                $label,
                $message,
                $notification->kind === 'rejected' ? $submission->review_note : null,
                $answerSummary,
                $omittedAnswerCount,
            ));
            $notification->forceFill([
                'status' => 'sent',
                'sent_at' => now(),
                'processing_at' => null,
                'last_error' => null,
            ])->save();
        } catch (Throwable $exception) {
            $notification->forceFill([
                'status' => 'failed',
                'processing_at' => null,
                'last_error' => mb_substr($exception->getMessage(), 0, 2000),
            ])->save();
            throw $exception;
        }
    }

    private function isStillRelevant(string $kind, string $submissionStatus): bool
    {
        return match ($kind) {
            'submitted' => $submissionStatus === 'submitted',
            'approved' => $submissionStatus === 'approved',
            'rejected' => $submissionStatus === 'rejected',
            'cancelled' => $submissionStatus === 'cancelled',
            default => false,
        };
    }

    private function copyFor(string $kind): array
    {
        return match ($kind) {
            'submitted' => ['Registration received', 'We received your registration. It is awaiting organizer review.'],
            'approved' => ['Registration approved', 'Your registration has been approved.'],
            'rejected' => ['Registration update', 'Your registration was not approved.'],
            'cancelled' => ['Event cancelled', 'The event connected to this registration has been cancelled.'],
            default => throw new RuntimeException('Unsupported registration notification kind.'),
        };
    }

    private function answerSummary($submission): array
    {
        $maximumAnswers = 500;
        $used = 0;
        $omitted = 0;
        $sections = [];
        $append = function ($answers) use (&$used, &$omitted, $maximumAnswers): array {
            $rows = [];
            foreach ($answers->sortBy('question_id') as $answer) {
                if ($used >= $maximumAnswers) {
                    $omitted++;
                    continue;
                }
                $rows[] = [
                    'label' => (string) $answer->question_label,
                    'value' => $this->displayAnswer($answer->value['value'] ?? null),
                ];
                $used++;
            }

            return $rows;
        };

        $registrantAnswers = $submission->answers->whereNull('attendee_id');
        if ($registrantAnswers->isNotEmpty()) {
            $sections[] = [
                'heading' => 'Registration details',
                'answers' => $append($registrantAnswers),
            ];
        }

        foreach ($submission->attendees->sortBy('sequence') as $attendee) {
            $answers = $append($attendee->answers);
            $sections[] = [
                'heading' => 'Attendee '.(int) $attendee->sequence.': '.(string) $attendee->name,
                'answers' => array_merge([
                    ['label' => 'Name', 'value' => (string) $attendee->name],
                    ['label' => 'Email', 'value' => (string) ($attendee->email ?: 'Not provided')],
                ], $answers),
            ];
        }

        return [array_values(array_filter($sections, fn (array $section): bool => $section['answers'] !== [])), $omitted];
    }

    private function displayAnswer(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }
        if (is_array($value) && isset($value['file_public_id'])) {
            return (string) ($value['original_name'] ?? 'Private uploaded file');
        }
        if (is_array($value)) {
            $value = implode(' | ', array_map(static fn ($item): string => (string) $item, $value));
        }

        $value = preg_replace('/\s+/u', ' ', trim((string) $value)) ?: 'Not provided';

        return mb_strlen($value) > 1000 ? mb_substr($value, 0, 997).'...' : $value;
    }
}
