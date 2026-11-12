<?php

namespace App\Services;

use App\Models\Event\Booking;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class BookingExportQueryService
{
    public function query(array $filters, ?int $organizerId = null): Builder
    {
        $languageId = (int) ($filters['language_id'] ?? 0);
        $maxBookingId = (int) ($filters['max_booking_id'] ?? 0);
        $paymentStatus = $filters['status'] ?? $filters['payment_status'] ?? null;
        $snapshotAt = !empty($filters['financial_snapshot_at'])
            ? CarbonImmutable::parse((string) $filters['financial_snapshot_at'])
            : CarbonImmutable::now();

        return Booking::query()
            ->join('event_contents', function ($join) use ($languageId) {
                $join->on('event_contents.event_id', '=', 'bookings.event_id');
                if ($languageId > 0) {
                    $join->where('event_contents.language_id', '=', $languageId);
                }
            })
            ->leftJoin('customers', 'customers.id', '=', 'bookings.customer_id')
            ->when($organizerId, fn ($query, $id) => $query->where('bookings.organizer_id', $id))
            ->when($maxBookingId > 0, fn ($query) => $query->where('bookings.id', '<=', $maxBookingId))
            ->when($filters['booking_id'] ?? null, fn ($query, $value) => $query->where('bookings.booking_id', 'like', '%' . $value . '%'))
            ->when($paymentStatus, fn ($query, $value) => $query->where('bookings.paymentStatus', $value))
            ->when($filters['payment_method'] ?? null, fn ($query, $value) => $query->where('bookings.paymentMethod', $value))
            ->when($filters['from_date'] ?? null, fn ($query, $value) => $query->whereDate('bookings.created_at', '>=', $value))
            ->when($filters['to_date'] ?? null, fn ($query, $value) => $query->whereDate('bookings.created_at', '<=', $value))
            ->when($filters['event_title'] ?? null, fn ($query, $value) => $query->where('event_contents.title', 'like', '%' . $value . '%'))
            ->select(
                'bookings.*',
                'event_contents.title',
                DB::raw("COALESCE(customers.fname, bookings.fname, '') as customerfname"),
                DB::raw("COALESCE(customers.lname, bookings.lname, '') as customerlname")
            )
            ->addSelect([
                'ledger_gateway' => DB::table('transactions')
                    ->select('gateway')->whereColumn('transactions.booking_id', 'bookings.id')
                    ->where('transcation_type', 1)->orderByDesc('transactions.id')->limit(1),
                'ledger_reference' => DB::table('transactions')
                    ->select('gateway_reference')->whereColumn('transactions.booking_id', 'bookings.id')
                    ->where('transcation_type', 1)->orderByDesc('transactions.id')->limit(1),
                'ledger_charged_amount' => DB::table('transactions')
                    ->select('grand_total')->whereColumn('transactions.booking_id', 'bookings.id')
                    ->where('transcation_type', 1)->orderByDesc('transactions.id')->limit(1),
                'snapshot_refunded_amount' => DB::table('payment_refunds')
                    ->selectRaw('COALESCE(SUM(amount), 0)')
                    ->whereColumn('payment_refunds.booking_id', 'bookings.id')
                    ->whereNotNull('financial_applied_at')->where('financial_applied_at', '<=', $snapshotAt),
                'settlement_id' => DB::table('gateway_settlement_entries as settlement_entries')
                    ->join('gateway_settlements as settlements', 'settlements.id', '=', 'settlement_entries.gateway_settlement_id')
                    ->select('settlements.gateway_settlement_id')
                    ->whereColumn('settlement_entries.booking_id', 'bookings.id')
                    ->where('settlement_entries.result', 'matched')
                    ->where('settlement_entries.checked_at', '<=', $snapshotAt)
                    ->orderByDesc('settlement_entries.checked_at')->limit(1),
                'settlement_status' => DB::table('gateway_settlement_entries as settlement_entries')
                    ->join('gateway_settlements as settlements', 'settlements.id', '=', 'settlement_entries.gateway_settlement_id')
                    ->select('settlements.reconciliation_status')
                    ->whereColumn('settlement_entries.booking_id', 'bookings.id')
                    ->where('settlement_entries.result', 'matched')
                    ->where('settlement_entries.checked_at', '<=', $snapshotAt)
                    ->orderByDesc('settlement_entries.checked_at')->limit(1),
                'settlement_date' => DB::table('gateway_settlement_entries as settlement_entries')
                    ->join('gateway_settlements as settlements', 'settlements.id', '=', 'settlement_entries.gateway_settlement_id')
                    ->select('settlements.settlement_date')
                    ->whereColumn('settlement_entries.booking_id', 'bookings.id')
                    ->where('settlement_entries.result', 'matched')
                    ->where('settlement_entries.checked_at', '<=', $snapshotAt)
                    ->orderByDesc('settlement_entries.checked_at')->limit(1),
                'settlement_gateway_fee' => DB::table('gateway_settlement_entries as settlement_entries')
                    ->select('settlement_entries.gateway_fee')
                    ->whereColumn('settlement_entries.booking_id', 'bookings.id')
                    ->where('settlement_entries.result', 'matched')
                    ->where('settlement_entries.checked_at', '<=', $snapshotAt)
                    ->orderByDesc('settlement_entries.checked_at')->limit(1),
            ])
            ->orderByDesc('bookings.id');
    }
}
