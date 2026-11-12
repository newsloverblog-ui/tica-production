<?php

namespace App\Http\Controllers\BackEnd\Organizer;

use App\Http\Controllers\Controller;
use App\Services\ScannerOfflineService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ScannerOfflineController extends Controller
{
    public function events(Request $request, ScannerOfflineService $service)
    {
        return response()->json([
            'events' => $service->events($this->organizerId($request)),
        ])->header('Cache-Control', 'no-store, private');
    }

    public function store(Request $request, ScannerOfflineService $service)
    {
        $validated = $request->validate(['event_id' => ['required', 'integer', 'min:1']]);
        $payload = $service->createManifest(
            $this->organizerId($request),
            (int) $validated['event_id'],
            $request->attributes->get('scanner_device'),
            $request->attributes->get('scanner_staff')
        );

        return response()->json($payload, 201)->header('Cache-Control', 'no-store, private');
    }

    public function sync(Request $request, ScannerOfflineService $service)
    {
        $batchSize = (int) config('tickets.offline_sync_batch_size', 250);
        $validated = $request->validate([
            'manifest_id' => ['required', 'uuid'],
            'scans' => ['required', 'array', 'min:1', 'max:' . $batchSize],
            'scans.*.client_scan_id' => ['required', 'uuid'],
            'scans.*.payload' => ['required', 'string', 'max:512'],
            'scans.*.scanned_at' => ['required', 'date'],
        ]);
        $organizerId = $this->organizerId($request);
        $device = $request->attributes->get('scanner_device');
        $staff = $request->attributes->get('scanner_staff');

        $payload = $service->sync(
            $organizerId,
            $device,
            $staff,
            $validated['manifest_id'],
            $validated['scans'],
            function (string $qrPayload) use ($request, $organizerId, $device, $staff) {
                $checkRequest = Request::create('/scanner/check-qrcode', 'POST', [
                    'booking_id' => $qrPayload,
                    'source' => 'offline_sync',
                ]);
                $checkRequest->attributes->set('scanner_organizer_id', $organizerId);
                $checkRequest->attributes->set('scanner_device', $device);
                $checkRequest->attributes->set('scanner_staff', $staff);
                $checkRequest->attributes->set('scanner_access_role', $request->attributes->get('scanner_access_role'));

                return app(OrganizerController::class)->check_qrcode($checkRequest);
            }
        );

        return response()->json($payload)->header('Cache-Control', 'no-store, private');
    }

    private function organizerId(Request $request): int
    {
        return (int) ($request->attributes->get('scanner_organizer_id') ?: Auth::guard('organizer')->id());
    }
}
