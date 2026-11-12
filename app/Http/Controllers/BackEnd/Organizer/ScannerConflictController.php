<?php

namespace App\Http\Controllers\BackEnd\Organizer;

use App\Http\Controllers\Controller;
use App\Models\ScannerCheckInConflict;
use App\Services\ScannerConflictService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ScannerConflictController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'status' => ['nullable', 'in:open,resolved,all'],
        ]);
        $status = $validated['status'] ?? 'open';
        $conflicts = ScannerCheckInConflict::query()
            ->with(['ticket.booking', 'originalDevice', 'attemptedDevice', 'originalStaff', 'attemptedStaff'])
            ->where('organizer_id', $request->attributes->get('scanner_organizer_id') ?: Auth::guard('organizer')->id())
            ->when($status === 'open', fn ($query) => $query->whereNull('resolved_at'))
            ->when($status === 'resolved', fn ($query) => $query->whereNotNull('resolved_at'))
            ->orderByDesc('last_detected_at')
            ->limit(100)
            ->get();

        return response()->json(['conflicts' => $conflicts->map(fn ($conflict) => $this->present($conflict))]);
    }

    public function resolve(Request $request, ScannerCheckInConflict $conflict, ScannerConflictService $service)
    {
        $validated = $request->validate([
            'note' => ['required', 'string', 'min:3', 'max:500'],
        ]);
        $organizerId = (int) ($request->attributes->get('scanner_organizer_id') ?: Auth::guard('organizer')->id());
        $conflict = $service->resolve($organizerId, $conflict->id, $validated['note']);

        return response()->json(['message' => 'Conflict marked as reviewed.', 'conflict' => $this->present($conflict->load(['ticket.booking', 'originalDevice', 'attemptedDevice', 'originalStaff', 'attemptedStaff']))]);
    }

    private function present(ScannerCheckInConflict $conflict): array
    {
        $booking = optional(optional($conflict->ticket)->booking);
        return [
            'id' => $conflict->id,
            'ticket_public_id' => optional($conflict->ticket)->public_id,
            'booking_id' => $booking->booking_id,
            'attendee' => trim(($booking->fname ?? '') . ' ' . ($booking->lname ?? '')),
            'event_id' => $conflict->event_id,
            'original_gate' => optional($conflict->originalDevice)->name ?: 'Unknown gate',
            'attempted_gate' => optional($conflict->attemptedDevice)->name ?: 'Unknown gate',
            'original_staff' => optional($conflict->originalStaff)->name,
            'attempted_staff' => optional($conflict->attemptedStaff)->name,
            'occurrences' => $conflict->occurrences,
            'first_detected_at' => optional($conflict->first_detected_at)->toIso8601String(),
            'last_detected_at' => optional($conflict->last_detected_at)->toIso8601String(),
            'resolved_at' => optional($conflict->resolved_at)->toIso8601String(),
            'resolution_note' => $conflict->resolution_note,
        ];
    }
}
