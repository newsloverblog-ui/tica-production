<?php

namespace App\Http\Controllers\BackEnd\ShopManagement;

use App\Http\Controllers\Controller;
use App\Models\DataExport;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ProductOrderExportController extends Controller
{
    public function index()
    {
        $exports = DataExport::query()
            ->where('requested_by_type', 'admin')
            ->where('requested_by_id', (int) Auth::guard('admin')->id())
            ->where('export_type', 'product_orders')
            ->latest()
            ->paginate(25);

        return view('backend.product.order.exports', compact('exports'));
    }

    public function download(DataExport $export)
    {
        abort_unless(
            $export->export_type === 'product_orders'
            && $export->requested_by_type === 'admin'
            && (int) $export->requested_by_id === (int) Auth::guard('admin')->id(),
            403
        );
        abort_unless($export->isDownloadable(), 410, 'This export is not ready or has expired.');
        abort_unless(Storage::disk($export->disk)->exists($export->path), 404, 'The export file is unavailable.');

        $export->forceFill(['downloaded_at' => now()])->save();
        AuditLogger::record('data_export.downloaded', $export, [], [
            'downloaded_at' => $export->downloaded_at,
        ]);

        return Storage::disk($export->disk)->download($export->path, $export->filename, [
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }
}
