<?php

namespace App\Support;

use LogicException;

/**
 * Canonical accounting split for an event ticket sale.
 *
 * Legacy records store ticket sales in `price`/`grand_total`, buyer-paid
 * checkout fees in `tax`, and organizer-paid fees in `commission`. Keep those
 * records intact and derive every financial view from the same definitions.
 */
final class EventFinancialBreakdown
{
    /** @return array{ticket_sales:float,checkout_fee:float,organizer_fee:float,platform_revenue:float,customer_paid:float,organizer_revenue:float} */
    public static function fromBooking(object $booking): array
    {
        return self::calculate(
            (float) ($booking->price ?? 0),
            (float) ($booking->tax ?? 0),
            (float) ($booking->commission ?? 0),
        );
    }

    /** @return array{ticket_sales:float,checkout_fee:float,organizer_fee:float,platform_revenue:float,customer_paid:float,organizer_revenue:float} */
    public static function fromTransaction(object $transaction): array
    {
        return self::calculate(
            (float) ($transaction->grand_total ?? 0),
            (float) ($transaction->tax ?? 0),
            (float) ($transaction->commission ?? 0),
        );
    }

    /** @return array{ticket_sales:float,checkout_fee:float,organizer_fee:float,platform_revenue:float,customer_paid:float,organizer_revenue:float} */
    public static function calculate(float $ticketSales, float $checkoutFee, float $organizerFee): array
    {
        $ticketSales = round($ticketSales, 2);
        $checkoutFee = round($checkoutFee, 2);
        $organizerFee = round($organizerFee, 2);

        if ($ticketSales < 0 || $checkoutFee < 0 || $organizerFee < 0) {
            throw new LogicException('Event financial amounts cannot be negative.');
        }
        if ($organizerFee > $ticketSales) {
            throw new LogicException('Organizer-paid fees cannot exceed ticket sales.');
        }

        return [
            'ticket_sales' => $ticketSales,
            'checkout_fee' => $checkoutFee,
            'organizer_fee' => $organizerFee,
            'platform_revenue' => round($checkoutFee + $organizerFee, 2),
            'customer_paid' => round($ticketSales + $checkoutFee, 2),
            'organizer_revenue' => round($ticketSales - $organizerFee, 2),
        ];
    }

    public static function bookingOrganizerRevenueSql(string $table = 'bookings'): string
    {
        $prefix = self::safePrefix($table);

        return "COALESCE({$prefix}price, 0) - COALESCE({$prefix}commission, 0)";
    }

    public static function bookingCustomerPaidSql(string $table = 'bookings'): string
    {
        $prefix = self::safePrefix($table);

        return "COALESCE({$prefix}price, 0) + COALESCE({$prefix}tax, 0)";
    }

    public static function bookingPlatformRevenueSql(string $table = 'bookings'): string
    {
        $prefix = self::safePrefix($table);

        return "COALESCE({$prefix}tax, 0) + COALESCE({$prefix}commission, 0)";
    }

    private static function safePrefix(string $table): string
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $table)) {
            throw new LogicException('Invalid financial SQL table identifier.');
        }

        return $table.'.';
    }
}
