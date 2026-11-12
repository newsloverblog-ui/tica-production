<?php

namespace App\Http\Controllers\BackEnd\Organizer;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventRegistrationForm;
use App\Models\EventRegistrationFile;
use App\Models\EventRegistrationAllocation;
use App\Models\EventRegistrationFormTemplate;
use App\Models\EventRegistrationImport;
use App\Models\EventRegistrationImportRow;
use App\Models\EventRegistrationQuestion;
use App\Models\EventRegistrationSubmission;
use App\Models\EventRegistrationSession;
use App\Models\EventRegistrationInvoice;
use App\Services\EventRegistrationService;
use App\Services\EventRegistrationAllocationService;
use App\Services\EventRegistrationImportService;
use App\Services\EventRegistrationSessionService;
use App\Services\EventRegistrationInvoiceService;
use App\Services\EventRegistrationInvoiceDocumentService;
use App\Services\EventRegistrationBadgeService;
use App\Services\EventRegistrationEmbedService;
use App\Services\AuditLogger;
use App\Services\DataExportService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class EventRegistrationController extends Controller
{
    public function index(Request $request)
    {
        $organizerId = (int) Auth::guard('organizer')->id();
        $events = Event::with('information')->where('organizer_id', $organizerId)->latest('id')->get();
        $eventId = (int) ($request->integer('event_id') ?: ($events->first()?->id ?? 0));
        $event = $eventId
            ? Event::with([
                'information', 'tickets', 'registrationForm.questions.ticket', 'registrationForm.badgeSetting',
                'registrationForm.embedSetting',
                'registrationForm.sessions' => fn ($query) => $query->with('ticket')->withCount([
                    'selections as selected_count' => fn ($selection) => $selection->whereNull('cancelled_at'),
                ]),
            ])
                ->where('organizer_id', $organizerId)->findOrFail($eventId)
            : null;
        $form = $event?->registrationForm;
        $editingQuestion = null;
        if ($form && $request->filled('edit_question')) {
            $editingQuestion = EventRegistrationQuestion::query()
                ->where('form_id', $form->id)
                ->where('public_id', (string) $request->input('edit_question'))
                ->firstOrFail();
        }
        $ticketedAttendeeCount = $form
            ? (int) EventRegistrationSubmission::where('form_id', $form->id)
                ->where('organizer_id', $organizerId)->where('status', 'ticketed')->sum('attendee_count')
            : 0;
        $submissions = $form
            ? EventRegistrationSubmission::with('invoice', 'files')->withCount('attendees')
                ->where('form_id', $form->id)
                ->where('organizer_id', $organizerId)
                ->latest('submitted_at')
                ->paginate(25)
                ->withQueryString()
            : null;
        $imports = $form
            ? EventRegistrationImport::where('form_id', $form->id)
                ->where('organizer_id', $organizerId)
                ->latest('id')->limit(10)->get()
            : collect();
        $latestImport = $imports->first();
        $rejectedImportRows = $latestImport
            ? EventRegistrationImportRow::where('import_id', $latestImport->id)
                ->where('status', 'rejected')->orderBy('row_number')->limit(25)->get()
            : collect();
        $templates = EventRegistrationFormTemplate::where('organizer_id', $organizerId)
            ->orderBy('name')->get();
        $allocations = $form
            ? EventRegistrationAllocation::withCount([
                'claims as assigned_count' => fn ($query) => $query->whereIn('status', ['pending', 'completed']),
                'claims as completed_count' => fn ($query) => $query->where('status', 'completed'),
            ])->where('form_id', $form->id)->where('organizer_id', $organizerId)->latest('id')->get()
            : collect();

        return view('organizer.event.registration.index', compact(
            'events', 'event', 'form', 'submissions', 'imports', 'latestImport', 'rejectedImportRows', 'templates',
            'allocations', 'ticketedAttendeeCount', 'editingQuestion'
        ));
    }

    public function saveForm(Request $request, EventRegistrationService $registrations)
    {
        $validated = $request->validate([
            'event_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:150'],
            'approval_mode' => ['required', 'in:automatic,manual'],
            'intro' => ['nullable', 'string', 'max:5000'],
            'privacy_notice' => ['required', 'string', 'max:5000'],
        ]);
        $event = Event::findOrFail((int) $validated['event_id']);
        $registrations->saveForm($event, (int) Auth::guard('organizer')->id(), $validated);

        return redirect()->route('organizer.event.registrations.index', ['event_id' => $event->id])
            ->with('success', 'Registration form saved as a draft. Publish it when it is ready.');
    }

    public function addQuestion(Request $request, EventRegistrationForm $form, EventRegistrationService $registrations)
    {
        $validated = $request->validate([
            'label' => ['required', 'string', 'max:191'],
            'question_key' => ['nullable', 'string', 'max:80'],
            'help_text' => ['nullable', 'string', 'max:5000'],
            'placeholder' => ['nullable', 'string', 'max:191'],
            'min_length' => ['nullable', 'integer', 'min:0', 'max:5000'],
            'max_length' => ['nullable', 'integer', 'min:1', 'max:5000'],
            'type' => ['required', 'in:'.implode(',', EventRegistrationService::QUESTION_TYPES)],
            'scope' => ['required', 'in:registrant,attendee'],
            'ticket_id' => ['nullable', 'integer'],
            'is_required' => ['nullable', 'boolean'],
            'options' => ['nullable', 'string', 'max:5000'],
            'condition_question_key' => ['nullable', 'string', 'max:80'],
            'condition_operator' => ['nullable', 'in:equals,not_equals,not_empty'],
            'condition_value' => ['nullable', 'string', 'max:500'],
        ]);
        $registrations->addQuestion($form, (int) Auth::guard('organizer')->id(), $validated);

        return back()->with('success', 'Question added. Republish the form after all changes are complete.');
    }

    public function deleteQuestion(EventRegistrationQuestion $question, EventRegistrationService $registrations)
    {
        $registrations->deleteQuestion($question, (int) Auth::guard('organizer')->id());

        return back()->with('success', 'Question removed.');
    }

    public function updateQuestion(Request $request, EventRegistrationQuestion $question, EventRegistrationService $registrations)
    {
        $validated = $request->validate([
            'label' => ['required', 'string', 'max:191'],
            'question_key' => ['required', 'string', 'max:80'],
            'help_text' => ['nullable', 'string', 'max:5000'],
            'placeholder' => ['nullable', 'string', 'max:191'],
            'min_length' => ['nullable', 'integer', 'min:0', 'max:5000'],
            'max_length' => ['nullable', 'integer', 'min:1', 'max:5000'],
            'type' => ['required', 'in:'.implode(',', EventRegistrationService::QUESTION_TYPES)],
            'scope' => ['required', 'in:registrant,attendee'],
            'ticket_id' => ['nullable', 'integer'],
            'is_required' => ['nullable', 'boolean'],
            'options' => ['nullable', 'string', 'max:5000'],
            'condition_question_key' => ['nullable', 'string', 'max:80'],
            'condition_operator' => ['nullable', 'in:equals,not_equals,not_empty'],
            'condition_value' => ['nullable', 'string', 'max:500'],
        ]);
        $updated = $registrations->updateQuestion(
            $question,
            (int) Auth::guard('organizer')->id(),
            $validated
        );

        return redirect()->route('organizer.event.registrations.index', ['event_id' => $updated->form->event_id])
            ->with('success', 'Question updated. Republish the form after reviewing the change.');
    }

    public function reorderQuestions(Request $request, EventRegistrationForm $form, EventRegistrationService $registrations)
    {
        $validated = $request->validate([
            'ordered_questions' => ['required', 'array', 'min:1', 'max:'.config('registration.max_questions_per_form')],
            'ordered_questions.*' => ['required', 'string', 'max:64', 'distinct'],
        ]);
        $registrations->reorderQuestions(
            $form,
            (int) Auth::guard('organizer')->id(),
            $validated['ordered_questions']
        );

        return back()->with('success', 'Question order saved. Republish the form after reviewing the new order.');
    }

    public function moveQuestion(Request $request, EventRegistrationQuestion $question, EventRegistrationService $registrations)
    {
        $validated = $request->validate(['direction' => ['required', 'in:up,down']]);
        $registrations->moveQuestion(
            $question,
            (int) Auth::guard('organizer')->id(),
            $validated['direction']
        );

        return back()->with('success', 'Question moved.');
    }

    public function publish(EventRegistrationForm $form, EventRegistrationService $registrations)
    {
        $registrations->publish($form, (int) Auth::guard('organizer')->id());

        return back()->with('success', 'Registration form published.');
    }

    public function decide(Request $request, EventRegistrationSubmission $submission, EventRegistrationService $registrations)
    {
        $validated = $request->validate([
            'decision' => ['required', 'in:approved,rejected'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);
        $registrations->decide(
            $submission,
            (int) Auth::guard('organizer')->id(),
            $validated['decision'],
            $validated['note'] ?? null
        );

        return back()->with('success', 'Registration decision saved.');
    }

    public function downloadFile(EventRegistrationFile $file)
    {
        $organizerId = (int) Auth::guard('organizer')->id();
        $file->loadMissing('submission');
        abort_unless($file->submission && (int) $file->submission->organizer_id === $organizerId, 403);

        $diskConfig = (array) config('filesystems.disks.'.$file->disk, []);
        abort_if($file->disk === 'public' || $diskConfig === [] || ($diskConfig['visibility'] ?? 'private') === 'public', 404);
        abort_unless(Storage::disk($file->disk)->exists($file->path), 404);

        AuditLogger::record('event.registration_file.downloaded', $file, [], [
            'mime_type' => $file->mime_type,
            'size' => $file->size,
        ], [
            'event_id' => $file->submission->event_id,
            'submission_id' => $file->submission_id,
            'organizer_id' => $organizerId,
        ]);

        return Storage::disk($file->disk)->download($file->path, $file->original_name, [
            'Content-Type' => $file->mime_type,
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function export(Request $request, DataExportService $exports)
    {
        $validated = $request->validate([
            'event_id' => ['required', 'integer'],
            'status' => ['nullable', 'in:submitted,approved,ticketed,rejected,cancelled'],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
            'fileType' => ['required', 'in:csv,xlsx'],
            'privacy_confirmed' => ['accepted'],
        ]);
        try {
            $exports->queueRegistrations(
                (int) Auth::guard('organizer')->id(),
                (int) $validated['event_id'],
                $validated,
                $validated['fileType'],
                true,
            );
        } catch (DomainException $exception) {
            return back()->with('warning', $exception->getMessage());
        }

        return redirect()->route('organizer.data_exports.index')
            ->with('success', 'Registration export queued. The private download will appear when processing finishes.');
    }

    public function import(Request $request, EventRegistrationImportService $imports)
    {
        $validated = $request->validate([
            'event_id' => ['required', 'integer'],
            'file' => [
                'required', 'file', 'mimes:csv,txt',
                'max:'.(int) config('registration.import_max_file_kb', 2048),
            ],
            'contact_attested' => ['accepted'],
        ]);
        $event = Event::findOrFail((int) $validated['event_id']);
        try {
            $imports->queue(
                $event,
                (int) Auth::guard('organizer')->id(),
                $request->file('file'),
                true,
            );
        } catch (DomainException $exception) {
            return back()->with('warning', $exception->getMessage());
        }

        return redirect()->route('organizer.event.registrations.index', ['event_id' => $event->id])
            ->with('success', 'Registration import queued. Row-level results will appear here after processing.');
    }

    public function template(EventRegistrationForm $form, EventRegistrationImportService $imports)
    {
        $headers = $imports->headers($form->loadMissing('questions', 'sessions'), (int) Auth::guard('organizer')->id());

        return response()->streamDownload(function () use ($headers): void {
            $stream = fopen('php://output', 'wb');
            fputcsv($stream, $headers, ',', '"', '');
            fclose($stream);
        }, 'registration-import-template-'.$form->public_id.'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function saveTemplate(Request $request, EventRegistrationForm $form, EventRegistrationService $registrations)
    {
        $validated = $request->validate([
            'template_name' => ['required', 'string', 'max:150'],
            'template_description' => ['nullable', 'string', 'max:1000'],
        ]);
        $registrations->saveTemplate($form, (int) Auth::guard('organizer')->id(),
            $validated['template_name'], $validated['template_description'] ?? null);
        return back()->with('success', 'Reusable registration template saved. Saving the same name updates its version.');
    }

    public function applyTemplate(
        EventRegistrationForm $form,
        EventRegistrationFormTemplate $template,
        EventRegistrationService $registrations
    ) {
        $registrations->applyTemplate($form, $template, (int) Auth::guard('organizer')->id());
        return back()->with('success', 'Template applied. Review ticket assignments and privacy text before publishing.');
    }

    public function createAllocation(Request $request, EventRegistrationForm $form, EventRegistrationAllocationService $allocations)
    {
        $validated = $request->validate([
            'sponsor_name' => ['required', 'string', 'max:150'],
            'sponsor_email' => ['required', 'email:rfc', 'max:191'],
            'capacity' => ['required', 'integer', 'min:1', 'max:'.(int) config('registration.allocation_max_capacity')],
            'expires_at' => ['nullable', 'date', 'after:today', 'before_or_equal:'.now()->addYear()->toDateString()],
            'source_attested' => ['accepted'],
        ]);
        $allocations->create($form, (int) Auth::guard('organizer')->id(), $validated, $request->ip());
        return back()->with('success', 'Sponsor allocation saved. A secure management link has been queued for delivery.');
    }

    public function cancelAllocation(EventRegistrationAllocation $allocation, EventRegistrationAllocationService $allocations)
    {
        $allocations->cancel($allocation, (int) Auth::guard('organizer')->id());
        return back()->with('success', 'Sponsor allocation cancelled. Outstanding attendee links can no longer be used.');
    }

    public function saveSession(Request $request, EventRegistrationForm $form, EventRegistrationSessionService $sessions)
    {
        $validated = $request->validate([
            'session_id' => ['nullable', 'integer'], 'title' => ['required', 'string', 'max:191'],
            'description' => ['nullable', 'string', 'max:5000'], 'location' => ['nullable', 'string', 'max:191'],
            'starts_at' => ['required', 'date', 'after:now'], 'ends_at' => ['required', 'date', 'after:starts_at'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:100000'], 'ticket_id' => ['nullable', 'integer'],
            'is_required' => ['nullable', 'boolean'],
        ]);
        $session = !empty($validated['session_id']) ? EventRegistrationSession::findOrFail((int) $validated['session_id']) : null;
        $sessions->save($form, (int) Auth::guard('organizer')->id(), $validated, $session);
        return back()->with('success', $session ? 'Agenda session updated.' : 'Agenda session published.');
    }

    public function cancelSession(EventRegistrationSession $session, EventRegistrationSessionService $sessions)
    {
        $sessions->cancel($session, (int) Auth::guard('organizer')->id());
        return back()->with('success', 'Agenda session cancelled.');
    }

    public function issueInvoice(
        Request $request,
        EventRegistrationSubmission $submission,
        EventRegistrationInvoiceService $invoices,
    ) {
        $validated = $request->validate([
            'purchase_order_number' => ['nullable', 'string', 'max:191'],
            'due_at' => ['required', 'date', 'after:today', 'before_or_equal:'.now()->addDays(90)->toDateString()],
        ]);
        $invoices->issue($submission, (int) Auth::guard('organizer')->id(), $validated);
        return back()->with('success', 'Corporate invoice issued. Tickets remain unissued until verified payment is recorded.');
    }

    public function markInvoicePaid(
        Request $request,
        EventRegistrationInvoice $invoice,
        EventRegistrationInvoiceService $invoices,
    ) {
        $validated = $request->validate([
            'payment_reference' => ['required', 'string', 'max:191'],
            'payment_note' => ['nullable', 'string', 'max:2000'],
            'payment_verified' => ['accepted'],
        ]);
        $invoices->markPaid($invoice, (int) Auth::guard('organizer')->id(), $validated);
        return back()->with('success', 'Payment recorded and individual tickets queued for delivery.');
    }

    public function invoice(EventRegistrationInvoice $invoice, EventRegistrationInvoiceDocumentService $documents)
    {
        abort_unless((int) $invoice->organizer_id === (int) Auth::guard('organizer')->id(), 403);
        AuditLogger::record('event.registration_invoice.viewed', $invoice, [], [], [
            'event_id' => $invoice->event_id, 'submission_id' => $invoice->submission_id,
        ]);
        return $documents->response($invoice);
    }

    public function saveBadgeSettings(
        Request $request,
        EventRegistrationForm $form,
        EventRegistrationBadgeService $badges,
    ) {
        $validated = $request->validate([
            'badge_title' => ['nullable', 'string', 'max:100'],
            'company_question_key' => ['nullable', 'string', 'max:80'],
            'role_question_key' => ['nullable', 'string', 'max:80'],
            'accent_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);
        $badges->save($form, (int) Auth::guard('organizer')->id(), $validated);

        return back()->with('success', 'Badge design saved. Printed QR codes use the same secure ticket identity as event check-in.');
    }

    public function downloadBadges(EventRegistrationForm $form, EventRegistrationBadgeService $badges)
    {
        try {
            return $badges->download($form, (int) Auth::guard('organizer')->id());
        } catch (DomainException $exception) {
            return back()->with('warning', $exception->getMessage());
        }
    }

    public function saveEmbedSettings(
        Request $request,
        EventRegistrationForm $form,
        EventRegistrationEmbedService $embeds,
    ) {
        $validated = $request->validate([
            'enabled' => ['nullable', 'boolean'],
            'allowed_origins' => ['nullable', 'string', 'max:2000'],
            'accent_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'background_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);
        $embeds->save($form, (int) Auth::guard('organizer')->id(), $validated);

        return back()->with('success', !empty($validated['enabled'])
            ? 'Registration embed enabled only for the trusted website origins listed below.'
            : 'Registration embed settings saved. Embedding is currently disabled.');
    }
}
