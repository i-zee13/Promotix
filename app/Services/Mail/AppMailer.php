<?php

namespace App\Services\Mail;

use App\Models\EmailTemplate;
use App\Support\EmailTemplateDefaults;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AppMailer
{
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
        try {
            Mail::raw($body, function ($message) use ($to, $subject): void {
                $message->to($to)->subject($subject);

                $fromAddress = config('mail.from.address');
                $fromName = config('mail.from.name');
                if ($fromAddress) {
                    $message->from($fromAddress, $fromName ?: null);
                }
            });

            return true;
        } catch (\Throwable $e) {
            Log::warning('Outbound email failed', [
                'context' => $context,
                'to' => $to,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /** @param  array<string, string>  $replacements */
    private static function replaceTokens(string $text, array $replacements): string
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
