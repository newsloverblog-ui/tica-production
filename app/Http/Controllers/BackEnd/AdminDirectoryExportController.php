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

class AdminDirectoryExportController extends Controller
{
    public function store(Request $request, string $directory, DataExportService $exports)
    {
        abort_unless(in_array($directory, ['organizers', 'customers'], true), 404);
        $this->authorizeDirectory($directory);
        $validated = $request->validate([
            'format' => ['required', 'in:csv,xlsx'],
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'in:0,1'],
            'verified' => ['nullable', 'in:0,1'],
        ]);

        try {
            $exports->queueAdminDirectory(
                (int) Auth::guard('admin')->id(),
                $directory,
                $validated,
                $validated['format']
            );
        } catch (DomainException $exception) {
            return back()->withInput()->with('warning', $exception->getMessage());
        }

        return redirect()->route('admin.directory_exports.index', ['directory' => $directory])
            ->with('success', ucfirst($directory).' export queued. Your private download will appear when it is ready.');
    }

    public function index(Request $request)
    {
        $directory = in_array($request->query('directory'), ['organizers', 'customers'], true)
            ? $request->query('directory')
            : null;
        if ($directory) {
            $this->authorizeDirectory($directory);
        }

        $exports = DataExport::query()
            ->where('requested_by_type', 'admin')
            ->where('requested_by_id', (int) Auth::guard('admin')->id())
            ->whereIn('export_type', $directory ? [$directory] : $this->authorizedDirectories())
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('backend.admin.directory-exports', compact('exports', 'directory'));
    }

    public function download(DataExport $export)
    {
        abort_unless(
            $export->requested_by_type === 'admin'
            && (int) $export->requested_by_id === (int) Auth::guard('admin')->id()
            && in_array($export->export_type, ['organizers', 'customers'], true),
            403
        );
        $this->authorizeDirectory($export->export_type);
        abort_unless($export->isDownloadable(), 410, 'This export is not ready or has expired.');
        abort_unless(Storage::disk($export->disk)->exists($export->path), 404, 'The export file is unavailable.');

        $export->forceFill(['downloaded_at' => now()])->save();
        AuditLogger::record('data_export.downloaded', $export, [], ['downloaded_at' => $export->downloaded_at]);

        return Storage::disk($export->disk)->download($export->path, $export->filename, [
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function authorizeDirectory(string $directory): void
    {
        $permission = $directory === 'organizers' ? 'Organizer Mangement' : 'Customer Management';
        abort_unless(Auth::guard('admin')->user()?->hasPermission($permission), 403);
    }

    private function authorizedDirectories(): array
    {
        $admin = Auth::guard('admin')->user();
        $allowed = [];
        if ($admin?->hasPermission('Organizer Mangement')) {
            $allowed[] = 'organizers';
        }
        if ($admin?->hasPermission('Customer Management')) {
            $allowed[] = 'customers';
        }
        abort_if($allowed === [], 403);

        return $allowed;
    }
}
