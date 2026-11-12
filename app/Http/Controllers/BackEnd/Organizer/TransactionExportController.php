<?php

namespace App\Http\Controllers\BackEnd\Organizer;

use App\Http\Controllers\Controller;
use App\Models\DataExport;
use App\Services\AuditLogger;
use App\Services\DataExportService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class TransactionExportController extends Controller
{
    public function index()
    {
        $organizerId = (int) Auth::guard('organizer')->id();
        $exports = DataExport::query()
            ->where('requested_by_type', 'organizer')->where('requested_by_id', $organizerId)
            ->where('organizer_id', $organizerId)->where('export_type', 'transactions')
            ->latest()->paginate(25);
        return view('organizer.transaction-exports', compact('exports'));
    }

    public function store(Request $request, DataExportService $exports)
    {
        $validated = $this->filters($request, true);
        $organizerId = (int) Auth::guard('organizer')->id();
        try {
            $exports->queueTransactions('organizer', $organizerId, $organizerId, $validated, $validated['format']);
        } catch (DomainException $exception) {
            return back()->withInput()->with('warning', $exception->getMessage());
        }
        return redirect()->route('organizer.transaction_exports.index')
            ->with('success', 'Transaction export queued. The private download will appear when processing finishes.');
    }

    public function download(DataExport $export)
    {
        $organizerId = (int) Auth::guard('organizer')->id();
        abort_unless(
            $export->requested_by_type === 'organizer'
            && (int) $export->requested_by_id === $organizerId
            && (int) $export->organizer_id === $organizerId
            && $export->export_type === 'transactions', 403
        );
        if (!$export->isDownloadable()) abort(410, 'This export is not ready or has expired.');
        if (!Storage::disk($export->disk)->exists($export->path)) abort(404, 'The export file is unavailable.');
        $export->forceFill(['downloaded_at' => now()])->save();
        AuditLogger::record('data_export.downloaded', $export, [], ['downloaded_at' => $export->downloaded_at]);
        return Storage::disk($export->disk)->download($export->path, $export->filename, [
            'Cache-Control' => 'private, no-store, max-age=0', 'Pragma' => 'no-cache',
            'Expires' => '0', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function filters(Request $request, bool $requireFormat): array
    {
        $validated = $request->validate([
            'transcation_id' => ['nullable', 'string', 'max:100'],
            'transaction_type' => ['nullable', 'integer', 'between:1,7'],
            'payment_status' => ['nullable', 'integer', 'between:0,2'],
            'from_date' => ['nullable', 'date_format:Y-m-d'],
            'to_date' => ['nullable', 'date_format:Y-m-d'],
            'format' => [$requireFormat ? 'required' : 'nullable', 'in:csv,xlsx'],
        ]);
        $validated['transaction_reference'] = trim((string) $request->input('transcation_id', ''));
        return $validated;
    }
}
