<?php

namespace App\Http\Controllers\BackEnd;

use App\Http\Controllers\Controller;
use App\Services\PageTrafficAnalytics;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PageTrafficController extends Controller
{
    public function __invoke(Request $request, PageTrafficAnalytics $analytics)
    {
        $dates = $request->validate([
            'from' => ['nullable', 'required_with:to', 'date_format:Y-m-d', 'before_or_equal:to'],
            'to' => ['nullable', 'required_with:from', 'date_format:Y-m-d', 'before_or_equal:today'],
        ]);
        $today = CarbonImmutable::today();
        $to = isset($dates['to']) ? CarbonImmutable::parse($dates['to']) : $today;
        $from = isset($dates['from']) ? CarbonImmutable::parse($dates['from']) : $to->subDays(29);

        if ($from->lt($to->subDays(89))) {
            throw ValidationException::withMessages(['from' => __('Choose a date range of 90 days or less.')]);
        }

        return response()
            ->view('backend.admin.page-traffic.index', [
                'traffic' => $analytics->report($from, $to),
                'from' => $from,
                'to' => $to,
                'today' => $today,
            ])
            ->header('Cache-Control', 'private, no-store');
    }
}
