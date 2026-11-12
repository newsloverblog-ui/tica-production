<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use App\Services\FunnelAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class AnalyticsController extends Controller
{
    public function store(Request $request, FunnelAnalyticsService $analytics): JsonResponse
    {
        if (strlen((string) $request->getContent()) > 16384) {
            return response()->json(['message' => 'Analytics payload is too large.'], 413);
        }

        $validated = $request->validate([
            'event' => ['required', 'string', Rule::in(FunnelAnalyticsService::BROWSER_EVENTS)],
            'occurred_at' => ['nullable', 'date'],
            'properties' => ['nullable', 'array', 'max:20'],
            'properties.*' => ['nullable'],
        ]);

        try {
            $analytics->recordBrowser($request, $validated);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(null, 202);
    }
}
