<?php

namespace Tests\Unit;

use App\Services\PasswordPolicy;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class PasswordPolicyTest extends TestCase
{
    public function test_policy_rejects_short_or_single_case_passwords(): void
    {
        foreach (['short1A', 'alllowercase1', 'ALLUPPERCASE1', 'NoNumbersHere'] as $password) {
            $validator = Validator::make(
                ['password' => $password, 'password_confirmation' => $password],
                ['password' => ['required', 'confirmed', PasswordPolicy::rule()]]
            );
            $this->assertTrue($validator->fails(), $password . ' should not pass the password policy.');
        }
    }

    public function test_policy_accepts_a_mixed_case_password_with_a_number(): void
    {
        $validator = Validator::make(
            ['password' => 'TicaSecure2026', 'password_confirmation' => 'TicaSecure2026'],
            ['password' => ['required', 'confirmed', PasswordPolicy::rule()]]
        );

        $this->assertFalse($validator->fails());
    }
}
