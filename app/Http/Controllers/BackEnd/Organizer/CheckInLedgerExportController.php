<?php

namespace App\Http\Controllers\BackEnd\Organizer;

use App\Http\Controllers\Controller;
use App\Models\DataExport;
use App\Models\Event;
use App\Services\AuditLogger;
use App\Services\DataExportService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class CheckInLedgerExportController extends Controller
{
    public function index(Request $request)
    {
        [$actorType, $actorId, $organizerId] = $this->actor($request);
        $exports = DataExport::query()
            ->where('requested_by_type', $actorType)
            ->where('requested_by_id', $actorId)
            ->where('organizer_id', $organizerId)
            ->where('export_type', 'check_in_ledger')
            ->latest()
            ->paginate(25);

        if ($actorType === 'scanner' || $request->expectsJson()) {
            return response()->json(['exports' => collect($exports->items())->map(
                fn (DataExport $export) => $this->present($export, $actorType)
            )->values(), 'has_more' => $exports->hasMorePages()]);
        }

        $events = Event::query()
            ->with('information')
            ->where('organizer_id', $organizerId)
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        return view('organizer.check-in-ledger-exports', compact('exports', 'events'));
    }

    public function store(Request $request, Event $event, DataExportService $exports)
    {
        $validated = $request->validate(['format' => ['required', 'in:csv,xlsx']]);
        [$actorType, $actorId, $organizerId] = $this->actor($request);

        try {
            $export = $exports->queueCheckInLedger(
                $actorType, $actorId, $organizerId, (int) $event->id, $validated['format']
            );
        } catch (DomainException $exception) {
            if ($actorType === 'scanner' || $request->expectsJson()) {
                return response()->json(['message' => $exception->getMessage()], 422);
            }
            return back()->withInput()->with('warning', $exception->getMessage());
        }

        if ($actorType === 'scanner' || $request->expectsJson()) {
            return response()->json([
                'message' => 'Check-in ledger export queued.',
                'export' => $this->present($export, $actorType),
            ], 202);
        }

        return redirect()->route('organizer.check_in_ledger_exports.index')
            ->with('success', 'Check-in ledger export queued. The private download will appear when processing finishes.');
    }

    public function download(Request $request, DataExport $export)
    {
        [$actorType, $actorId, $organizerId] = $this->actor($request);
        abort_unless(
            $export->requested_by_type === $actorType
            && (int) $export->requested_by_id === $actorId
            && (int) $export->organizer_id === $organizerId
            && $export->export_type === 'check_in_ledger',
            403
        );
        if (!$export->isDownloadable()) {
            abort(410, 'This export is not ready or has expired.');
        }
        if (!Storage::disk($export->disk)->exists($export->path)) {
            abort(404, 'The export file is unavailable.');
        }

        $export->forceFill(['downloaded_at' => now()])->save();
        AuditLogger::record('data_export.downloaded', $export, [], [
            'downloaded_at' => $export->downloaded_at,
        ]);

        return Storage::disk($export->disk)->download($export->path, $export->filename, [
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function actor(Request $request): array
    {
        if (Auth::guard('organizer')->check()) {
            $id = (int) Auth::guard('organizer')->id();
            return ['organizer', $id, $id];
        }
        $staff = Auth::guard('scanner')->user();
        abort_unless($staff && $staff->role === 'supervisor', 403);

        return ['scanner', (int) $staff->id, (int) $staff->organizer_id];
    }

    private function present(DataExport $export, string $actorType): array
    {
        $route = $actorType === 'scanner'
            ? 'organizer.scanner.check_in_ledger_exports.download'
            : 'organizer.check_in_ledger_exports.download';

        return [
            'public_id' => $export->public_id,
            'event_id' => (int) ($export->filters['event_id'] ?? 0),
            'format' => $export->format,
            'status' => $export->status,
            'row_count' => (int) $export->row_count,
            'created_at' => $export->created_at?->toIso8601String(),
            'expires_at' => $export->expires_at?->toIso8601String(),
            'download_url' => $export->isDownloadable()
                ? URL::temporarySignedRoute($route, now()->addMinutes(10), ['export' => $export])
                : null,
        ];
    }
}
