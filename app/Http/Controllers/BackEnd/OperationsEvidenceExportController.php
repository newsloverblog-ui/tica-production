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

class OperationsEvidenceExportController extends Controller
{
    private const TYPES = ['operational_incidents', 'webhook_receipts'];

    public function index()
    {
        $adminId = (int) Auth::guard('admin')->id();
        $exports = DataExport::query()
            ->where('requested_by_type', 'admin')->where('requested_by_id', $adminId)
            ->whereIn('export_type', self::TYPES)->latest()->paginate(25);

        return view('backend.admin.operations-exports', compact('exports'));
    }

    public function storeIncidents(Request $request, DataExportService $exports)
    {
        $validated = $request->validate([
            'status'=>['nullable','in:open,resolved'],
            'severity'=>['nullable','in:critical,error,warning'],
            'format'=>['required','in:csv,xlsx'],
        ]);

        return $this->queue($exports, 'operational_incidents', $validated);
    }

    public function storeWebhooks(Request $request, DataExportService $exports)
    {
        $validated = $request->validate([
            'gateway'=>['nullable','in:paystack,flutterwave'],
            'outcome'=>['nullable','in:received,processed,duplicate,ignored,rejected,failed'],
            'from'=>['required','date_format:Y-m-d'],
            'to'=>['required','date_format:Y-m-d'],
            'format'=>['required','in:csv,xlsx'],
        ]);

        return $this->queue($exports, 'webhook_receipts', $validated);
    }

    public function download(DataExport $export)
    {
        abort_unless(
            $export->requested_by_type === 'admin'
            && (int) $export->requested_by_id === (int) Auth::guard('admin')->id()
            && in_array($export->export_type, self::TYPES, true),
            403
        );
        if (!$export->isDownloadable()) abort(410, 'This export is not ready or has expired.');
        if (!Storage::disk($export->disk)->exists($export->path)) abort(404, 'The export file is unavailable.');
        $export->forceFill(['downloaded_at'=>now()])->save();
        AuditLogger::record('data_export.downloaded', $export, [], ['downloaded_at'=>$export->downloaded_at]);

        return Storage::disk($export->disk)->download($export->path, $export->filename, [
            'Cache-Control'=>'private, no-store, max-age=0', 'Pragma'=>'no-cache',
            'Expires'=>'0', 'X-Content-Type-Options'=>'nosniff',
        ]);
    }

    private function queue(DataExportService $exports, string $type, array $validated)
    {
        try {
            $exports->queueOperationsEvidence(
                (int) Auth::guard('admin')->id(), $type, $validated, $validated['format']
            );
        } catch (DomainException $exception) {
            return back()->withInput()->with('warning', $exception->getMessage());
        }

        return redirect()->route('admin.operations_exports.index')
            ->with('success', 'Operations evidence export queued. The private download will appear when processing finishes.');
    }
}
