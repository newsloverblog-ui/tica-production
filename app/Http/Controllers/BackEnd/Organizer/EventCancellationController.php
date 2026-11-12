<?php

namespace App\Http\Controllers\BackEnd\Organizer;

use App\Http\Controllers\Controller;
use App\Services\EventCancellationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

class EventCancellationController extends Controller
{
    public function store(Request $request, int $event, EventCancellationService $service)
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
            'customer_message' => ['nullable', 'string', 'max:3000'],
            'confirm' => ['accepted'],
        ]);

        try {
            $service->requestByOrganizer(
                $event,
                (int) Auth::guard('organizer')->id(),
                $validated['reason'],
                $validated['customer_message'] ?? null
            );
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage())->withInput();
        }

        return back()->with('success', 'Cancellation requested. Sales are paused while an administrator reviews the refund impact.');
    }
}
