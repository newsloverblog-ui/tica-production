<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventRegistrationForm;
use App\Models\EventRegistrationQuestion;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * The organiser's form library: build a registration form once, then use it
 * on one or many events. Each event gets its own copy (its submissions,
 * sponsors, badges and embed stay per event); the copy remembers where it
 * came from, so later edits can be pushed to every event that uses it.
 */
class RegistrationFormLibrary
{
  public const DEFAULT_PRIVACY = 'We use the details you give here only to manage your registration for this event: confirming your place, checking you in and contacting you about the event. The organiser keeps them only as long as needed for that, and you can ask the organiser to correct or delete them.';

  public function __construct(private EventRegistrationService $registrations)
  {
  }

  /** Library forms with the events using each one. */
  public function forms(int $organizerId): Collection
  {
    return EventRegistrationForm::query()
      ->whereNull('event_id')->where('organizer_id', $organizerId)
      ->withCount('questions')
      ->with(['copies' => fn ($q) => $q->whereNull('archived_at')->with('event.information')->withCount('submissions')])
      ->latest('updated_at')->get();
  }

  /** Event forms that were built on the event page and aren't in the library yet. */
  public function standalone(int $organizerId): Collection
  {
    return EventRegistrationForm::query()
      ->whereNotNull('event_id')->whereNull('source_form_id')->whereNull('archived_at')
      ->where('organizer_id', $organizerId)
      ->withCount(['questions', 'submissions'])->with('event.information')
      ->latest('updated_at')->get();
  }

  public function create(int $organizerId, string $name, string $approvalMode = 'manual'): EventRegistrationForm
  {
    $name = $this->name($name);
    $form = EventRegistrationForm::create([
      'public_id' => (string) Str::ulid(),
      'event_id' => null,
      'organizer_id' => $organizerId,
      'name' => $name,
      'status' => 'draft',
      'approval_mode' => in_array($approvalMode, ['automatic', 'manual'], true) ? $approvalMode : 'manual',
      'version' => 1,
      'privacy_notice' => self::DEFAULT_PRIVACY,
      'consent_version' => (string) config('registration.consent_version'),
    ]);
    AuditLogger::record('registration_library.form_created', $form, [], ['name' => $form->name]);

    return $form;
  }

  public function updateSettings(EventRegistrationForm $form, int $organizerId, array $data): EventRegistrationForm
  {
    $this->assertLibrary($form, $organizerId);
    $privacy = trim((string) ($data['privacy_notice'] ?? ''));
    if ($privacy === '' || mb_strlen($privacy) > 5000) {
      throw ValidationException::withMessages(['privacy_notice' => 'Add a privacy notice (up to 5,000 characters). People see it before they register.']);
    }
    $intro = trim((string) ($data['intro'] ?? ''));
    if (mb_strlen($intro) > 5000) {
      throw ValidationException::withMessages(['intro' => 'The introduction can be up to 5,000 characters.']);
    }
    $before = $form->only(['name', 'approval_mode']);
    $form->forceFill([
      'name' => $this->name((string) ($data['name'] ?? '')),
      'approval_mode' => in_array($data['approval_mode'] ?? null, ['automatic', 'manual'], true) ? $data['approval_mode'] : 'manual',
      'intro' => $intro !== '' ? $intro : null,
      'privacy_notice' => $privacy,
    ])->save();
    AuditLogger::record('registration_library.form_updated', $form, $before, $form->only(['name', 'approval_mode']));

    return $form;
  }

  public function duplicate(EventRegistrationForm $form, int $organizerId): EventRegistrationForm
  {
    $this->assertOwner($form, $organizerId);

    return DB::transaction(function () use ($form, $organizerId) {
      $copy = $this->create($organizerId, mb_substr('Copy of ' . $form->name, 0, 150), $form->approval_mode);
      $copy->forceFill(['intro' => $form->intro, 'privacy_notice' => $form->privacy_notice ?: self::DEFAULT_PRIVACY])->save();
      $this->copyQuestions($form, $copy, false);

      return $copy;
    });
  }

  /** Keep an event's own form in the library, and link the event to it. */
  public function adopt(EventRegistrationForm $eventForm, int $organizerId): EventRegistrationForm
  {
    $this->assertOwner($eventForm, $organizerId);
    if ($eventForm->isLibrary()) {
      return $eventForm;
    }

    return DB::transaction(function () use ($eventForm, $organizerId) {
      $library = $this->duplicate($eventForm, $organizerId);
      $library->forceFill(['name' => $eventForm->name])->save();
      $eventForm->forceFill(['source_form_id' => $library->id])->save();

      return $library;
    });
  }

  public function delete(EventRegistrationForm $form, int $organizerId): void
  {
    $this->assertLibrary($form, $organizerId);
    DB::transaction(function () use ($form) {
      // Event copies keep working on their own; they just stop pointing here.
      EventRegistrationForm::where('source_form_id', $form->id)->update(['source_form_id' => null]);
      EventRegistrationQuestion::where('form_id', $form->id)->delete();
      AuditLogger::record('registration_library.form_deleted', $form, ['name' => $form->name], []);
      $form->delete();
    });
  }

  /**
   * Put this form on each chosen event. The event switches from ticket sales to
   * registration. Returns one plain line per event saying what happened.
   *
   * @param  array<int, int>  $eventIds
   * @return array<int, array{event:string, ok:bool, message:string}>
   */
  public function useOnEvents(EventRegistrationForm $library, int $organizerId, array $eventIds, bool $publish): array
  {
    $this->assertLibrary($library, $organizerId);
    $library->load('questions');
    $events = Event::with('information')->where('organizer_id', $organizerId)->whereIn('id', array_map('intval', $eventIds))->get();
    $results = [];
    foreach ($events as $event) {
      $title = (string) (optional($event->information)->title ?: 'Event #' . $event->id);
      try {
        DB::transaction(function () use ($library, $organizerId, $event, $publish) {
          $eventForm = $this->registrations->saveForm($event, $organizerId, [
            'name' => $library->name,
            'approval_mode' => $library->approval_mode,
            'intro' => $library->intro,
            'privacy_notice' => $library->privacy_notice ?: self::DEFAULT_PRIVACY,
          ]);
          $this->copyQuestions($library, $eventForm, true);
          $eventForm->forceFill(['source_form_id' => $library->id] + ($eventForm->status === 'archived' ? ['status' => 'draft'] : []))->save();
          if ($publish) {
            $this->registrations->publish($eventForm, $organizerId);
          }
        }, 3);
        $results[] = ['event' => $title, 'ok' => true, 'message' => $publish ? 'Live: people can register now.' : 'Added as a draft. Publish it from the event when ready.'];
      } catch (ValidationException $e) {
        $results[] = ['event' => $title, 'ok' => false, 'message' => collect($e->errors())->flatten()->first() ?: 'Could not be added.'];
      } catch (Throwable $e) {
        report($e);
        $results[] = ['event' => $title, 'ok' => false, 'message' => 'Could not be added. Please try again.'];
      }
    }
    AuditLogger::record('registration_library.used_on_events', $library, [], ['events' => $events->pluck('id')->all(), 'publish' => $publish]);

    return $results;
  }

  /** Stop taking registrations on an event: it goes back to ticket sales. Submissions are kept. */
  public function stopOnEvent(EventRegistrationForm $eventForm, int $organizerId): void
  {
    $this->assertOwner($eventForm, $organizerId);
    if ($eventForm->isLibrary()) {
      throw ValidationException::withMessages(['form' => 'This form is not on an event.']);
    }
    DB::transaction(function () use ($eventForm) {
      $before = $eventForm->only(['status']);
      $eventForm->forceFill(['status' => 'archived', 'archived_at' => now(), 'published_at' => null])->save();
      Event::whereKey($eventForm->event_id)->update(['registration_mode' => 'ticketing']);
      AuditLogger::record('registration_library.stopped_on_event', $eventForm, $before, ['status' => 'archived'], ['event_id' => $eventForm->event_id]);
    });
  }

  /** Copy fields from one form to another, replacing what the target had. */
  private function copyQuestions(EventRegistrationForm $from, EventRegistrationForm $to, bool $toEvent): void
  {
    $from->load('questions');
    if ($from->questions->count() > (int) config('registration.max_questions_per_form')) {
      throw ValidationException::withMessages(['form' => 'This form has more fields than an event form allows.']);
    }
    EventRegistrationQuestion::where('form_id', $to->id)->delete();
    $hasSettings = Schema::hasColumn('event_registration_questions', 'settings');
    foreach ($from->questions->values() as $index => $q) {
      $attributes = [
        'public_id' => (string) Str::ulid(), 'form_id' => $to->id,
        'question_key' => $q->question_key, 'label' => $q->label, 'help_text' => $q->help_text,
        'type' => $q->type, 'scope' => $q->scope, 'is_required' => (bool) $q->is_required,
        'options' => $q->options, 'condition' => $q->condition, 'position' => $index + 1,
      ];
      if ($hasSettings) {
        $attributes['settings'] = $q->settings;
      }
      // Ticket-specific fields only make sense on the event they came from.
      if (!$toEvent || (int) $from->event_id === (int) $to->event_id) {
        $attributes['ticket_id'] = $q->ticket_id ?? null;
      }
      EventRegistrationQuestion::create($attributes);
    }
  }

  private function name(string $name): string
  {
    $name = trim($name);
    if ($name === '' || mb_strlen($name) > 150) {
      throw ValidationException::withMessages(['name' => 'Give the form a name of up to 150 characters.']);
    }

    return $name;
  }

  private function assertOwner(EventRegistrationForm $form, int $organizerId): void
  {
    if ((int) $form->organizer_id !== $organizerId) {
      throw new AuthorizationException('You cannot manage another organiser’s form.');
    }
  }

  private function assertLibrary(EventRegistrationForm $form, int $organizerId): void
  {
    $this->assertOwner($form, $organizerId);
    if (!$form->isLibrary()) {
      throw ValidationException::withMessages(['form' => 'Edit this form from its event, or keep it in your forms first.']);
    }
  }
}
