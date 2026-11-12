<?php

namespace App\Services;

use App\Models\BasicSettings\MailTemplate;
use App\Models\Event;
use App\Models\Event\Booking;
use App\Models\Event\EventContent;
use App\Models\Language;
use App\Models\ShopManagement\ProductOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use RuntimeException;

class ManualPaymentDecisionMailService
{
    public function sendBookingRejection(Booking $booking): void
    {
        $template = MailTemplate::query()
            ->where('mail_type', 'event_booking_rejected')
            ->first();
        if (!$template) {
            throw new RuntimeException('The event booking rejection mail template is missing.');
        }

        $event = Event::query()->findOrFail($booking->event_id);
        $language = $this->language();
        $content = EventContent::query()
            ->where('event_id', $event->id)
            ->where('language_id', $language->id)
            ->first();
        if (!$content) {
            throw new RuntimeException('The event booking rejection language content is missing.');
        }

        $body = str_replace(
            ['{customer_name}', '{order_id}', '{title}', '{website_title}'],
            [
                e(trim((string) $booking->fname.' '.(string) $booking->lname)),
                e((string) $booking->booking_id),
                '<a href="'.e(route('event.details', [
                    'slug' => $content->slug,
                    'id' => $event->id,
                ])).'">'.e((string) $content->title).'</a>',
                e($this->websiteTitle()),
            ],
            (string) $template->mail_body
        );

        app(ConfiguredMailer::class)->sendHtml(
            (string) $booking->email,
            (string) $template->mail_subject,
            $body
        );
    }

    public function sendProductOrderRejection(ProductOrder $order): void
    {
        $template = MailTemplate::query()->where('mail_type', 'product_order')->first();
        if (!$template) {
            throw new RuntimeException('The product order mail template is missing.');
        }

        $body = str_replace(
            ['{customer_name}', '{order_id}', '{website_title}'],
            [
                e(trim((string) $order->billing_fname.' '.(string) $order->billing_lname)),
                e((string) $order->order_number),
                e($this->websiteTitle()),
            ],
            (string) $template->mail_body
        );

        app(ConfiguredMailer::class)->sendHtml(
            (string) $order->billing_email,
            (string) $template->mail_subject,
            $body
        );
    }

    private function language(): Language
    {
        $query = Language::query();
        $language = Session::has('lang')
            ? (clone $query)->where('code', Session::get('lang'))->first()
            : null;

        return $language ?: $query->where('is_default', 1)->firstOrFail();
    }

    private function websiteTitle(): string
    {
        return (string) (DB::table('basic_settings')->value('website_title') ?: config('app.name'));
    }
}
