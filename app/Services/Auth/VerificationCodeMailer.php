<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Services\Mail\AppMailer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VerificationCodeMailer
{
    public static function mailIsConfigured(): bool
    {
        return AppMailer::mailIsConfigured();
    }

    public static function send(string $name, string $email, string $code, int $expiryMinutes = 60): bool
    {
        return AppMailer::sendTemplate('otp_verification_email', $email, [
            '{{user_name}}' => $name !== '' ? $name : 'there',
            '{{otp_code}}' => $code,
            '{{otp_expiry}}' => (string) $expiryMinutes,
        ]);
    }

    /**
     * Create a 6-digit code and send it immediately over SMTP (same request).
     *
     * @return array{0: ?string, 1: bool, 2: bool} [devCode, sent, mailConfigured]
     */
    public static function issueAndSend(User $user, int $expiryMinutes = 60): array
    {
        $email = strtolower((string) $user->email);
        $code = (string) random_int(100000, 999999);

        DB::table('email_verification_codes')->updateOrInsert(
            ['email' => $email],
            [
                'email' => $email,
                'code_hash' => hash('sha256', $code),
                'attempts' => 0,
                'expires_at' => now()->addMinutes($expiryMinutes),
                'created_at' => now(),
            ]
        );

        $mailConfigured = self::mailIsConfigured();
        $sent = false;

        // Send immediately in this request (before any redirect).
        if ($mailConfigured) {
            try {
                $sent = self::send((string) ($user->name ?? ''), $email, $code, $expiryMinutes);
            } catch (\Throwable $e) {
                Log::warning('Signup OTP send failed', [
                    'email' => $email,
                    'error' => $e->getMessage(),
                ]);
                $sent = false;
            }

            // One immediate retry on transient SMTP failure (same code).
            if (! $sent) {
                try {
                    usleep(250000);
                    $sent = self::send((string) ($user->name ?? ''), $email, $code, $expiryMinutes);
                } catch (\Throwable $e) {
                    Log::warning('Signup OTP retry failed', [
                        'email' => $email,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        $devCode = null;
        if (! $mailConfigured || (! $sent && config('app.debug'))) {
            $devCode = $code;
        }

        return [$devCode, $sent, $mailConfigured];
    }
}
