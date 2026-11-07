<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\PasswordResetCodeNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class PasswordResetCodeService
{
    public const CODE_LENGTH = 6;

    public const EXPIRES_MINUTES = 10;

    public const MAX_ATTEMPTS = 5;

    /**
     * Issue a fresh 6-digit code for an email when the account exists.
     * Always returns the same public outcome so emails are not enumerated.
     */
    public function send(string $email): void
    {
        $normalized = $this->normalizeEmail($email);
        $user = User::query()->where('email', $normalized)->first();

        DB::table('password_reset_codes')->where('email', $normalized)->delete();

        if ($user === null) {
            return;
        }

        $code = $this->generateCode();

        DB::table('password_reset_codes')->insert([
            'email' => $normalized,
            'code' => Hash::make($code),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(self::EXPIRES_MINUTES),
            'verified_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user->notify(new PasswordResetCodeNotification($code, self::EXPIRES_MINUTES));
    }

    /**
     * @throws ValidationException
     */
    public function verify(string $email, string $code): void
    {
        $normalized = $this->normalizeEmail($email);
        $record = DB::table('password_reset_codes')
            ->where('email', $normalized)
            ->orderByDesc('id')
            ->first();

        if ($record === null) {
            throw ValidationException::withMessages([
                'code' => 'Enter a valid reset code, or request a new one.',
            ]);
        }

        if ($record->verified_at !== null) {
            return;
        }

        if (now()->greaterThan($record->expires_at)) {
            DB::table('password_reset_codes')->where('id', $record->id)->delete();

            throw ValidationException::withMessages([
                'code' => 'This code has expired. Request a new one.',
            ]);
        }

        if ((int) $record->attempts >= self::MAX_ATTEMPTS) {
            DB::table('password_reset_codes')->where('id', $record->id)->delete();

            throw ValidationException::withMessages([
                'code' => 'Too many incorrect attempts. Request a new code.',
            ]);
        }

        if (! Hash::check($code, $record->code)) {
            DB::table('password_reset_codes')
                ->where('id', $record->id)
                ->update([
                    'attempts' => (int) $record->attempts + 1,
                    'updated_at' => now(),
                ]);

            throw ValidationException::withMessages([
                'code' => 'That code is incorrect.',
            ]);
        }

        DB::table('password_reset_codes')
            ->where('id', $record->id)
            ->update([
                'verified_at' => now(),
                'updated_at' => now(),
            ]);
    }

    public function hasVerifiedCode(string $email): bool
    {
        $normalized = $this->normalizeEmail($email);

        $record = DB::table('password_reset_codes')
            ->where('email', $normalized)
            ->whereNotNull('verified_at')
            ->where('expires_at', '>', now())
            ->orderByDesc('id')
            ->first();

        return $record !== null;
    }

    public function clear(string $email): void
    {
        DB::table('password_reset_codes')
            ->where('email', $this->normalizeEmail($email))
            ->delete();
    }

    private function generateCode(): string
    {
        return str_pad((string) random_int(0, 999999), self::CODE_LENGTH, '0', STR_PAD_LEFT);
    }

    private function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }
}
