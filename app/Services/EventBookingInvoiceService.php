<?php

namespace App\Services;

use App\Models\Event\Booking;
use App\Models\Event\EventContent;
use App\Models\Language;
use Illuminate\Support\Facades\Session;
use PDF;
use RuntimeException;

class EventBookingInvoiceService
{
    public function __construct(
        private readonly TicketDocumentService $tickets,
        private readonly EventBookingDocumentStorage $documents,
    ) {
    }

    /**
     * Build a private PDF from the booking's current ticket state.
     *
     * When $bookingRecipientOnly is true, accepted transfers are omitted and
     * inactive ticket units are rendered without reusable admission QR codes.
     */
    public function generate(Booking $booking, bool $bookingRecipientOnly = false): string
    {
        return $this->renderAndStore($booking, $bookingRecipientOnly, false);
    }

    public function generateCurrentTickets(Booking $booking): string
    {
        return $this->renderAndStore($booking, true, true);
    }

    private function renderAndStore(Booking $booking, bool $bookingRecipientOnly, bool $currentTickets): string
    {
        $this->tickets->generateQrCodes($booking, $bookingRecipientOnly);

        $language = $this->language();
        $eventInfo = EventContent::query()
            ->where('event_id', $booking->event_id)
            ->where('language_id', $language->id)
            ->first();

        $bookingInfo = $booking;
        $width = '50%';
        $float = 'right';
        $mb = '35px';
        $ml = '18px';

        $contents = PDF::loadView(
            'frontend.event.invoice',
            compact('bookingInfo', 'eventInfo', 'width', 'float', 'mb', 'ml')
        )->output();

        return $currentTickets
            ? $this->documents->storeCurrentTickets($booking, $contents)
            : $this->documents->storeInvoice($booking, $contents);
    }

    private function language(): Language
    {
        $locale = Session::get('lang');
        $language = $locale
            ? Language::query()->where('code', $locale)->first()
            : null;

        $language ??= Language::query()->where('is_default', 1)->first();

        if (!$language) {
            throw new RuntimeException('A default language is required to generate ticket documents.');
        }

        return $language;
    }
}
