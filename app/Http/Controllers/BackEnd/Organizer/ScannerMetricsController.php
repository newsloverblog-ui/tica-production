<?php

namespace App\Http\Controllers\BackEnd\Organizer;

use App\Http\Controllers\Controller;
use App\Services\ScannerMetricsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ScannerMetricsController extends Controller
{
    public function show(Request $request, int $event, ScannerMetricsService $service)
    {
        $organizerId = (int) (
            $request->attributes->get('scanner_organizer_id')
            ?: Auth::guard('organizer')->id()
        );

        return response()->json($service->forEvent($organizerId, $event))
            ->header('Cache-Control', 'no-store, private');
    }
}
