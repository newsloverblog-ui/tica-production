<?php

namespace App\Rules;

use App\Services\AccountTypeGuard;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Fails when an email already belongs to the other account type
 * ('customer' or 'organizer'). See AccountTypeGuard.
 */
class AccountTypeEmail implements ValidationRule
{
    public function __construct(private readonly string $as)
    {
    }

    public static function customer(): self
    {
        return new self(AccountTypeGuard::CUSTOMER);
    }

    public static function organizer(): self
    {
        return new self(AccountTypeGuard::ORGANIZER);
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value) || trim($value) === '') {
            return;
        }
        $message = app(AccountTypeGuard::class)->message($this->as, $value);
        if ($message) {
            $fail($message);
        }
    }
}
