<?php

namespace App\Http\Controllers\BackEnd\Organizer;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventGuestInvitation;
use App\Models\EventGuestImport;
use App\Services\DataExportService;
use App\Services\EventGuestImportService;
use App\Services\EventGuestListService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EventGuestListController extends Controller
{
    public function index(Request $request)
    {
        $organizerId = (int) Auth::guard('organizer')->id();
        $events = Event::with('information')
            ->where('organizer_id', $organizerId)
            ->orderByDesc('id')
            ->get();
        $eventId = (int) ($request->integer('event_id') ?: ($events->first()?->id ?? 0));
        $event = $eventId
            ? Event::with('information')->where('organizer_id', $organizerId)->findOrFail($eventId)
            : null;
        $invitations = $event
            ? EventGuestInvitation::where('organizer_id', $organizerId)
                ->where('event_id', $event->id)
                ->orderByDesc('id')
                ->paginate(25)
                ->withQueryString()
            : null;
        $imports = $event
            ? EventGuestImport::where('organizer_id', $organizerId)->where('event_id', $event->id)
                ->with(['rows' => fn ($query) => $query->where('status', 'rejected')->orderBy('row_number')])
                ->orderByDesc('id')->limit(10)->get()
            : collect();

        return view('organizer.event.guest-list.index', compact('events', 'event', 'invitations', 'imports'));
    }

    public function store(Request $request, EventGuestListService $guestList)
    {
        $validated = $request->validate([
            'event_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email:rfc', 'max:191'],
            'party_size' => ['required', 'integer', 'min:1', 'max:20'],
            'source_attested' => ['accepted'],
        ]);
        $event = Event::query()->findOrFail((int) $validated['event_id']);
        $guestList->invite($event, (int) Auth::guard('organizer')->id(), $validated, $request->ip());

        return redirect()->route('organizer.event.guest-list.index', ['event_id' => $event->id])
            ->with('success', 'Guest invitation queued. The guest must accept before check-in.');
    }

    public function cancel(EventGuestInvitation $invitation, EventGuestListService $guestList)
    {
        $guestList->cancel($invitation, (int) Auth::guard('organizer')->id());
        return back()->with('success', 'Guest invitation cancelled.');
    }

    public function checkIn(EventGuestInvitation $invitation, EventGuestListService $guestList)
    {
        $guestList->checkIn($invitation, (int) Auth::guard('organizer')->id());
        return back()->with('success', 'Guest checked in.');
    }

    public function import(Request $request, EventGuestImportService $imports)
    {
        $validated = $request->validate([
            'event_id' => ['required', 'integer'],
            'guest_file' => ['required', 'file', 'mimes:csv,txt', 'max:'.max(64, (int) config('tickets.guest_import_max_file_kb', 2048))],
            'source_attested' => ['accepted'],
        ]);
        $event = Event::query()->findOrFail((int) $validated['event_id']);
        try {
            $imports->queue($event, (int) Auth::guard('organizer')->id(), $validated['guest_file'], true);
        } catch (DomainException $exception) {
            return back()->withErrors(['guest_file' => $exception->getMessage()])->withInput();
        }
        return redirect()->route('organizer.event.guest-list.index', ['event_id' => $event->id])
            ->with('success', 'Guest import queued. Row-level results will appear here after processing.');
    }

    public function template()
    {
        return response()->streamDownload(function (): void {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, EventGuestImportService::HEADERS, ',', '"', '');
            fputcsv($stream, ['Example Guest', 'guest@example.com', '1'], ',', '"', '');
            fclose($stream);
        }, 'tica-guest-import-template.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function export(Request $request, DataExportService $exports)
    {
        $validated = $request->validate([
            'event_id' => ['required', 'integer'],
            'status' => ['nullable', 'in:pending,accepted,declined,cancelled,expired'],
            'from_date' => ['nullable', 'date'], 'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
            'format' => ['required', 'in:csv,xlsx'], 'privacy_confirmed' => ['accepted'],
        ]);
        try {
            $exports->queueGuestList((int) Auth::guard('organizer')->id(), (int) $validated['event_id'],
                $request->only(['status', 'from_date', 'to_date']), $validated['format'], true);
        } catch (DomainException $exception) {
            return back()->withErrors(['export' => $exception->getMessage()])->withInput();
        }
        return redirect()->route('organizer.data_exports.index')
            ->with('success', 'Guest-list export queued. The private download will appear when processing finishes.');
    }
}
