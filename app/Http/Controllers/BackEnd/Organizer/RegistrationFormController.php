<?php

namespace App\Http\Controllers\BackEnd\Organizer;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventRegistrationForm;
use App\Models\EventRegistrationQuestion;
use App\Services\RegistrationFormLibrary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Registration forms library: one place to build forms and choose which
 * events use them. Field editing reuses the existing question endpoints.
 */
class RegistrationFormController extends Controller
{
  public function index(Request $request, RegistrationFormLibrary $library)
  {
    $organizerId = $this->organizerId();
    $forms = $library->forms($organizerId);
    $standalone = $library->standalone($organizerId);
    $events = $this->events($organizerId);

    return view('organizer.forms.index', compact('forms', 'standalone', 'events'));
  }

  public function store(Request $request, RegistrationFormLibrary $library)
  {
    $data = $request->validate([
      'name' => ['required', 'string', 'max:150'],
      'approval_mode' => ['nullable', 'in:automatic,manual'],
      'event_id' => ['nullable', 'integer'],
    ]);
    $form = $library->create($this->organizerId(), $data['name'], $data['approval_mode'] ?? 'manual');
    $query = !empty($data['event_id']) ? ['event' => (int) $data['event_id']] : [];

    return redirect()->route('organizer.forms.show', ['form' => $form] + $query)
      ->with('success', 'Form created. Add your fields, then choose the events that use it.');
  }

  public function show(Request $request, EventRegistrationForm $form)
  {
    $organizerId = $this->owned($form);
    if (!$form->isLibrary()) {
      // Event forms are managed on the event's registration page.
      return redirect()->route('organizer.event.registrations.index', ['event_id' => $form->event_id]);
    }
    $form->load('questions');
    $form->load(['copies' => fn ($q) => $q->whereNull('archived_at')->with('event.information')->withCount('submissions')]);
    $editing = null;
    if ($request->filled('field')) {
      $editing = EventRegistrationQuestion::where('form_id', $form->id)
        ->where('public_id', (string) $request->query('field'))->firstOrFail();
    }
    $events = $this->events($organizerId);
    $usedOn = $form->copies->pluck('event_id')->map(fn ($id) => (int) $id)->all();
    $preselect = (int) $request->query('event', 0);

    return view('organizer.forms.show', compact('form', 'editing', 'events', 'usedOn', 'preselect'));
  }

  public function settings(Request $request, EventRegistrationForm $form, RegistrationFormLibrary $library)
  {
    $data = $request->validate([
      'name' => ['required', 'string', 'max:150'],
      'approval_mode' => ['required', 'in:automatic,manual'],
      'intro' => ['nullable', 'string', 'max:5000'],
      'privacy_notice' => ['required', 'string', 'max:5000'],
    ]);
    $library->updateSettings($form, $this->owned($form), $data);
    if ($request->expectsJson()) {
      return response()->json(['message' => 'Form settings saved.', 'name' => $data['name']]);
    }

    return back()->with('success', 'Form settings saved.');
  }

  public function useOnEvents(Request $request, EventRegistrationForm $form, RegistrationFormLibrary $library)
  {
    $data = $request->validate([
      'events' => ['required', 'array', 'min:1', 'max:50'],
      'events.*' => ['integer'],
      'publish' => ['nullable', 'boolean'],
      'confirm_switch' => ['accepted'],
    ], [
      'events.required' => 'Choose at least one event.',
      'confirm_switch.accepted' => 'Confirm that these events will take registrations instead of selling tickets.',
    ]);

    return $this->finish($library->useOnEvents($form, $this->owned($form), $data['events'], $request->boolean('publish')));
  }

  /** Re-apply the library form to every event already using it. */
  public function pushChanges(Request $request, EventRegistrationForm $form, RegistrationFormLibrary $library)
  {
    $organizerId = $this->owned($form);
    $form->load(['copies' => fn ($q) => $q->whereNull('archived_at')]);
    $events = $form->copies->pluck('event_id')->filter()->all();
    if ($events === []) {
      return back()->with('warning', 'No events use this form yet.');
    }
    $publish = $form->copies->every(fn ($copy) => $copy->status === 'published');

    return $this->finish($library->useOnEvents($form, $organizerId, $events, $publish));
  }

  public function duplicate(EventRegistrationForm $form, RegistrationFormLibrary $library)
  {
    $copy = $library->duplicate($form, $this->owned($form));

    return redirect()->route('organizer.forms.show', $copy)->with('success', 'Form copied. Rename it and adjust the fields.');
  }

  public function adopt(EventRegistrationForm $form, RegistrationFormLibrary $library)
  {
    $kept = $library->adopt($form, $this->owned($form));

    return redirect()->route('organizer.forms.show', $kept)->with('success', 'Saved to your forms. You can now use it on other events too.');
  }

  public function destroy(EventRegistrationForm $form, RegistrationFormLibrary $library)
  {
    $library->delete($form, $this->owned($form));

    return redirect()->route('organizer.forms.index')->with('success', 'Form deleted. Events that used it keep their own copy.');
  }

  public function stop(EventRegistrationForm $form, RegistrationFormLibrary $library)
  {
    $library->stopOnEvent($form, $this->owned($form));

    return back()->with('success', 'Registration stopped. The event is back to selling tickets; past registrations are kept.');
  }

  private function finish(array $results)
  {
    $ok = collect($results)->where('ok', true);
    $failed = collect($results)->where('ok', false);
    $lines = collect($results)->map(fn ($r) => $r['event'] . ': ' . $r['message'])->implode(' · ');
    $level = $failed->isEmpty() ? 'success' : 'warning';

    return back()->with($level, $lines ?: 'No matching events.');
  }

  private function events(int $organizerId)
  {
    return Event::with(['information', 'registrationForm' => fn ($q) => $q->withCount('submissions')])->where('organizer_id', $organizerId)->latest('id')->get();
  }

  private function organizerId(): int
  {
    return (int) Auth::guard('organizer')->id();
  }

  private function owned(EventRegistrationForm $form): int
  {
    $id = $this->organizerId();
    abort_unless((int) $form->organizer_id === $id, 404);

    return $id;
  }
}
