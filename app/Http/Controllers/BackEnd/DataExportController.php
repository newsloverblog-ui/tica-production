<?php

namespace App\Http\Controllers\BackEnd;

use App\Http\Controllers\Controller;
use App\Models\DataExport;
use App\Models\DataExportSchedule;
use App\Services\AuditLogger;
use App\Services\DataExportScheduleService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class DataExportController extends Controller
{
    public function index()
    {
        $adminId = (int) Auth::guard('admin')->id();
        $exports = DataExport::query()
            ->where('requested_by_type', 'admin')
            ->where('requested_by_id', $adminId)
            ->where('export_type', 'bookings')
            ->latest()
            ->paginate(25);
        $schedules = DataExportSchedule::query()
            ->where('requested_by_type', 'admin')
            ->where('requested_by_id', $adminId)
            ->latest()
            ->get();

        return view('backend.admin.data-exports.index', compact('exports', 'schedules'));
    }

    public function withdrawalIndex()
    {
        $adminId = (int) Auth::guard('admin')->id();
        $exports = DataExport::query()
            ->where('requested_by_type', 'admin')
            ->where('requested_by_id', $adminId)
            ->where('export_type', 'withdrawals')
            ->latest()
            ->paginate(25);

        return view('backend.withdraw.history.exports', compact('exports'));
    }

    public function storeSchedule(Request $request, DataExportScheduleService $schedules)
    {
        $validated = $this->validateSchedule($request);
        try {
            $schedules->createBookingSchedule(
                'admin', (int) Auth::guard('admin')->id(), null, (int) $this->getLanguage()->id,
                $validated, $validated['format'], $validated['frequency'], $validated['timezone'],
                $validated['starts_at'], (int) $validated['date_window_days']
            );
        } catch (DomainException $exception) {
            return back()->withInput()->with('warning', $exception->getMessage());
        }

        return back()->with('success', 'Recurring export schedule created.');
    }

    public function toggleSchedule(DataExportSchedule $schedule, DataExportScheduleService $schedules)
    {
        $this->authorizeSchedule($schedule);
        $schedules->setActive($schedule, !$schedule->active);

        return back()->with('success', $schedule->active ? 'Export schedule resumed.' : 'Export schedule paused.');
    }

    public function download(Request $request, DataExport $export)
    {
        $this->authorizeActor($export);
        abort_unless($export->export_type === 'bookings', 404);
        return $this->downloadAuthorized($export);
    }

    public function downloadWithdrawal(Request $request, DataExport $export)
    {
        $this->authorizeActor($export);
        abort_unless($export->export_type === 'withdrawals', 404);

        return $this->downloadAuthorized($export);
    }

    private function downloadAuthorized(DataExport $export)
    {
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

    private function authorizeActor(DataExport $export): void
    {
        abort_unless(
            $export->requested_by_type === 'admin'
            && (int) $export->requested_by_id === (int) Auth::guard('admin')->id(),
            403
        );
    }

    private function authorizeSchedule(DataExportSchedule $schedule): void
    {
        abort_unless(
            $schedule->requested_by_type === 'admin'
            && (int) $schedule->requested_by_id === (int) Auth::guard('admin')->id(),
            403
        );
    }

    private function validateSchedule(Request $request): array
    {
        return $request->validate([
            'format' => ['required', 'in:csv,xlsx,pdf'],
            'frequency' => ['required', 'in:daily,weekly,monthly'],
            'timezone' => ['required', 'timezone'],
            'starts_at' => ['required', 'date'],
            'date_window_days' => ['required', 'integer', 'between:1,365'],
            'booking_id' => ['nullable', 'string', 'max:100'],
            'event_title' => ['nullable', 'string', 'max:191'],
            'status' => ['nullable', 'string', 'max:40'],
            'payment_status' => ['nullable', 'string', 'max:40'],
            'payment_method' => ['nullable', 'string', 'max:100'],
        ]);
    }
}
