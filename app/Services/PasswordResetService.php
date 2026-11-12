<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PasswordResetService
{
    public function issue(Model $user): string
    {
        $email = (string) $user->email;
        $token = Str::random(64);

        DB::transaction(function () use ($email, $token, $user) {
            DB::table('password_resets')->where('email', $email)->delete();
            DB::table('password_resets')->insert([
                'email' => $email,
                'token' => Hash::make($token),
                'created_at' => now(),
            ]);
            AuditLogger::record('account.password_reset_requested', $user, [], [], [
                'account_type' => class_basename($user),
            ]);
        }, 3);

        return $token;
    }

    public function consume(string $modelClass, string $email, string $token, string $password): Model
    {
        return DB::transaction(function () use ($modelClass, $email, $token, $password) {
            $reset = DB::table('password_resets')
                ->where('email', $email)
                ->lockForUpdate()
                ->first();

            if (!$reset || !$reset->created_at || now()->diffInMinutes($reset->created_at) > 60 || !Hash::check($token, $reset->token)) {
                throw ValidationException::withMessages([
                    'token' => ['This password reset link is invalid or has expired.'],
                ]);
            }

            /** @var Model|null $user */
            $user = $modelClass::where('email', $email)->lockForUpdate()->first();
            if (!$user) {
                throw ValidationException::withMessages([
                    'token' => ['This password reset link is invalid or has expired.'],
                ]);
            }

            $user->password = Hash::make($password);
            $user->save();
            DB::table('password_resets')->where('email', $email)->delete();
            AuditLogger::record('account.password_reset_completed', $user, [], [], [
                'account_type' => class_basename($user),
            ]);

            return $user;
        }, 3);
    }
}
