<?php

namespace App\Http\Controllers\BackEnd\Organizer;

use App\Http\Controllers\Controller;
use App\Models\ScannerDevice;
use App\Services\ScannerDeviceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ScannerDeviceController extends Controller
{
    public function current(Request $request)
    {
        return response()->json([
            'device' => $this->present($request->attributes->get('scanner_device')),
        ])->header('Cache-Control', 'no-store');
    }

    public function index()
    {
        $devices = ScannerDevice::where('organizer_id', Auth::guard('organizer')->id())
            ->orderByRaw("case when status = 'active' then 0 else 1 end")
            ->orderByDesc('last_seen_at')
            ->orderByDesc('id')
            ->get();

        return response()->json(['devices' => $devices->map(fn (ScannerDevice $device) => $this->present($device))]);
    }

    public function store(Request $request, ScannerDeviceService $devices)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:100'],
        ]);

        [$device, $secret] = $devices->enroll((int) Auth::guard('organizer')->id(), $validated['name']);

        return response()->json([
            'message' => 'Scanner enrolled. Its private credential is stored in this browser only.',
            'device' => $this->present($device),
            'secret' => $secret,
        ], 201)->header('Cache-Control', 'no-store');
    }

    public function revoke(ScannerDevice $device, ScannerDeviceService $devices)
    {
        $device = $devices->revoke((int) Auth::guard('organizer')->id(), $device->id);

        return response()->json([
            'message' => 'Scanner access revoked.',
            'device' => $this->present($device),
        ]);
    }

    private function present(ScannerDevice $device): array
    {
        return [
            'id' => $device->id,
            'public_id' => $device->public_id,
            'name' => $device->name,
            'status' => $device->status,
            'last_seen_at' => optional($device->last_seen_at)->toIso8601String(),
            'revoked_at' => optional($device->revoked_at)->toIso8601String(),
        ];
    }
}
