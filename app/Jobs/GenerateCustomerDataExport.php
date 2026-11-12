<?php

namespace App\Jobs;

use App\Models\Customer;
use App\Models\DataExport;
use App\Models\Event\Booking;
use App\Models\Event\BookingTicket;
use App\Models\Event\Wishlist;
use App\Models\ShopManagement\ProductOrder;
use App\Services\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;
use ZipArchive;

class GenerateCustomerDataExport implements ShouldQueue, ShouldBeEncrypted, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 4;
    public int $timeout = 900;
    public int $uniqueFor = 3600;

    public function __construct(public int $exportId)
    {
        $this->onQueue('default');
    }

    public function uniqueId(): string
    {
        return 'customer-data-export-'.$this->exportId;
    }

    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping($this->uniqueId()))->expireAfter($this->timeout + 60)];
    }

    public function handle(): void
    {
        $export = DataExport::findOrFail($this->exportId);
        if ($export->status === 'ready' && $export->path && Storage::disk($export->disk)->exists($export->path)) {
            return;
        }
        if ($export->export_type !== 'customer_personal_data'
            || $export->requested_by_type !== 'customer'
            || $export->format !== 'zip') {
            throw new RuntimeException('The export type does not match this generator.');
        }
        if ($export->expires_at && $export->expires_at->isPast()) {
            $export->update(['status' => 'expired']);
            return;
        }

        $export->forceFill([
            'status' => 'processing',
            'started_at' => $export->started_at ?: now(),
            'attempts' => (int) $export->attempts + 1,
            'last_error' => null,
        ])->save();

        try {
            $customerId = (int) $export->requested_by_id;
            $customer = Customer::query()->findOrFail($customerId);
            $filters = $export->filters ?: [];
            $payload = [
                'schema' => 'tica.customer-data.v1',
                'requested_at' => $filters['requested_at'] ?? $export->created_at?->toIso8601String(),
                'generated_at' => now()->toIso8601String(),
                'account' => $this->account($customer),
                'event_bookings' => $this->bookings($customerId, (int) ($filters['max_booking_id'] ?? 0)),
                'held_tickets' => $this->heldTickets($customerId, (int) ($filters['max_booking_ticket_id'] ?? 0)),
                'product_orders' => $this->orders($customerId, (int) ($filters['max_product_order_id'] ?? 0)),
                'wishlist' => $this->wishlist($customerId),
                'support_tickets' => $this->supportTickets($customerId),
                'product_reviews' => $this->productReviews($customerId),
            ];
            $archive = $this->archive($payload);
            if (!Storage::disk($export->disk)->put($export->path, $archive)) {
                throw new RuntimeException('The personal-data export writer did not produce a file.');
            }

            DB::transaction(function () use ($export): void {
                $locked = DataExport::query()->whereKey($export->id)->lockForUpdate()->firstOrFail();
                $before = $locked->only(['status', 'attempts']);
                $locked->forceFill([
                    'status' => 'ready',
                    'completed_at' => now(),
                    'last_error' => null,
                ])->save();
                AuditLogger::record('customer_data_export.ready', $locked, $before, $locked->only([
                    'status', 'attempts', 'row_count', 'completed_at', 'expires_at',
                ]));
            });
        } catch (Throwable $exception) {
            $export->forceFill([
                'status' => 'failed',
                'last_error' => mb_substr($exception->getMessage(), 0, 2000),
            ])->save();
            throw $exception;
        }
    }

    public function failed(Throwable $exception): void
    {
        $export = DataExport::find($this->exportId);
        if ($export && $export->status !== 'ready') {
            $export->forceFill([
                'status' => 'failed',
                'last_error' => mb_substr($exception->getMessage(), 0, 2000),
            ])->save();
        }
    }

    private function account(Customer $customer): array
    {
        return [
            'id' => $customer->id,
            'first_name' => $customer->fname,
            'last_name' => $customer->lname,
            'username' => $customer->username,
            'email' => $customer->email,
            'phone' => $customer->phone,
            'address' => $customer->address,
            'country' => $customer->country,
            'state' => $customer->state,
            'city' => $customer->city,
            'postal_code' => $customer->zip_code,
            'gender' => $customer->gender,
            'email_verified_at' => $customer->email_verified_at?->toIso8601String(),
            'created_at' => $customer->created_at?->toIso8601String(),
            'updated_at' => $customer->updated_at?->toIso8601String(),
        ];
    }

    private function bookings(int $customerId, int $maxId): array
    {
        if (!Schema::hasTable('bookings') || $maxId < 1) {
            return [];
        }

        return Booking::query()
            ->with(['event', 'ticketUnits.events', 'ticketUnits.transfers'])
            ->where('customer_id', $customerId)
            ->where('id', '<=', $maxId)
            ->orderBy('id')
            ->get()
            ->map(fn (Booking $booking): array => [
                'id' => $booking->id,
                'booking_reference' => $booking->booking_id,
                'event_id' => $booking->event_id,
                'event_title' => $booking->event?->title,
                'event_date' => $this->isoDate($booking->event_date),
                'ticket_type' => $booking->variation,
                'quantity' => $booking->quantity,
                'price' => $booking->price,
                'tax' => $booking->tax,
                'discount' => $booking->discount,
                'currency' => $booking->currencyText,
                'payment_method' => $booking->paymentMethod,
                'payment_status' => $booking->paymentStatus,
                'refund_status' => $booking->refund_status,
                'refunded_amount' => $booking->refunded_amount,
                'buyer' => [
                    'first_name' => $booking->fname,
                    'last_name' => $booking->lname,
                    'email' => $booking->email,
                    'phone' => $booking->phone,
                    'address' => $booking->address,
                    'city' => $booking->city,
                    'state' => $booking->state,
                    'country' => $booking->country,
                    'postal_code' => $booking->zip_code,
                ],
                'created_at' => $booking->created_at?->toIso8601String(),
                'updated_at' => $booking->updated_at?->toIso8601String(),
            ])->all();
    }

    private function orders(int $customerId, int $maxId): array
    {
        if (!Schema::hasTable('product_orders') || $maxId < 1) {
            return [];
        }

        return ProductOrder::query()
            ->with('order_items')
            ->where('user_id', $customerId)
            ->where('id', '<=', $maxId)
            ->orderBy('id')
            ->get()
            ->map(fn (ProductOrder $order): array => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'invoice_number' => $order->invoice_number,
                'subtotal' => $order->cart_total,
                'discount' => $order->discount,
                'tax' => $order->tax,
                'shipping_charge' => $order->shipping_charge,
                'total' => $order->total,
                'currency' => $order->currency_text,
                'payment_method' => $order->method,
                'payment_status' => $order->payment_status,
                'order_status' => $order->order_status,
                'refund_status' => $order->refund_status,
                'refunded_amount' => $order->refunded_amount,
                'billing' => $this->orderAddress($order, 'billing'),
                'shipping' => $this->orderAddress($order, 'shipping'),
                'items' => $order->order_items->map(fn ($item): array => [
                    'product_id' => $item->product_id,
                    'title' => $item->title,
                    'sku' => $item->sku,
                    'quantity' => $item->qty,
                    'price' => $item->price,
                ])->all(),
                'created_at' => $order->created_at?->toIso8601String(),
                'updated_at' => $order->updated_at?->toIso8601String(),
            ])->all();
    }

    private function heldTickets(int $customerId, int $maxId): array
    {
        if (!Schema::hasTable('booking_tickets') || $maxId < 1) {
            return [];
        }

        return BookingTicket::query()
            ->with(['booking.event', 'events', 'transfers'])
            ->where(function ($query) use ($customerId): void {
                $query->where('holder_customer_id', $customerId)
                    ->orWhereHas('booking', fn ($booking) => $booking->where('customer_id', $customerId));
            })
            ->where('id', '<=', $maxId)
            ->orderBy('id')
            ->get()
            ->map(fn (BookingTicket $ticket): array => [
                'id' => $ticket->public_id,
                'booking_reference' => $ticket->booking?->booking_id,
                'event_id' => $ticket->event_id,
                'event_title' => $ticket->booking?->event?->title,
                'sequence' => $ticket->sequence,
                'type' => $ticket->variation_name,
                'holder_name' => $ticket->holder_name,
                'holder_email' => $ticket->holder_email,
                'status' => $ticket->status,
                'checked_in_at' => $ticket->checked_in_at?->toIso8601String(),
                'revoked_at' => $ticket->revoked_at?->toIso8601String(),
                'transfer_history' => $ticket->transfers->map(fn ($transfer): array => [
                    'recipient_name' => $transfer->recipient_name,
                    'recipient_email' => $transfer->recipient_email,
                    'status' => $transfer->status,
                    'expires_at' => $transfer->expires_at?->toIso8601String(),
                    'accepted_at' => $transfer->accepted_at?->toIso8601String(),
                    'cancelled_at' => $transfer->cancelled_at?->toIso8601String(),
                ])->all(),
                'created_at' => $ticket->created_at?->toIso8601String(),
                'updated_at' => $ticket->updated_at?->toIso8601String(),
            ])->all();
    }

    private function orderAddress(ProductOrder $order, string $prefix): array
    {
        return [
            'first_name' => $order->{$prefix.'_fname'},
            'last_name' => $order->{$prefix.'_lname'},
            'email' => $order->{$prefix.'_email'},
            'phone' => $order->{$prefix.'_phone'},
            'address' => $order->{$prefix.'_address'},
            'city' => $order->{$prefix.'_city'},
            'state' => $order->{$prefix.'_state'},
            'country' => $order->{$prefix.'_country'},
            'postal_code' => $order->{$prefix.'_zip_code'},
        ];
    }

    private function wishlist(int $customerId): array
    {
        if (!Schema::hasTable('wishlists')) return [];

        return Wishlist::query()->where('customer_id', $customerId)->orderBy('id')->get()
            ->map(fn ($item): array => [
                'event_id' => $item->event_id,
                'created_at' => $item->created_at?->toIso8601String(),
            ])->all();
    }

    private function supportTickets(int $customerId): array
    {
        if (!Schema::hasTable('support_tickets')) return [];

        return DB::table('support_tickets')
            ->where('user_id', $customerId)
            ->where('user_type', 'customer')
            ->orderBy('id')
            ->get()
            ->map(function ($ticket): array {
                $messages = Schema::hasTable('conversations')
                    ? DB::table('conversations')->where('support_ticket_id', $ticket->id)->orderBy('id')->get()
                    : collect();

                return [
                    'id' => $ticket->id,
                    'email' => $ticket->email,
                    'subject' => $ticket->subject,
                    'description' => $ticket->description,
                    'status' => $ticket->status,
                    'messages' => $messages->map(fn ($message): array => [
                        'sender_type' => $message->type,
                        'message' => $message->reply,
                        'created_at' => $this->isoDate($message->created_at),
                    ])->all(),
                    'created_at' => $this->isoDate($ticket->created_at),
                    'updated_at' => $this->isoDate($ticket->updated_at),
                ];
            })->all();
    }

    private function productReviews(int $customerId): array
    {
        if (!Schema::hasTable('product_reviews')) return [];

        return DB::table('product_reviews')->where('user_id', $customerId)->orderBy('id')->get()
            ->map(fn ($review): array => [
                'product_id' => $review->product_id,
                'rating' => $review->review,
                'comment' => $review->comment,
                'created_at' => $this->isoDate($review->created_at),
                'updated_at' => $this->isoDate($review->updated_at),
            ])->all();
    }

    private function isoDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return CarbonImmutable::parse($value)->toIso8601String();
    }

    private function archive(array $payload): string
    {
        $temporaryPath = tempnam(sys_get_temp_dir(), 'tica-customer-data-');
        if ($temporaryPath === false) {
            throw new RuntimeException('Unable to prepare a private export archive.');
        }

        try {
            $zip = new ZipArchive();
            if ($zip->open($temporaryPath, ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Unable to create the private export archive.');
            }

            $zip->addFromString('README.txt', implode("\n", [
                'Tica personal data export',
                'Schema: '.$payload['schema'],
                'Requested at: '.$payload['requested_at'],
                'Generated at: '.$payload['generated_at'],
                '',
                'Every CSV is UTF-8, uses one row per record and contains stable record IDs where available.',
                'Sensitive authentication, QR-signing and payment-provider secret fields are intentionally excluded.',
            ])."\n");

            $zip->addFromString('account.csv', $this->csv([$payload['account']]));
            $zip->addFromString('event_bookings.csv', $this->csv(array_map(
                fn (array $booking): array => $this->flatten($booking, ['buyer']),
                $payload['event_bookings']
            ), [
                'id', 'booking_reference', 'event_id', 'event_title', 'event_date', 'ticket_type',
                'quantity', 'price', 'tax', 'discount', 'currency', 'payment_method', 'payment_status',
                'refund_status', 'refunded_amount', 'created_at', 'updated_at', 'buyer_first_name',
                'buyer_last_name', 'buyer_email', 'buyer_phone', 'buyer_address', 'buyer_city',
                'buyer_state', 'buyer_country', 'buyer_postal_code',
            ]));

            $ticketTransfers = [];
            $tickets = array_map(function (array $ticket) use (&$ticketTransfers): array {
                foreach ($ticket['transfer_history'] as $transfer) {
                    $ticketTransfers[] = ['ticket_id' => $ticket['id']] + $transfer;
                }
                unset($ticket['transfer_history']);
                return $ticket;
            }, $payload['held_tickets']);
            $zip->addFromString('tickets.csv', $this->csv($tickets, [
                'id', 'booking_reference', 'event_id', 'event_title', 'sequence', 'type', 'holder_name',
                'holder_email', 'status', 'checked_in_at', 'revoked_at', 'created_at', 'updated_at',
            ]));
            $zip->addFromString('ticket_transfers.csv', $this->csv($ticketTransfers, [
                'ticket_id', 'recipient_name', 'recipient_email', 'status', 'expires_at', 'accepted_at', 'cancelled_at',
            ]));

            $orderItems = [];
            $orders = array_map(function (array $order) use (&$orderItems): array {
                foreach ($order['items'] as $item) {
                    $orderItems[] = ['order_id' => $order['id'], 'order_number' => $order['order_number']] + $item;
                }
                unset($order['items']);
                return $this->flatten($order, ['billing', 'shipping']);
            }, $payload['product_orders']);
            $zip->addFromString('product_orders.csv', $this->csv($orders, [
                'id', 'order_number', 'invoice_number', 'subtotal', 'discount', 'tax', 'shipping_charge',
                'total', 'currency', 'payment_method', 'payment_status', 'order_status', 'refund_status',
                'refunded_amount', 'created_at', 'updated_at', 'billing_first_name', 'billing_last_name',
                'billing_email', 'billing_phone', 'billing_address', 'billing_city', 'billing_state',
                'billing_country', 'billing_postal_code', 'shipping_first_name', 'shipping_last_name',
                'shipping_email', 'shipping_phone', 'shipping_address', 'shipping_city', 'shipping_state',
                'shipping_country', 'shipping_postal_code',
            ]));
            $zip->addFromString('product_order_items.csv', $this->csv($orderItems, [
                'order_id', 'order_number', 'product_id', 'title', 'sku', 'quantity', 'price',
            ]));

            $supportMessages = [];
            $supportTickets = array_map(function (array $ticket) use (&$supportMessages): array {
                foreach ($ticket['messages'] as $message) {
                    $supportMessages[] = ['support_ticket_id' => $ticket['id']] + $message;
                }
                unset($ticket['messages']);
                return $ticket;
            }, $payload['support_tickets']);
            $zip->addFromString('support_tickets.csv', $this->csv($supportTickets, [
                'id', 'email', 'subject', 'description', 'status', 'created_at', 'updated_at',
            ]));
            $zip->addFromString('support_messages.csv', $this->csv($supportMessages, [
                'support_ticket_id', 'sender_type', 'message', 'created_at',
            ]));
            $zip->addFromString('wishlist.csv', $this->csv($payload['wishlist'], ['event_id', 'created_at']));
            $zip->addFromString('product_reviews.csv', $this->csv($payload['product_reviews'], [
                'product_id', 'rating', 'comment', 'created_at', 'updated_at',
            ]));

            if (!$zip->close()) {
                throw new RuntimeException('Unable to finalize the private export archive.');
            }
            $contents = file_get_contents($temporaryPath);
            if ($contents === false || $contents === '') {
                throw new RuntimeException('The private export archive is empty.');
            }

            return $contents;
        } finally {
            @unlink($temporaryPath);
        }
    }

    private function flatten(array $row, array $nestedKeys): array
    {
        foreach ($nestedKeys as $nestedKey) {
            $nested = $row[$nestedKey] ?? [];
            unset($row[$nestedKey]);
            foreach ($nested as $key => $value) {
                $row[$nestedKey.'_'.$key] = $value;
            }
        }

        return $row;
    }

    private function csv(array $rows, ?array $headers = null): string
    {
        $headers ??= $rows ? array_keys($rows[0]) : [];
        $stream = fopen('php://temp', 'w+b');
        if ($stream === false) {
            throw new RuntimeException('Unable to prepare an export table.');
        }

        fwrite($stream, "\xEF\xBB\xBF");
        if ($headers) {
            fputcsv($stream, $headers, ',', '"', '');
        }
        foreach ($rows as $row) {
            fputcsv($stream, array_map(fn ($header) => $this->safeCsvValue($row[$header] ?? null), $headers), ',', '"', '');
        }
        rewind($stream);
        $contents = stream_get_contents($stream);
        fclose($stream);

        if ($contents === false) {
            throw new RuntimeException('Unable to generate an export table.');
        }

        return $contents;
    }

    private function safeCsvValue(mixed $value): mixed
    {
        if (!is_string($value)) {
            return $value;
        }
        if ($value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        return $value;
    }
}
