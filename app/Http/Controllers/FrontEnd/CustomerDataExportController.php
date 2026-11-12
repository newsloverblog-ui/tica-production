<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use App\Models\DataExport;
use App\Models\CustomerPrivacyRequest;
use App\Services\AuditLogger;
use App\Services\DataExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class CustomerDataExportController extends Controller
{
    public function index()
    {
        $exports = DataExport::query()
            ->where('requested_by_type', 'customer')
            ->where('requested_by_id', Auth::guard('customer')->id())
            ->where('export_type', 'customer_personal_data')
            ->latest()
            ->paginate(15);
        $privacyRequests = CustomerPrivacyRequest::query()
            ->where('customer_id', Auth::guard('customer')->id())
            ->latest('submitted_at')
            ->limit(10)
            ->get();

        return view('frontend.customer.dashboard.data-exports', compact('exports', 'privacyRequests'));
    }

    public function store(DataExportService $exports)
    {
        $export = $exports->queueCustomerData((int) Auth::guard('customer')->id());

        return redirect()->route('customer.data-export.index')->with(
            'success',
            $export->status === 'ready'
                ? 'Your existing export is ready below.'
                : 'Your private data export has been queued. Refresh this page shortly.'
        );
    }

    public function download(Request $request, DataExport $export)
    {
        abort_unless(
            $export->requested_by_type === 'customer'
            && (int) $export->requested_by_id === (int) Auth::guard('customer')->id()
            && $export->export_type === 'customer_personal_data',
            403
        );
        if (!$export->isDownloadable()) {
            abort(410, 'This export is not ready or has expired.');
        }
        if (!Storage::disk($export->disk)->exists($export->path)) {
            abort(404, 'The export file is unavailable.');
        }

        $export->forceFill(['downloaded_at' => now()])->save();
        AuditLogger::record('customer_data_export.downloaded', $export, [], [
            'downloaded_at' => $export->downloaded_at,
        ]);

        return Storage::disk($export->disk)->download($export->path, $export->filename, [
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
