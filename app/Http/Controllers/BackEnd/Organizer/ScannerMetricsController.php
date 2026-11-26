<?php

namespace App\Http\Controllers\BackEnd\Organizer;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\ScannerAdmissionPolicy;
use App\Services\ScannerMetricsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ScannerMetricsController extends Controller
{
    public function events()
    {
        $recent = now()->subDays(2);
        $events = Event::query()
            ->where('organizer_id', Auth::guard('organizer')->id())
            ->where('status', 1)
            ->whereNull('cancelled_at')
            ->where(fn ($query) => $query->whereNull('lifecycle_status')->orWhere('lifecycle_status', '!=', 'cancelled'))
            ->where(fn ($query) => $query->whereNull('registration_mode')->orWhere('registration_mode', '!=', 'corporate'))
            ->where(fn ($query) => $query->where('end_date_time', '>=', $recent)
                ->orWhereHas('dates', fn ($dates) => $dates->where('end_date_time', '>=', $recent)))
            ->with('information:id,event_id,title')
            ->orderByRaw('CASE WHEN start_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('start_date')
            ->orderBy('id')
            ->get()
            ->map(fn (Event $event) => [
                'id' => (int) $event->id,
                'title' => $event->information?->title ?: __('Event #:id', ['id' => $event->id]),
                'date' => $event->start_date,
            ]);

        return response()->json(['events' => $events])->header('Cache-Control', 'no-store, private');
    }

    public function show(Request $request, int $event, ScannerMetricsService $service)
    {
        app(ScannerAdmissionPolicy::class)->deviceForEvent($request, $event);
        $organizerId = (int) (
            $request->attributes->get('scanner_organizer_id')
            ?: Auth::guard('organizer')->id()
        );

        return response()->json($service->forEvent($organizerId, $event))
            ->header('Cache-Control', 'no-store, private');
    }
}
