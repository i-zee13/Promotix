<?php

namespace App\Services\Auth;

use App\Services\Mail\AppMailer;

class VerificationCodeMailer
{
    public static function send(string $name, string $email, string $code, int $expiryMinutes = 60): bool
    {
        return AppMailer::sendTemplate('otp_verification_email', $email, [
            '{{user_name}}' => $name !== '' ? $name : 'there',
            '{{otp_code}}' => $code,
            '{{otp_expiry}}' => (string) $expiryMinutes,
        ]);
    }

    public static function mailIsConfigured(): bool
    {
        return AppMailer::mailIsConfigured();
    }
}
