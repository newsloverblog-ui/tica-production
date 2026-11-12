<?php

namespace App\Http\Controllers\BackEnd;

use App\Http\Controllers\Controller;
use App\Models\DataExport;
use App\Models\PaymentReconciliationRun;
use App\Models\GatewaySettlement;
use App\Services\AuditLogger;
use App\Services\DataExportService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ReconciliationExportController extends Controller
{
    private const TYPES = [
        'payment_reconciliation_runs', 'gateway_settlements',
        'payment_reconciliation_entries', 'gateway_settlement_entries',
    ];

    public function index()
    {
        $adminId = (int) Auth::guard('admin')->id();
        $exports = DataExport::query()
            ->where('requested_by_type', 'admin')
            ->where('requested_by_id', $adminId)
            ->whereIn('export_type', self::TYPES)
            ->latest()
            ->paginate(25);

        return view('backend.admin.payment-reconciliation.exports', compact('exports'));
    }

    public function storeRuns(Request $request, DataExportService $exports)
    {
        return $this->store($request, $exports, 'payment_reconciliation_runs');
    }

    public function storeSettlements(Request $request, DataExportService $exports)
    {
        return $this->store($request, $exports, 'gateway_settlements');
    }

    public function storeRunEntries(Request $request, PaymentReconciliationRun $run, DataExportService $exports)
    {
        $validated = $request->validate([
            'result'=>['nullable','in:matched,gateway_only,paid_without_booking,local_status_not_completed,amount_mismatch,currency_mismatch,local_only'],
            'format'=>['required','in:csv,xlsx'],
        ]);

        return $this->storeEntries($exports,'payment_reconciliation_entries',(int)$run->id,$validated);
    }

    public function storeSettlementEntries(Request $request, GatewaySettlement $settlement, DataExportService $exports)
    {
        $validated = $request->validate([
            'result'=>['nullable','in:matched,settled_charge_missing_locally,settled_without_booking,local_status_not_completed,amount_mismatch,currency_mismatch'],
            'format'=>['required','in:csv,xlsx'],
        ]);

        return $this->storeEntries($exports,'gateway_settlement_entries',(int)$settlement->id,$validated);
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

        $export->forceFill(['downloaded_at' => now()])->save();
        AuditLogger::record('data_export.downloaded', $export, [], ['downloaded_at' => $export->downloaded_at]);

        return Storage::disk($export->disk)->download($export->path, $export->filename, [
            'Cache-Control' => 'private, no-store, max-age=0', 'Pragma' => 'no-cache',
            'Expires' => '0', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function store(Request $request, DataExportService $exports, string $type)
    {
        $rules = [
            'date' => ['nullable', 'date_format:Y-m-d'],
            'status' => ['nullable', 'string', 'max:32'],
            'format' => ['required', 'in:csv,xlsx'],
        ];
        if ($type === 'payment_reconciliation_runs') {
            $rules['gateway'] = ['nullable', 'in:paystack,flutterwave'];
        }
        $validated = $request->validate($rules);

        try {
            $exports->queueReconciliation(
                (int) Auth::guard('admin')->id(),
                $type,
                $validated,
                $validated['format']
            );
        } catch (DomainException $exception) {
            return back()->withInput()->with('warning', $exception->getMessage());
        }

        return redirect()->route('admin.reconciliation_exports.index')
            ->with('success', 'Reconciliation export queued. The private download will appear when processing finishes.');
    }

    private function storeEntries(DataExportService $exports,string $type,int $parentId,array $validated)
    {
        try {
            $exports->queueReconciliationEntries(
                (int) Auth::guard('admin')->id(),$type,$parentId,$validated,$validated['format']
            );
        } catch (DomainException $exception) {
            return back()->withInput()->with('warning',$exception->getMessage());
        }

        return redirect()->route('admin.reconciliation_exports.index')
            ->with('success','Reconciliation detail export queued. The private download will appear when processing finishes.');
    }
}
