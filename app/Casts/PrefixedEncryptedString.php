<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use RuntimeException;

class PrefixedEncryptedString implements CastsAttributes
{
    public const PREFIX = 'enc:v1:';

    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        // Legacy plaintext remains readable only so the dry deployment window
        // can migrate safely. Release readiness blocks traffic until none is left.
        if (!str_starts_with((string) $value, self::PREFIX)) {
            return (string) $value;
        }

        return Crypt::decryptString(substr((string) $value, strlen(self::PREFIX)));
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        return self::encrypt((string) $value);
    }

    public static function encrypt(string $value): string
    {
        if (str_starts_with($value, self::PREFIX)) {
            try {
                Crypt::decryptString(substr($value, strlen(self::PREFIX)));

                return $value;
            } catch (RuntimeException) {
                // A user-controlled value that merely resembles the prefix is
                // plaintext and must be encrypted normally.
            }
        }

        return self::PREFIX.Crypt::encryptString($value);
    }

    public static function decryptForRollback(?string $value): ?string
    {
        if ($value === null || $value === '' || !str_starts_with($value, self::PREFIX)) {
            return $value;
        }

        return Crypt::decryptString(substr($value, strlen(self::PREFIX)));
    }
}
