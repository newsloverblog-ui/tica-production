<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Event\Booking;
use App\Models\EventRegistrationAnswer;
use App\Models\EventRegistrationAttendee;
use App\Models\EventRegistrationForm;
use App\Models\EventRegistrationQuestion;
use App\Models\EventRegistrationSubmission;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * A registration form attached to a ticketed event is filled in at checkout,
 * once for every ticket. Answers are checked before payment and saved against
 * the booking when it is created.
 */
class CheckoutRegistrationService
{
  public function __construct(private EventRegistrationService $registrations)
  {
  }

  /** The published forms for these ticketed events, shaped for the checkout page. */
  public function payloads(array $eventIds): Collection
  {
    if ($eventIds === [] || !Schema::hasTable('event_registration_forms')) {
      return collect();
    }

    return EventRegistrationForm::query()
      ->with('questions')
      ->whereIn('event_id', $eventIds)
      ->where('status', 'published')
      ->whereHas('event', fn ($query) => $query->where('registration_mode', 'ticketing'))
      ->get()
      ->mapWithKeys(fn (EventRegistrationForm $form) => [(int) $form->event_id => $this->payload($form)])
      ->filter();
  }

  public function payload(EventRegistrationForm $form): ?array
  {
    $questions = $form->questions->reject(fn (EventRegistrationQuestion $question): bool => $question->type === 'file')->values();
    if ($questions->isEmpty()) {
      return null;
    }

    return [
      'name' => (string) $form->name,
      'intro' => (string) ($form->intro ?? ''),
      'privacy' => (string) ($form->privacy_notice ?? ''),
      'questions' => $questions->map(fn (EventRegistrationQuestion $question) => [
        'key' => (string) $question->question_key,
        'label' => (string) $question->label,
        'type' => (string) $question->type,
        'required' => (bool) $question->is_required,
        'help' => (string) ($question->help_text ?? ''),
        'options' => array_values(array_map('strval', $question->options ?: [])),
        'placeholder' => (string) (($question->settings ?? [])['placeholder'] ?? ''),
        'max' => (int) (($question->settings ?? [])['max_length'] ?? 0) ?: null,
        'ticketId' => $question->ticket_id ? (int) $question->ticket_id : null,
        'condition' => $question->condition ? [
          'key' => (string) ($question->condition['question_key'] ?? ''),
          'op' => (string) ($question->condition['operator'] ?? 'equals'),
          'value' => $question->condition['value'] ?? null,
        ] : null,
      ])->all(),
    ];
  }

  /**
   * Check every ticket holder's answers before payment. $ticketIds lists the
   * ticket type of each ticket in order. Returns the attendees with their
   * checked answers added, or the attendees unchanged when there is no form.
   */
  public function check(Event $event, array $attendees, array $ticketIds, bool $consented): array
  {
    $form = $this->formFor($event);
    if (!$form || !$this->payload($form)) {
      return $attendees;
    }
    $forTicket = fn (int $ticketId) => $form->questions->filter(
      fn (EventRegistrationQuestion $question): bool => !$question->ticket_id || (int) $question->ticket_id === $ticketId
    );
    $asked = collect($ticketIds)->contains(fn ($ticketId) => $forTicket((int) $ticketId)->contains(
      fn (EventRegistrationQuestion $question): bool => !in_array($question->type, ['file', 'terms'], true)
    ));
    if (!$asked) {
      return $attendees;
    }
    if (!$consented) {
      throw ValidationException::withMessages(['consent' => 'Agree to the privacy notice to continue.']);
    }
    foreach (array_values($ticketIds) as $index => $ticketId) {
      $person = is_array($attendees[$index] ?? null) ? $attendees[$index] : [];
      $questions = $forTicket((int) $ticketId);
      $raw = is_array($person['answers'] ?? null) ? $person['answers'] : [];
      try {
        $person['answers'] = $this->registrations->checkoutAnswers($questions, $raw, "attendees.{$index}.answers");
      } catch (ValidationException $exception) {
        $message = collect($exception->errors())->flatten()->first() ?: 'Check the answers for this ticket.';
        throw ValidationException::withMessages(["attendees.{$index}.answers" => 'Ticket ' . ($index + 1) . ': ' . $message]);
      }
      $person['registration'] = ['form_id' => (int) $form->id, 'version' => (int) $form->version, 'ticket_id' => (int) $ticketId];
      $attendees[$index] = $person;
    }

    return $attendees;
  }

  /** Ticket type of each ticket in the order the tickets are issued. */
  public function ticketOrder(array $selectedTickets, int $eventId): array
  {
    $order = [];
    foreach ($selectedTickets as $selection) {
      $quantity = max(0, (int) ($selection['qty'] ?? 0));
      for ($k = 0; $k < $quantity; $k++) {
        $order[] = (int) ($selection['ticket_id'] ?? 0);
      }
    }
    if ($order === []) {
      $ticketId = (int) \App\Models\Event\Ticket::query()->where('event_id', $eventId)->value('id');
      $quantity = \Illuminate\Support\Facades\Session::get('quantity', 1);
      $quantity = is_array($quantity) ? array_sum(array_map('intval', $quantity)) : (int) $quantity;
      $order = array_fill(0, max(1, min(500, $quantity)), $ticketId);
    }

    return $order;
  }

  /**
   * Save the answers against a new booking. A problem here is reported but
   * never stops the booking: the buyer has already been charged.
   */
  public function record(Booking $booking, $attendees, $units = null): void
  {
    try {
      $people = collect(is_array($attendees) ? $attendees : [])->values();
      if (!$people->contains(fn ($person) => is_array($person) && isset($person['registration']))) {
        return;
      }
      if (EventRegistrationSubmission::query()->where('booking_id', $booking->id)->exists()) {
        return;
      }
      $formId = (int) data_get($people->first(fn ($person) => isset($person['registration'])), 'registration.form_id');
      $form = EventRegistrationForm::with('questions')->whereKey($formId)->where('event_id', $booking->event_id)->first();
      if (!$form) {
        return;
      }
      $questions = $form->questions->keyBy('question_key');
      $unitList = $units ? collect($units)->sortBy('sequence')->values() : collect();
      $buyerName = trim($booking->fname . ' ' . $booking->lname);
      $buyerEmail = mb_strtolower(trim((string) $booking->email));

      $submission = EventRegistrationSubmission::create([
        'public_id' => (string) Str::ulid(),
        'form_id' => $form->id,
        'event_id' => $booking->event_id,
        'organizer_id' => $booking->organizer_id,
        'customer_id' => $booking->customer_id,
        'booking_id' => $booking->id,
        'form_version' => (int) data_get($people->first(), 'registration.version', $form->version),
        'status' => $unitList->isNotEmpty() ? 'ticketed' : 'submitted',
        'primary_name' => $buyerName !== '' ? $buyerName : 'Ticket buyer',
        'primary_email' => $buyerEmail,
        'primary_email_hash' => hash_hmac('sha256', $buyerEmail, (string) config('app.key')),
        'attendee_count' => $people->count(),
        'consent_version' => $form->consent_version,
        'consented_at' => now(),
        'submitted_at' => now(),
      ]);

      foreach ($people as $index => $person) {
        $person = is_array($person) ? $person : [];
        $name = trim((string) ($person['name'] ?? '')) ?: ($index === 0 ? $submission->primary_name : 'Ticket ' . ($index + 1));
        $email = mb_strtolower(trim((string) ($person['email'] ?? '')));
        $email = filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
        $unit = $unitList->get($index);
        $attendee = EventRegistrationAttendee::create(array_filter([
          'public_id' => (string) Str::ulid(),
          'submission_id' => $submission->id,
          'sequence' => $index + 1,
          'name' => mb_substr($name, 0, 150),
          'email' => $email,
          'email_hash' => $email ? hash_hmac('sha256', $email, (string) config('app.key')) : null,
          'ticket_id' => data_get($person, 'registration.ticket_id') ?: null,
          'booking_ticket_id' => $unit?->id,
          'status' => $unit ? 'ticketed' : 'registered',
        ], fn ($value) => $value !== null));

        foreach ((array) ($person['answers'] ?? []) as $key => $value) {
          $question = $questions->get((string) $key);
          if (!$question) {
            continue;
          }
          EventRegistrationAnswer::create([
            'submission_id' => $submission->id,
            'attendee_id' => $attendee->id,
            'question_id' => $question->id,
            'question_key' => $question->question_key,
            'question_label' => $question->label,
            'question_type' => $question->type,
            'value' => ['value' => $value],
          ]);
        }
      }

      AuditLogger::record('event.registration_checkout_saved', $submission, [], [
        'attendee_count' => $submission->attendee_count,
      ], ['event_id' => $booking->event_id, 'booking_id' => $booking->id]);
    } catch (Throwable $exception) {
      report($exception);
    }
  }

  private function formFor(Event $event): ?EventRegistrationForm
  {
    if (($event->registration_mode ?: 'ticketing') !== 'ticketing' || !Schema::hasTable('event_registration_forms')) {
      return null;
    }

    return EventRegistrationForm::with('questions')
      ->where('event_id', $event->id)
      ->where('status', 'published')
      ->first();
  }
}
