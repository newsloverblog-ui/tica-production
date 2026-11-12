<?php

namespace App\Services;

use App\Models\BasicSettings\Basic;
use App\Models\Event\Booking;
use App\Models\Event\Ticket;
use App\Models\EventRegistrationInvoice;
use App\Models\EventRegistrationSubmission;
use Carbon\Carbon;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PDF;
use Throwable;

class EventRegistrationInvoiceService
{
    public function expireOverdue(int $limit = 200): int
    {
        $ids = EventRegistrationInvoice::query()->where('status', 'issued')
            ->where('due_at', '<=', now())->orderBy('id')->limit(max(1, $limit))->pluck('id');
        $expired = 0;
        foreach ($ids as $id) {
            $changed = DB::transaction(function () use ($id): bool {
                $invoice = EventRegistrationInvoice::whereKey($id)->lockForUpdate()->first();
                if (!$invoice || $invoice->status !== 'issued' || $invoice->due_at->isFuture()) return false;
                app(TicketReservationService::class)->release((string) $invoice->reservation_token, 'invoice_expired');
                $invoice->forceFill(['status' => 'expired', 'cancelled_at' => now()])->save();
                AuditLogger::record('event.registration_invoice.expired', $invoice, ['status' => 'issued'], ['status' => 'expired']);
                return true;
            }, 3);
            if ($changed) $expired++;
        }
        return $expired;
    }

    public function issue(EventRegistrationSubmission $submission, int $organizerId, array $data): EventRegistrationInvoice
    {
        $this->authorize($submission, $organizerId);
        $dueAt = !empty($data['due_at'])
            ? Carbon::parse((string) $data['due_at'])->endOfDay()
            : now()->addDays((int) config('registration.invoice_default_days', 14))->endOfDay();
        if ($dueAt->lessThanOrEqualTo(now()) || $dueAt->greaterThan(now()->addDays(90))) {
            throw ValidationException::withMessages(['due_at' => 'Choose an invoice due date within the next 90 days.']);
        }
        $poNumber = trim((string) ($data['purchase_order_number'] ?? ''));
        if (mb_strlen($poNumber) > 191) {
            throw ValidationException::withMessages(['purchase_order_number' => 'The purchase-order number cannot exceed 191 characters.']);
        }

        $created = false;
        $invoice = DB::transaction(function () use ($submission, $organizerId, $dueAt, $poNumber, &$created): EventRegistrationInvoice {
            $locked = EventRegistrationSubmission::with('attendees')->whereKey($submission->id)->lockForUpdate()->firstOrFail();
            $this->authorize($locked, $organizerId);
            if ($locked->status !== 'approved' || $locked->booking_id) {
                throw ValidationException::withMessages(['invoice' => 'Only an approved, unticketed registration can be invoiced.']);
            }
            $existing = EventRegistrationInvoice::where('submission_id', $locked->id)->lockForUpdate()->first();
            if ($existing && in_array($existing->status, ['issued', 'paid'], true)) {
                $created = $existing->status === 'issued' && !$existing->invoice_path;
                return $existing;
            }
            if ($locked->attendees->isEmpty() || $locked->attendees->contains(fn ($attendee) => !$attendee->ticket_id)) {
                throw ValidationException::withMessages(['invoice' => 'Every attendee needs a registration type before an invoice can be issued.']);
            }

            $allocations = $locked->attendees->groupBy(fn ($attendee): string => $this->allocationKey(
                (int) $attendee->ticket_id,
                $attendee->variation_name
            ));
            $ticketIds = $locked->attendees->pluck('ticket_id')->map(fn ($id) => (int) $id)->unique()->sort()->values();
            $tickets = Ticket::query()->where('event_id', $locked->event_id)
                ->whereIn('id', $ticketIds->all())->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            if ($tickets->count() !== $ticketIds->count()) {
                throw ValidationException::withMessages(['invoice' => 'One or more registration types are no longer available for this event.']);
            }

            $lineItems = [];
            $selections = [];
            $subtotal = 0.0;
            $paidQuantity = 0;
            foreach ($allocations as $attendees) {
                $attendee = $attendees->first();
                $ticket = $tickets->get((int) $attendee->ticket_id);
                $quantity = $attendees->count();
                $variationName = $attendee->variation_name ?: null;
                if ($ticket->pricing_type === 'variation') {
                    $option = collect(json_decode((string) $ticket->variations, true) ?: [])->first(
                        fn (array $candidate): bool => hash_equals((string) ($candidate['name'] ?? ''), (string) $variationName)
                    );
                    if (!$option) {
                        throw ValidationException::withMessages(['invoice' => 'A selected ticket option is no longer available. Review the attendee registration types.']);
                    }
                    $unitPrice = max(0, (float) ($option['price'] ?? 0));
                } else {
                    $variationName = null;
                    $unitPrice = $ticket->pricing_type === 'free' ? 0.0 : max(0, (float) $ticket->price);
                }
                $amount = round($unitPrice * (int) $quantity, 2);
                $subtotal += $amount;
                if ($unitPrice > 0) $paidQuantity += (int) $quantity;
                $lineItems[] = [
                    'ticket_id' => $ticket->id,
                    'title' => $variationName ? $ticket->title.' — '.$variationName : $ticket->title,
                    'variation_name' => $variationName,
                    'quantity' => (int) $quantity, 'unit_price' => $unitPrice, 'amount' => $amount,
                ];
                $selections[] = [
                    'ticket_id' => $ticket->id,
                    'name' => $variationName ?: $ticket->title,
                    'qty' => (int) $quantity,
                    'price' => $unitPrice,
                ];
            }
            $basic = Basic::query()->first();
            $tax = round(max(0, (float) ($basic?->tax ?? 0)) * $paidQuantity, 2);
            $total = round($subtotal + $tax, 2);
            $commission = round($total * max(0, (float) ($basic?->commission ?? 0)) / 100, 2);
            $reservationToken = app(TicketReservationService::class)->reserve(
                $locked->event_id,
                $selections,
                null,
                $locked->customer_id,
                $dueAt,
                true,
                $locked->primary_email_hash,
            );
            $number = 'TICA-COR-'.now()->format('Ym').'-'.strtoupper(substr((string) Str::ulid(), -8));
            $attributes = [
                'public_id' => (string) Str::ulid(), 'invoice_number' => $number,
                'submission_id' => $locked->id, 'form_id' => $locked->form_id,
                'event_id' => $locked->event_id, 'organizer_id' => $organizerId,
                'reservation_token' => $reservationToken, 'status' => 'issued',
                'currency' => (string) ($basic?->base_currency_text ?: 'NGN'),
                'currency_symbol' => (string) ($basic?->base_currency_symbol ?: '₦'),
                'subtotal' => $subtotal, 'tax' => $tax, 'commission' => $commission, 'total' => $total,
                'line_items' => $lineItems, 'purchase_order_number' => $poNumber !== '' ? $poNumber : null,
                'issued_at' => now(), 'due_at' => $dueAt,
            ];
            $existing ? $existing->forceFill($attributes)->save() : $existing = EventRegistrationInvoice::create($attributes);
            $created = true;
            AuditLogger::record('event.registration_invoice.issued', $existing, [], [
                'status' => 'issued', 'invoice_number' => $number, 'total' => $total,
                'currency' => $attributes['currency'], 'attendee_count' => $locked->attendees->count(),
            ], ['event_id' => $locked->event_id, 'submission_id' => $locked->id]);
            return $existing;
        }, 3);

        if (!$created || $invoice->invoice_path) return $invoice;
        try {
            $invoice->load('submission', 'event.information');
            $contents = PDF::loadView('frontend.event.registration-invoice', [
                'invoice' => $invoice, 'submission' => $invoice->submission,
                'eventTitle' => $invoice->event?->information?->title ?: 'Corporate registration',
            ])->output();
            $path = app(EventRegistrationInvoiceDocumentService::class)->store($invoice, $contents);
            $invoice->forceFill(['invoice_path' => $path])->save();
        } catch (Throwable $exception) {
            app(TicketReservationService::class)->release((string) $invoice->reservation_token, 'invoice_generation_failed');
            $invoice->forceFill(['status' => 'cancelled', 'cancelled_at' => now()])->save();
            throw $exception;
        }

        return $invoice->fresh();
    }

    public function markPaid(EventRegistrationInvoice $invoice, int $organizerId, array $data): EventRegistrationInvoice
    {
        $reference = trim((string) ($data['payment_reference'] ?? ''));
        $note = trim((string) ($data['payment_note'] ?? ''));
        if ($reference === '' || mb_strlen($reference) > 191) {
            throw ValidationException::withMessages(['payment_reference' => 'Enter the verified bank or payment reference.']);
        }
        if (mb_strlen($note) > 2000) {
            throw ValidationException::withMessages(['payment_note' => 'The payment note cannot exceed 2,000 characters.']);
        }

        $bookingId = null;
        $result = DB::transaction(function () use ($invoice, $organizerId, $reference, $note, &$bookingId): EventRegistrationInvoice {
            $locked = EventRegistrationInvoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            if ((int) $locked->organizer_id !== $organizerId) throw new AuthorizationException('You cannot manage another organizer’s invoice.');
            if ($locked->status === 'paid') return $locked;
            if ($locked->status !== 'issued' || $locked->due_at->isPast()) {
                throw ValidationException::withMessages(['invoice' => 'Only a current issued invoice can be marked paid.']);
            }
            $submission = EventRegistrationSubmission::with('attendees')->whereKey($locked->submission_id)->lockForUpdate()->firstOrFail();
            if ($submission->status !== 'approved' || $submission->booking_id) {
                throw ValidationException::withMessages(['invoice' => 'The registration is no longer eligible for ticket issue.']);
            }
            app(TicketReservationService::class)->consume(
                (string) $locked->reservation_token,
                $locked->event_id,
                true,
                $locked->customer_id,
                $locked->primary_email_hash,
            );

            $nameParts = preg_split('/\s+/', trim((string) $submission->primary_name), 2) ?: [];
            $booking = Booking::create([
                'customer_id' => $submission->customer_id, 'booking_id' => (string) Str::uuid(),
                'event_id' => $locked->event_id, 'organizer_id' => $organizerId,
                'ticket_id' => count($locked->line_items) === 1 ? $locked->line_items[0]['ticket_id'] : null,
                'fname' => $nameParts[0] ?? 'Registrant', 'lname' => $nameParts[1] ?? '',
                'email' => $submission->primary_email, 'phone' => '',
                'variation' => json_encode(array_map(fn (array $item): array => [
                    'ticket_id' => $item['ticket_id'], 'name' => $item['variation_name'],
                    'qty' => $item['quantity'], 'price' => $item['unit_price'],
                ], $locked->line_items)),
                'price' => $locked->total, 'tax' => $locked->tax, 'commission' => $locked->commission,
                'tax_percentage' => 0, 'commission_percentage' => 0,
                'quantity' => $submission->attendee_count, 'discount' => 0, 'early_bird_discount' => 0,
                'currencyText' => $locked->currency, 'currencyTextPosition' => 'right',
                'currencySymbol' => $locked->currency_symbol, 'currencySymbolPosition' => 'left',
                'paymentMethod' => 'Corporate invoice / PO', 'gatewayType' => 'offline',
                'paymentStatus' => 'completed', 'invoice' => null,
                'event_date' => $submission->event?->start_date,
            ]);
            $unitsByTicket = app(TicketIssuanceService::class)->issueForBooking($booking)
                ->groupBy(fn ($unit): string => $this->allocationKey((int) $unit->ticket_id, $unit->variation_name))
                ->map(fn ($units) => $units->values());
            foreach ($submission->attendees->values() as $attendee) {
                $allocationKey = $this->allocationKey((int) $attendee->ticket_id, $attendee->variation_name);
                $ticketUnits = $unitsByTicket->get($allocationKey, collect());
                $unit = $ticketUnits->shift();
                $unitsByTicket->put($allocationKey, $ticketUnits);
                if (!$unit) {
                    throw new DomainException('The issued ticket allocation does not match the approved attendees.');
                }
                $unit->forceFill(['holder_name' => $attendee->name, 'holder_email' => $attendee->email ?: $submission->primary_email])->save();
                $attendee->forceFill(['booking_ticket_id' => $unit->id, 'status' => 'ticketed'])->save();
            }
            $submission->forceFill(['booking_id' => $booking->id, 'status' => 'ticketed'])->save();
            $locked->forceFill([
                'status' => 'paid', 'booking_id' => $booking->id, 'payment_reference' => $reference,
                'payment_note' => $note !== '' ? $note : null, 'paid_at' => now(),
                'marked_paid_by_organizer_id' => $organizerId,
            ])->save();
            app(EventSaleAccountingService::class)->apply(
                $booking,
                'invoice_manual',
                $reference,
                (float) $booking->price
            );
            $bookingId = $booking->id;
            AuditLogger::record('event.registration_invoice.paid', $locked, ['status' => 'issued'], [
                'status' => 'paid', 'booking_id' => $booking->id, 'has_payment_note' => $note !== '',
            ], ['event_id' => $locked->event_id, 'submission_id' => $submission->id]);
            return $locked;
        }, 3);

        if ($bookingId) app(PaidBookingFulfillmentService::class)->dispatch($bookingId);
        return $result->fresh();
    }

    private function authorize(EventRegistrationSubmission $submission, int $organizerId): void
    {
        if ((int) $submission->organizer_id !== $organizerId) {
            throw new AuthorizationException('You cannot manage another organizer’s registration invoice.');
        }
    }

    private function allocationKey(int $ticketId, ?string $variationName): string
    {
        return $ticketId.'|'.($variationName ?? '');
    }
}
