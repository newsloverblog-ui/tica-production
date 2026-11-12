<?php

namespace App\Http\Controllers\BackEnd;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\DataExport;
use App\Services\AuditLogger;
use App\Services\DataExportService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $filters = $this->validatedFilters($request);
        $logs = AuditLog::query()
            ->when($filters['from_date'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['to_date'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '<=', $date))
            ->when($filters['action'] ?? null, fn ($query, $action) => $query->where('action', $action))
            ->when($filters['actor_type'] ?? null, fn ($query, $type) => $query->where('actor_type', $type))
            ->latest('id')->paginate(50)->withQueryString();
        $adminId = (int) Auth::guard('admin')->id();
        $exports = DataExport::query()
            ->where('requested_by_type', 'admin')
            ->where('requested_by_id', $adminId)
            ->where('export_type', 'audit_logs')
            ->latest()->limit(10)->get();

        return view('backend.admin.audit-logs.index', compact('logs', 'exports', 'filters'));
    }

    public function export(Request $request, DataExportService $exports)
    {
        $validated = $request->validate([
            'format' => ['required', 'in:csv,xlsx'],
            'from_date' => ['nullable', 'date_format:Y-m-d'],
            'to_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from_date'],
            'action' => ['nullable', 'string', 'max:120'],
            'actor_type' => ['nullable', 'in:admin,organizer,scanner,customer,web,system'],
        ]);
        try {
            $exports->queueAuditLogs((int) Auth::guard('admin')->id(), $validated, $validated['format']);
        } catch (DomainException $exception) {
            return back()->withInput()->with('warning', $exception->getMessage());
        }

        return back()->with('success', 'The private audit export has been queued.');
    }

    public function download(DataExport $export)
    {
        abort_unless(
            $export->export_type === 'audit_logs'
            && $export->requested_by_type === 'admin'
            && (int) $export->requested_by_id === (int) Auth::guard('admin')->id(),
            403
        );
        if (!$export->isDownloadable()) abort(410, 'This export is not ready or has expired.');
        if (!Storage::disk($export->disk)->exists($export->path)) abort(404, 'The export file is unavailable.');

        $export->forceFill(['downloaded_at' => now()])->save();
        AuditLogger::record('data_export.downloaded', $export, [], ['downloaded_at' => $export->downloaded_at]);

        return Storage::disk($export->disk)->download($export->path, $export->filename);
    }

    private function validatedFilters(Request $request): array
    {
        return $request->validate([
            'from_date' => ['nullable', 'date_format:Y-m-d'],
            'to_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from_date'],
            'action' => ['nullable', 'string', 'max:120'],
            'actor_type' => ['nullable', 'in:admin,organizer,scanner,customer,web,system'],
        ]);
    }
}
