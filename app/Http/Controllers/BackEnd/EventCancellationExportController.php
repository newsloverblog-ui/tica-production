<?php

namespace App\Http\Controllers\BackEnd;

use App\Http\Controllers\Controller;
use App\Models\DataExport;
use App\Models\EventCancellationRequest;
use App\Services\AuditLogger;
use App\Services\DataExportService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class EventCancellationExportController extends Controller
{
    public function index()
    {
        $exports = DataExport::query()
            ->where('requested_by_type', 'admin')
            ->where('requested_by_id', (int) Auth::guard('admin')->id())
            ->where('export_type', 'event_cancellation_cases')
            ->latest()
            ->paginate(25);

        return view('backend.event.cancellation-exports', compact('exports'));
    }

    public function store(
        Request $request,
        EventCancellationRequest $cancellation,
        DataExportService $exports
    ) {
        $validated = $request->validate([
            'status' => [
                'nullable',
                'in:refund_queued,manual_review,dispute_open,external_refund_recorded,resolved_no_refund',
            ],
            'format' => ['required', 'in:csv,xlsx'],
        ]);

        try {
            $exports->queueEventCancellationCases(
                (int) Auth::guard('admin')->id(),
                (int) $cancellation->id,
                $validated,
                $validated['format']
            );
        } catch (DomainException $exception) {
            return back()->withInput()->with('warning', $exception->getMessage());
        }

        return redirect()->route('admin.event_cancellation_exports.index')
            ->with('success', 'Cancellation case export queued. The private download will appear when processing finishes.');
    }

    public function download(DataExport $export)
    {
        abort_unless(
            $export->requested_by_type === 'admin'
            && (int) $export->requested_by_id === (int) Auth::guard('admin')->id()
            && $export->export_type === 'event_cancellation_cases',
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
}
