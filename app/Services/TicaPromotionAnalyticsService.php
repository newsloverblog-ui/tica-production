<?php

namespace App\Services;

use App\Models\Event\Booking;
use App\Models\TicaPromotionCampaign;
use App\Models\TicaPromotionEvent;
use App\Support\EventFinancialBreakdown;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TicaPromotionAnalyticsService
{
    public function recordVisit(TicaPromotionCampaign $campaign, string $type, Request $request): bool
    {
        if (!in_array($type, ['impression', 'click'], true) || !$this->isPubliclyEligible($campaign)) return false;
        $visitorHash = hash_hmac('sha256', $request->session()->getId() . '|' . $request->ip(), (string) config('app.key'));
        return DB::transaction(function () use ($campaign, $type, $visitorHash): bool {
            $event = TicaPromotionEvent::query()->firstOrCreate(
                ['campaign_id' => $campaign->id, 'event_type' => $type, 'visitor_hash' => $visitorHash],
                ['occurred_at' => now(), 'amount' => 0, 'currency' => $campaign->currency]
            );
            if (!$event->wasRecentlyCreated) return false;
            TicaPromotionCampaign::query()->whereKey($campaign->id)->increment($type === 'click' ? 'clicks' : 'impressions');
            return true;
        }, 3);
    }

    public function recordConversion(TicaPromotionCampaign $campaign, Booking $booking): bool
    {
        if ((int) $campaign->event_id !== (int) $booking->event_id || !in_array($booking->paymentStatus, ['completed', 'free'], true)) return false;
        return DB::transaction(function () use ($campaign, $booking): bool {
            $amount = EventFinancialBreakdown::fromBooking($booking)['organizer_revenue'];
            $event = TicaPromotionEvent::query()->firstOrCreate(
                ['campaign_id' => $campaign->id, 'event_type' => 'conversion', 'booking_id' => $booking->id],
                ['occurred_at' => now(), 'amount' => $amount, 'currency' => $booking->currencyText ?: $campaign->currency]
            );
            if (!$event->wasRecentlyCreated) return false;
            TicaPromotionCampaign::query()->whereKey($campaign->id)->update([
                'attributed_orders' => DB::raw('attributed_orders + 1'),
                'attributed_tickets' => DB::raw('attributed_tickets + ' . max(1, (int) $booking->quantity)),
                'attributed_revenue' => DB::raw('attributed_revenue + ' . $amount),
            ]);
            return true;
        }, 3);
    }

    public function isPubliclyEligible(TicaPromotionCampaign $campaign): bool
    {
        return $campaign->status === 'active' && $campaign->payment_status === 'paid' && $campaign->approved_at
            && $campaign->starts_at?->lte(now()) && $campaign->ends_at?->gt(now()) && (int) optional($campaign->event)->status === 1;
    }
}
