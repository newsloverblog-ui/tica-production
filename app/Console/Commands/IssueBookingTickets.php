<?php

namespace App\Console\Commands;

use App\Models\Event\Booking;
use App\Services\TicketIssuanceService;
use Illuminate\Console\Command;
use Throwable;

class IssueBookingTickets extends Command
{
    protected $signature = 'tickets:issue-booking-units {--booking= : Numeric database ID or public booking ID}';
    protected $description = 'Idempotently issue one traceable ticket unit for every admission in existing bookings';

    public function handle(TicketIssuanceService $issuer): int
    {
        $query = Booking::query()->orderBy('id');
        if ($identifier = $this->option('booking')) {
            $query->where(function ($builder) use ($identifier) {
                $builder->where('booking_id', $identifier);
                if (ctype_digit((string) $identifier)) {
                    $builder->orWhereKey((int) $identifier);
                }
            });
        }

        $issuedBookings = 0;
        $ticketUnits = 0;
        $failed = 0;

        $query->chunkById(200, function ($bookings) use ($issuer, &$issuedBookings, &$ticketUnits, &$failed) {
            foreach ($bookings as $booking) {
                try {
                    $before = $booking->ticketUnits()->count();
                    $units = $issuer->issueForBooking($booking);
                    if ($before === 0) {
                        $issuedBookings++;
                        $ticketUnits += $units->count();
                    }
                } catch (Throwable $exception) {
                    $failed++;
                    $this->error('Booking ' . $booking->booking_id . ': ' . $exception->getMessage());
                    report($exception);
                }
            }
        });

        $this->info("Issued {$ticketUnits} ticket units across {$issuedBookings} bookings; {$failed} failed.");
        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
