<?php

namespace App\Services;

use App\Jobs\SendEventBookingSms;
use App\Models\BookingDelivery;
use App\Models\Event;
use App\Models\Event\Booking;
use App\Models\Event\EventContent;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class TicketSmsService
{
    public function enabled(): bool
    {
        return (bool) config('ticket_delivery.sms.enabled', false);
    }

    public function dispatch(int $bookingId): ?BookingDelivery
    {
        if (!$this->enabled()) {
            return null;
        }

        $delivery = BookingDelivery::firstOrCreate(
            ['booking_id' => $bookingId, 'kind' => 'event_booking_sms'],
            ['channel' => 'sms', 'status' => 'pending']
        );

        if (!in_array($delivery->status, ['sent', 'cancelled', 'awaiting_event_review'], true)) {
            $this->dispatchDelivery($delivery);
        }

        return $delivery;
    }

    public function dispatchDelivery(BookingDelivery $delivery): bool
    {
        if ($delivery->kind !== 'event_booking_sms'
            || $delivery->status !== 'pending'
            || $delivery->processing_at !== null) {
            return false;
        }

        $delivery->forceFill([
            'dispatch_attempts' => (int) $delivery->dispatch_attempts + 1,
            'dispatch_last_attempt_at' => now(),
            'dispatch_last_error' => null,
        ])->save();

        try {
            $this->dispatchJob((int) $delivery->booking_id);
            return true;
        } catch (Throwable $exception) {
            $delivery->forceFill([
                'dispatch_last_error' => mb_substr(
                    'SMS queue scheduling failed: '.$exception->getMessage(),
                    0,
                    2000
                ),
            ])->save();

            throw $exception;
        }
    }

    protected function dispatchJob(int $bookingId): void
    {
        SendEventBookingSms::dispatch($bookingId)->afterCommit();
    }

    /**
     * @return array{provider_reference: string, recipient_hash: string}
     */
    public function send(Booking $booking): array
    {
        $configuration = $this->configuration();
        $phone = $this->normalizePhone((string) $booking->phone, $configuration['default_country_code']);
        $event = Event::query()->findOrFail((int) $booking->event_id);
        $eventName = trim((string) EventContent::query()
            ->where('event_id', $event->id)
            ->orderBy('id')
            ->value('title'));
        $url = app(BookingCompletionUrlService::class)->make((int) $booking->event_id, (int) $booking->id, 'sms');
        $message = Str::limit(
            'Tica: Your booking for '.($eventName !== '' ? $eventName : 'your event')
                .' is confirmed. View your ticket: '.$url,
            480,
            ''
        );

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->connectTimeout($configuration['connect_timeout_seconds'])
                ->timeout($configuration['timeout_seconds'])
                ->post(rtrim($configuration['base_url'], '/').'/api/sms/send', [
                    'api_key' => $configuration['api_key'],
                    'to' => $phone,
                    'from' => $configuration['sender_id'],
                    'sms' => $message,
                    'type' => 'plain',
                    'channel' => $configuration['channel'],
                ]);
        } catch (ConnectionException $exception) {
            throw new RuntimeException(
                'SMS provider outcome is ambiguous; verify provider activity before manually retrying.',
                previous: $exception
            );
        }

        if (!$response->successful()) {
            throw new RuntimeException('SMS provider rejected the delivery request with HTTP '.$response->status().'.');
        }

        $providerReference = trim((string) $response->json('message_id'));
        if ($providerReference === '') {
            throw new RuntimeException('SMS provider response did not contain a message identifier.');
        }

        return [
            'provider_reference' => mb_substr($providerReference, 0, 191),
            'recipient_hash' => hash_hmac('sha256', $phone, $this->recipientHashKey()),
        ];
    }

    public function normalizePhone(string $value, ?string $countryCode = null): string
    {
        $raw = trim($value);
        $international = str_starts_with($raw, '+');
        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
            $international = true;
        }

        $countryCode ??= (string) config('ticket_delivery.sms.default_country_code', '234');
        $countryCode = preg_replace('/\D+/', '', $countryCode) ?? '';
        if (!$international && str_starts_with($digits, '0')) {
            $digits = $countryCode.ltrim($digits, '0');
        }

        if (!preg_match('/^[1-9][0-9]{7,14}$/', $digits)) {
            throw new RuntimeException('The booking phone number cannot be delivered by SMS.');
        }

        return $digits;
    }

    /** @return array{base_url: string, api_key: string, sender_id: string, channel: string, default_country_code: string, connect_timeout_seconds: int, timeout_seconds: int} */
    private function configuration(): array
    {
        if (!$this->enabled() || config('ticket_delivery.sms.driver') !== 'termii') {
            throw new RuntimeException('Ticket SMS delivery is not configured.');
        }

        $baseUrl = rtrim(trim((string) config('ticket_delivery.sms.base_url')), '/');
        $apiKey = trim((string) config('ticket_delivery.sms.api_key'));
        $senderId = trim((string) config('ticket_delivery.sms.sender_id'));
        $channel = trim((string) config('ticket_delivery.sms.channel'));
        $countryCode = preg_replace('/\D+/', '', (string) config('ticket_delivery.sms.default_country_code')) ?? '';
        if (filter_var($baseUrl, FILTER_VALIDATE_URL) === false
            || strtolower((string) parse_url($baseUrl, PHP_URL_SCHEME)) !== 'https'
            || $apiKey === ''
            || !preg_match('/^[A-Za-z0-9]{2,11}$/', $senderId)
            || !in_array($channel, ['dnd', 'generic'], true)
            || !preg_match('/^[1-9][0-9]{0,3}$/', $countryCode)) {
            throw new RuntimeException('Ticket SMS provider configuration is invalid.');
        }

        return [
            'base_url' => $baseUrl,
            'api_key' => $apiKey,
            'sender_id' => $senderId,
            'channel' => $channel,
            'default_country_code' => $countryCode,
            'connect_timeout_seconds' => max(1, (int) config('ticket_delivery.sms.connect_timeout_seconds', 5)),
            'timeout_seconds' => max(2, (int) config('ticket_delivery.sms.timeout_seconds', 10)),
        ];
    }

    private function recipientHashKey(): string
    {
        $key = (string) config('tickets.buyer_identity_hash_key', config('app.key'));
        if (str_starts_with($key, 'base64:')) {
            $decoded = base64_decode(substr($key, 7), true);
            if ($decoded !== false) {
                return $decoded;
            }
        }

        return $key;
    }
}
