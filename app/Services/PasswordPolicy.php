<?php

namespace App\Services;

use Illuminate\Validation\Rules\Password;

class PasswordPolicy
{
    public static function rule(): Password
    {
        return Password::min(8)->letters()->mixedCase()->numbers();
    }
}
