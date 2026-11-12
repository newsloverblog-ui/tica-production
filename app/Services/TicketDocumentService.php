<?php

namespace App\Services;

use App\Models\Event\Booking;
use Illuminate\Support\Collection;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class TicketDocumentService
{
    public function generateQrCodes(Booking $booking, bool $bookingRecipientOnly = false): Collection
    {
        $units = (new TicketIssuanceService())->issueForBooking($booking);

        if ($bookingRecipientOnly) {
            // Accepted transfers belong to the recipient. A resend to the
            // original booking address must not disclose the recipient's live QR.
            $units = $units->filter(function ($unit) use ($booking) {
                return $unit->holder_customer_id === null
                    || ($booking->customer_id && (int) $unit->holder_customer_id === (int) $booking->customer_id);
            })->values();
        }

        foreach ($units as $unit) {
            if ($unit->status === 'issued') {
                $svg = QrCode::format('svg')->size(240)->margin(2)->generate(
                    signedTicketUnitPayload($unit->public_id, $unit->event_id)
                );
                // Keep the signed admission payload in-memory while rendering the
                // private invoice. Never leave reusable QR files in the public root.
                $unit->setAttribute('qr_data_uri', 'data:image/svg+xml;base64,'.base64_encode((string) $svg));
            } else {
                // A resent document must never reproduce a checked-in, revoked,
                // refunded or otherwise inactive admission credential.
                $unit->setAttribute('qr_data_uri', null);
            }
        }

        $booking->setRelation('ticketUnits', $units);
        return $units;
    }
}
