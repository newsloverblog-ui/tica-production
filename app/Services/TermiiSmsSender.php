<?php

namespace App\Services;

use App\Contracts\SmsSender;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Termii SMS, using the same TERMII_* settings as ticket SMS. */
class TermiiSmsSender implements SmsSender
{
  public function configured(): bool
  {
    return trim((string) config('ticket_delivery.sms.api_key')) !== '';
  }

  public function send(string $phone, string $message): void
  {
    if (!$this->configured()) {
      throw new RuntimeException('SMS is not set up. Add TERMII_API_KEY.');
    }
    $to = app(TicketSmsService::class)->normalizePhone($phone);
    $response = Http::acceptJson()
      ->connectTimeout(max(1, (int) config('ticket_delivery.sms.connect_timeout_seconds', 5)))
      ->timeout(max(2, (int) config('ticket_delivery.sms.timeout_seconds', 10)))
      ->post(rtrim((string) config('ticket_delivery.sms.base_url', 'https://api.ng.termii.com'), '/') . '/api/sms/send', [
        'api_key' => (string) config('ticket_delivery.sms.api_key'),
        'to' => $to,
        'from' => (string) config('ticket_delivery.sms.sender_id', 'Tica'),
        'sms' => mb_substr($message, 0, 300),
        'type' => 'plain',
        'channel' => (string) config('ticket_delivery.sms.channel', 'dnd'),
      ]);
    if (!$response->successful()) {
      throw new RuntimeException('Termii refused the SMS: ' . mb_substr((string) ($response->json('message') ?? $response->status()), 0, 200));
    }
  }
}
