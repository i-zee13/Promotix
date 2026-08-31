<?php

namespace App\Services\Mail;

use App\Models\EmailLog;
use App\Models\EmailTemplate;
use App\Support\EmailTemplateDefaults;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AppMailer
{
    private static ?string $lastError = null;

    public static function lastError(): ?string
    {
        return self::$lastError;
    }

    public static function humanizeSmtpError(?string $error): ?string
    {
        if ($error === null || trim($error) === '') {
            return null;
        }

        $lower = strtolower($error);

        if (str_contains($lower, 'getaddrinfo') || str_contains($lower, 'name resolution') || str_contains($lower, 'php_network_getaddresses')) {
            return $error.' — SMTP host is wrong or misspelled (e.g. smtp.gmail.org does not exist; Gmail = smtp.gmail.com, Mailgun = smtp.mailgun.org).';
        }

        if (str_contains($lower, 'connection timed out') || str_contains($lower, 'could not be established with host')) {
            $host = strtolower((string) config('mail.mailers.smtp.host', ''));
            $port = (int) config('mail.mailers.smtp.port', 0);

            if (str_contains($host, 'gmail') && $port === 2525) {
                return 'Cannot use Gmail on port 2525 — Gmail only supports 587/465 (blocked on DigitalOcean). Switch to Mailgun: host smtp.mailgun.org, port 2525, Mailgun SMTP username/password (not Gmail).';
            }

            return $error.' — Your server cannot reach outbound SMTP (common on DigitalOcean: ports 25, 587, and 465 are blocked). '
                .'Use a relay on port 2525 (e.g. Mailgun/SendGrid SMTP), an HTTP mail API (Mailgun/Postmark/SES), or ask your host to unblock SMTP.';
        }

        if (str_contains($lower, 'authentication') || str_contains($lower, '535') || str_contains($lower, 'username and password not accepted')) {
            $host = strtolower((string) config('mail.mailers.smtp.host', ''));

            if (str_contains($host, 'mailgun')) {
                return $error.' — Mailgun rejected login. Username must be your Mailgun SMTP user (postmaster@…mailgun.org), password = Mailgun SMTP password — not Gmail.';
            }

            return $error.' — Check SMTP username/password. Gmail needs an App Password; Mailgun/SendGrid need their own SMTP credentials.';
        }

        return $error;
    }

    public static function mailIsConfigured(): bool
    {
        $mailer = config('mail.default', 'log');

        return ! in_array($mailer, ['log', 'array', 'null'], true);
    }

    /**
     * Send using a DB/default email template key, with {{token}} replacements.
     *
     * @param  array<string, string>  $replacements
     */
    public static function sendTemplate(string $key, string $to, array $replacements = [], ?string $fallbackSubject = null, ?string $fallbackBody = null): bool
    {
        $defaults = EmailTemplateDefaults::forKey($key) ?? [
            'subject' => $fallbackSubject ?? ((string) config('app.name', 'Promotix')),
            'body' => $fallbackBody ?? '',
        ];

        $template = EmailTemplate::query()->where('key', $key)->first();

        if ($template && $template->is_active === false) {
            Log::info('Email template skipped (inactive)', [
                'key' => $key,
                'to' => $to,
            ]);

            return false;
        }

        $subject = self::replaceTokens($template?->subject ?: $defaults['subject'], $replacements);
        $body = self::replaceTokens($template?->body ?: $defaults['body'], $replacements);

        return self::sendRaw($to, $subject, $body, $key);
    }

    /**
     * Replace {{tokens}} the same way production sends do (for Settings test mail).
     *
     * @param  array<string, string>  $replacements
     */
    public static function renderTokens(string $text, array $replacements = []): string
    {
        return self::replaceTokens($text, $replacements);
    }

    public static function sendRaw(string $to, string $subject, string $body, string $context = 'mail'): bool
    {
        self::$lastError = null;

        try {
            $plain = trim(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $body)));
            $html = self::wrapHtmlEmail($subject, $body);

            Mail::send([], [], function ($message) use ($to, $subject, $plain, $html): void {
                $message->to($to)->subject($subject);
                $message->text($plain);
                $message->html($html);

                $fromAddress = config('mail.from.address');
                $fromName = config('mail.from.name');
                if ($fromAddress) {
                    $message->from($fromAddress, $fromName ?: null);
                }
            });

            Log::info('Outbound email accepted by mailer', [
                'context' => $context,
                'to' => $to,
                'subject' => $subject,
            ]);
            self::logEmail($context, $to, 'sent', null, ['subject' => $subject]);

            return true;
        } catch (\Throwable $e) {
            self::$lastError = $e->getMessage();
            Log::warning('Outbound email failed', [
                'context' => $context,
                'to' => $to,
                'error' => $e->getMessage(),
            ]);
            self::logEmail($context, $to, 'failed', $e->getMessage(), ['subject' => $subject]);

            return false;
        }
    }

    /** @param  array<string, mixed>  $meta */
    private static function logEmail(string $templateKey, string $recipient, string $status, ?string $error = null, array $meta = []): void
    {
        if (! Schema::hasTable('email_logs')) {
            return;
        }

        try {
            EmailLog::query()->create([
                'template_key' => $templateKey,
                'recipient' => $recipient,
                'status' => $status,
                'error' => $error,
                'meta' => $meta,
            ]);
        } catch (\Throwable) {
            // Never break outbound mail on logging failures.
        }
    }

    private static function wrapHtmlEmail(string $subject, string $body): string
    {
        $appName = e((string) config('app.name', 'Promotix'));
        $primary = e((string) (app_setting('branding.color_primary', '#6400B2') ?: '#6400B2'));
        $safeSubject = e($subject);

        // If body already looks like HTML, keep it; otherwise convert plain text.
        $content = Str::contains($body, ['<p', '<div', '<br', '<a ', '<table'])
            ? $body
            : nl2br(e($body), false);

        // Auto-link bare URLs in converted plain text.
        if (! Str::contains($body, ['<a ', '<p', '<div'])) {
            $content = preg_replace(
                '~(https?://[^\s<]+)~i',
                '<a href="$1" style="color:'.$primary.';font-weight:600;word-break:break-all;">$1</a>',
                $content
            ) ?: $content;
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>{$safeSubject}</title></head>
<body style="margin:0;padding:0;background:#f4f2f7;font-family:Inter,Segoe UI,Arial,sans-serif;color:#1a1a1a;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f4f2f7;padding:28px 12px;">
    <tr><td align="center">
      <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:560px;background:#ffffff;border:1px solid #d4c4e8;border-radius:12px;overflow:hidden;">
        <tr><td style="background:{$primary};padding:18px 24px;color:#ffffff;font-size:18px;font-weight:700;">{$appName}</td></tr>
        <tr><td style="padding:24px;font-size:14px;line-height:1.6;color:#2d2d3a;">{$content}</td></tr>
        <tr><td style="padding:0 24px 22px;font-size:12px;color:#6b6280;">If you did not expect this email, you can ignore it.</td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;
    }

    /** @param  array<string, string>  $replacements */
    private static function replaceTokens(string $text, array $replacements = []): string
    {
        $appName = (string) config('app.name', 'Promotix');
        $base = [
            '{{app_name}}' => $appName,
            '{{dashboard_url}}' => url('/dashboard'),
        ];

        $all = array_merge($base, $replacements);

        return str_replace(array_keys($all), array_values($all), $text);
    }
}
