<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use App\Models\TicaPromotionCampaign;
use App\Services\TicaPromotionAnalyticsService;
use App\Services\TicaPromotionPaystackService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PromotionTrackingController extends Controller
{
    public function paystackCallback(Request $request, TicaPromotionPaystackService $payments): RedirectResponse
    {
        $reference = trim((string) ($request->query('reference') ?: $request->query('trxref')));
        if ($reference === '') return redirect('/dashboard/promote')->with('error', 'The promotion payment reference is missing.');
        try {
            $campaign = $payments->verifyReference($reference);
            return redirect('/dashboard/promote')->with('success', 'Payment verified for ' . $campaign->reference . '. TICA will review the campaign before it goes live.');
        } catch (\Throwable $exception) {
            report($exception);
            return redirect('/dashboard/promote')->with('error', 'We could not verify that payment yet. Do not pay again; contact TICA support with reference ' . $reference . '.');
        }
    }

    public function click(Request $request, int $campaign, TicaPromotionAnalyticsService $analytics): RedirectResponse
    {
        $record = TicaPromotionCampaign::query()->with(['event.information', 'plan'])->whereKey($campaign)->firstOrFail();
        abort_unless($analytics->isPubliclyEligible($record), 404);
        $analytics->recordVisit($record, 'click', $request);
        $request->session()->put('tica_promotion_campaign_id', $record->id);

        $content = optional($record->event)->information;
        $fallback = route('event.details', [optional($content)->slug, $record->event_id]);
        $destination = filter_var($record->destination_url, FILTER_VALIDATE_URL) ? $record->destination_url : $fallback;
        return redirect()->away($destination);
    }
}
