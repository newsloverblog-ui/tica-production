<?php

namespace App\Http\Middleware;

use App\Services\ScannerDeviceService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class VerifyScannerDevice
{
    public function __construct(private ScannerDeviceService $devices)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        $publicId = (string) $request->header('X-Scanner-Device', '');
        $secret = (string) $request->header('X-Scanner-Secret', '');

        if ($publicId === '' || $secret === '') {
            return response()->json([
                'message' => 'This browser is not enrolled as a scanner.',
                'code' => 'scanner_device_required',
            ], 401);
        }

        $organizerId = (int) ($request->attributes->get('scanner_organizer_id') ?: Auth::guard('organizer')->id());
        $device = $this->devices->authenticate($organizerId, $publicId, $secret, $request);
        if (!$device) {
            return response()->json([
                'message' => 'This scanner credential is invalid or has been revoked.',
                'code' => 'scanner_device_invalid',
            ], 401);
        }

        $request->attributes->set('scanner_device', $device);

        return $next($request);
    }
}
