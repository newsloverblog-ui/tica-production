<?php

namespace App\Http\Controllers\BackEnd;

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
        $adminId = (int) Auth::guard('admin')->id();
        $exports = DataExport::query()
            ->where('requested_by_type', 'admin')->where('requested_by_id', $adminId)
            ->where('export_type', 'transactions')->latest()->paginate(25);
        return view('backend.admin.transaction-exports', compact('exports'));
    }

    public function store(Request $request, DataExportService $exports)
    {
        $validated = $this->filters($request, true);
        try {
            $exports->queueTransactions('admin', (int) Auth::guard('admin')->id(), null, $validated, $validated['format']);
        } catch (DomainException $exception) {
            return back()->withInput()->with('warning', $exception->getMessage());
        }
        return redirect()->route('admin.transaction_exports.index')
            ->with('success', 'Transaction export queued. The private download will appear when processing finishes.');
    }

    public function download(DataExport $export)
    {
        abort_unless(
            $export->requested_by_type === 'admin'
            && (int) $export->requested_by_id === (int) Auth::guard('admin')->id()
            && $export->export_type === 'transactions', 403
        );
        return $this->downloadAuthorized($export);
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

    private function downloadAuthorized(DataExport $export)
    {
        if (!$export->isDownloadable()) abort(410, 'This export is not ready or has expired.');
        if (!Storage::disk($export->disk)->exists($export->path)) abort(404, 'The export file is unavailable.');
        $export->forceFill(['downloaded_at' => now()])->save();
        AuditLogger::record('data_export.downloaded', $export, [], ['downloaded_at' => $export->downloaded_at]);
        return Storage::disk($export->disk)->download($export->path, $export->filename, [
            'Cache-Control' => 'private, no-store, max-age=0', 'Pragma' => 'no-cache',
            'Expires' => '0', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
