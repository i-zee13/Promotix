<?php

namespace App\Support;

class EmailTemplateDefaults
{
    /** @return array{subject: string, body: string}|null */
    public static function forKey(string $key): ?array
    {
        return match ($key) {
            'welcome_email' => [
                'subject' => 'Welcome to {{app_name}}!',
                'body' => "Hi {{user_name}},\n\nYour account has been successfully created!\n\nHere's what you can do next:\n- Add your first domain\n- Enable tracking\n- Connect ad platforms\n- Configure alerts\n\nGet started: {{dashboard_url}}\n\nIf you need help, our support team is always here.\n\nBest,\n{{app_name}} Team",
            ],
            'otp_verification_email' => [
                'subject' => 'Your {{app_name}} Verification Code',
                'body' => "Hi {{user_name}},\n\nYour verification code is:\n\n{{otp_code}}\n\nThis code expires in {{otp_expiry}} minutes.\nIf you didn't request this, ignore this email.\n\n— {{app_name}} Security Team",
            ],
            'password_reset_email' => [
                'subject' => 'Reset Your {{app_name}} Password',
                'body' => "Hi {{user_name}},\n\nWe received a request to reset your password.\n\nClick the link below to continue:\n{{reset_url}}\n\nThis link expires in {{reset_expiry}} minutes.\nIf you didn't request this, ignore this email.\n\n— {{app_name}} Team",
            ],
            'payment_failed_email' => [
                'subject' => 'Payment Failed – Action Required',
                'body' => "Hi {{user_name}},\n\nWe couldn't process your payment for {{plan_name}}.\n\nReason:\n{{failure_reason}}\n\nTo avoid service interruption, update your payment method:\n{{billing_url}}\n\n— {{app_name}} Billing Team",
            ],
            'subscription_cancelled_email' => [
                'subject' => 'Your {{app_name}} Subscription Has Been Cancelled',
                'body' => "Hi {{user_name}},\n\nYour subscription to {{plan_name}} has been cancelled effective {{cancel_date}}.\n\nYou can resubscribe anytime here:\n{{billing_url}}\n\n— {{app_name}} Team",
            ],
            'security_alert_email' => [
                'subject' => 'Alert: {{alert_title}}',
                'body' => "Hi {{user_name}},\n\nWe detected the following activity:\n\n{{alert_message}}\n\nTime: {{event_time}}\nIP: {{ip_address}}\n\nIf this wasn't you, secure your account immediately:\n{{security_url}}\n\n— {{app_name}} Security",
            ],
            default => null,
        };
    }
}
