<?php

namespace App\Services;

final class EventFeeCalculatorService
{
    public const FIXED_FEE_PER_PAID_TICKET = 100.0;

    /**
     * Integer version used by the authoritative ledger. Percent is expressed
     * in basis points (600 = 6%) so no floating-point money enters accounting.
     */
    public function quoteMinor(
        int $ticketPriceMinor,
        int $quantity,
        int $flatFeeMinor,
        int $percentageBasisPoints,
        string $payer
    ): array {
        $ticketPriceMinor = max(0, $ticketPriceMinor);
        $quantity = max(1, min(100000, $quantity));
        $flatFeeMinor = max(0, $flatFeeMinor);
        $percentageBasisPoints = max(0, min(10000, $percentageBasisPoints));
        $sales = $ticketPriceMinor * $quantity;
        $flat = $flatFeeMinor * $quantity;
        $percentage = intdiv(($sales * $percentageBasisPoints) + 5000, 10000);
        $fee = $flat + $percentage;
        $organizerPays = $payer === 'organizer';

        return [
            'ticket_sales_minor' => $sales,
            'platform_fee_minor' => $fee,
            'attendee_total_minor' => $organizerPays ? $sales : $sales + $fee,
            'organizer_net_minor' => $organizerPays ? max(0, $sales - $fee) : $sales,
            'payer' => $organizerPays ? 'organizer' : 'customer',
        ];
    }

    /**
     * Return the attendee charge and organizer proceeds for a paid ticket run.
     *
     * The booking fee is a fixed amount per paid ticket. The platform
     * commission is applied to the attendee total, matching event checkout.
     * Gateway processing fees are deliberately not estimated here because
     * they are reconciled from provider settlement data.
     *
     * @return array{
     *   ticket_price: float,
     *   quantity: int,
     *   ticket_sales: float,
     *   booking_fee_per_ticket: float,
     *   attendee_booking_fees: float,
     *   attendee_total: float,
     *   commission_percentage: float,
     *   platform_fee: float,
     *   organizer_net: float
     * }
     */
    public function quote(
        float $ticketPrice,
        int $quantity,
        float $bookingFeePerTicket,
        float $commissionPercentage
    ): array {
        $ticketPrice = round(max(0, $ticketPrice), 2);
        $quantity = max(1, min(100000, $quantity));

        return ['ticket_price' => $ticketPrice] + $this->quoteGross(
            round($ticketPrice * $quantity, 2),
            $quantity,
            $bookingFeePerTicket,
            $commissionPercentage
        );
    }

    /**
     * The booking fee on paid tickets: a percentage of the discounted ticket
     * sales plus a fixed amount for every paid ticket. When buyers pay it, the
     * fee is added at checkout and the organiser keeps the ticket sales. When
     * the organiser includes it, the fee is deducted from their sales.
     *
     * @return array{
     *   ticket_sales: float,
     *   booking_fee: float,
     *   attendee_booking_fees: float,
     *   attendee_total: float,
     *   commission: float,
     *   organizer_net: float
     * }
     */
    public function quotePercentage(
        float $ticketSales,
        int $paidQuantity,
        float $percentage,
        string $payer,
        float $fixedFeePerPaidTicket = self::FIXED_FEE_PER_PAID_TICKET,
    ): array
    {
        $ticketSales = round(max(0, $ticketSales), 2);
        $paidQuantity = max(0, min(100000, $paidQuantity));
        $percentage = round(min(100, max(0, $percentage)), 2);
        $fixedFeePerPaidTicket = round(max(0, $fixedFeePerPaidTicket), 2);
        $fee = $paidQuantity > 0 && $ticketSales > 0
            ? round(($ticketSales * $percentage / 100) + ($paidQuantity * $fixedFeePerPaidTicket), 2)
            : 0.0;
        $organizerPays = $payer === 'organizer';
        if ($organizerPays) {
            $fee = min($fee, $ticketSales);
        }

        return [
            'ticket_sales' => $ticketSales,
            'booking_fee' => $fee,
            'attendee_booking_fees' => $organizerPays ? 0.0 : $fee,
            'attendee_total' => $organizerPays ? $ticketSales : round($ticketSales + $fee, 2),
            'commission' => $organizerPays ? $fee : 0.0,
            'organizer_net' => $organizerPays ? round($ticketSales - $fee, 2) : $ticketSales,
        ];
    }

    /**
     * Calculate from an authoritative sales subtotal, including subtotals
     * changed by discounts that cannot be represented as one unit price.
     *
     * @return array{
     *   quantity: int,
     *   ticket_sales: float,
     *   booking_fee_per_ticket: float,
     *   attendee_booking_fees: float,
     *   attendee_total: float,
     *   commission_percentage: float,
     *   platform_fee: float,
     *   organizer_net: float
     * }
     */
    public function quoteGross(
        float $ticketSales,
        int $quantity,
        float $bookingFeePerTicket,
        float $commissionPercentage
    ): array {
        $ticketSales = round(max(0, $ticketSales), 2);
        $quantity = max(0, min(100000, $quantity));
        $bookingFeePerTicket = round(max(0, $bookingFeePerTicket), 2);
        $commissionPercentage = round(min(100, max(0, $commissionPercentage)), 4);

        $attendeeBookingFees = round($bookingFeePerTicket * $quantity, 2);
        $attendeeTotal = round($ticketSales + $attendeeBookingFees, 2);
        $platformFee = round($attendeeTotal * $commissionPercentage / 100, 2);

        return [
            'quantity' => $quantity,
            'ticket_sales' => $ticketSales,
            'booking_fee_per_ticket' => $bookingFeePerTicket,
            'attendee_booking_fees' => $attendeeBookingFees,
            'attendee_total' => $attendeeTotal,
            'commission_percentage' => $commissionPercentage,
            'platform_fee' => $platformFee,
            'organizer_net' => round(max(0, $ticketSales - $platformFee), 2),
        ];
    }
}
