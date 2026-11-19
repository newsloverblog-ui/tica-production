<?php

namespace App\Exceptions;

use RuntimeException;

class PaystackInitializationException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly string $reason,
        private readonly int $providerStatus
    ) {
        parent::__construct($message, $providerStatus);
    }

    public static function fromProvider(int $status, string $message): self
    {
        $normalized = strtolower($message);
        $reason = match (true) {
            $status === 401 || str_contains($normalized, 'authorization') || str_contains($normalized, 'secret key') => 'invalid_credentials',
            str_contains($normalized, 'email') => 'invalid_email',
            str_contains($normalized, 'amount') => 'invalid_amount',
            str_contains($normalized, 'currency') => 'invalid_currency',
            str_contains($normalized, 'reference') => 'invalid_reference',
            str_contains($normalized, 'callback') || str_contains($normalized, 'url') => 'invalid_callback_url',
            str_contains($normalized, 'business') || str_contains($normalized, 'account') => 'account_restricted',
            default => 'provider_rejected',
        };

        return new self($message ?: 'Paystack rejected the payment initialization.', $reason, $status);
    }

    public function reason(): string
    {
        return $this->reason;
    }

    public function providerStatus(): int
    {
        return $this->providerStatus;
    }
}
