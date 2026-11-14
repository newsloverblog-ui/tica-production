<?php

namespace App\Services;

use App\Models\TicaPromotionCampaign;
use Throwable;

class TicaPromotionNotificationService
{
    public function send(TicaPromotionCampaign $campaign, string $kind, string $subject, string $message): void
    {
        try {
            $campaign->loadMissing(['organizer', 'event.information', 'plan']);
            $eventName = (string) (optional(optional($campaign->event)->information)->title ?: 'your event');
            $body = '<div style="font-family:Arial,sans-serif;max-width:600px;margin:auto;color:#11213b">'
                . '<p style="color:#2b63cf;font-weight:700">TICA Promote</p><h1 style="font-size:24px">' . e($subject) . '</h1>'
                . '<p style="line-height:1.6">' . e($message) . '</p>'
                . '<div style="background:#f4f7fc;border-radius:12px;padding:16px;margin:20px 0"><strong>' . e($eventName) . '</strong><br>'
                . e((string) optional($campaign->plan)->name) . ' · ' . e((string) $campaign->reference) . '</div>'
                . '<p><a href="' . e(url('/dashboard/promote')) . '" style="background:#2b63cf;color:#fff;text-decoration:none;padding:12px 18px;border-radius:8px;display:inline-block">View campaign</a></p></div>';
            app(ApplicationMailService::class)->queueHtml(
                'promotion_' . $kind, (string) $campaign->organizer->email, $subject, $body, [], null, $campaign,
                'promotion:' . $kind . ':' . $campaign->id . ':' . $campaign->status . ':' . $campaign->payment_status
            );
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
