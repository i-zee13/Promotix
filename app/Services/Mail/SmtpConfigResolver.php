<?php

namespace App\Services\Mail;

use App\Models\AdminIntegrationSetting;
use App\Support\AdminIntegrationCatalog;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;

class SmtpConfigResolver
{
    /** Human-readable note from the last apply() call (for UI / diagnostics). */
    private static ?string $lastNote = null;

    /**
     * Apply Super Admin → Integrations → SMTP when .env is not configured for real delivery.
     */
    public static function apply(bool $force = false): bool
    {
        self::$lastNote = null;

        if (! $force && self::envSmtpIsConfigured()) {
            self::$lastNote = 'Using SMTP from .env (MAIL_*).';

            return false;
        }

        $integration = self::resolveEnabledSmtpIntegration();
        if ($integration === null) {
            self::$lastNote = 'No enabled SMTP integration found for a platform admin. Open Super Admin → Integrations → SMTP, fill host/port/credentials, toggle ON, and Save.';

            return false;
        }

        $settings = is_array($integration->settings) ? $integration->settings : [];
        $secrets = self::decryptSecrets($integration);

        return self::applyFromSettings($settings, $secrets);
    }

    /**
     * @param  array<string, mixed>  $settings
     * @param  array<string, string>  $secrets
     */
    public static function applyFromSettings(array $settings, array $secrets): bool
    {
        $host = trim((string) ($settings['host'] ?? ''));
        if ($host === '') {
            self::$lastNote = 'SMTP host is empty.';

            return false;
        }

        $port = (int) ($settings['port'] ?? 587);
        if ($port <= 0) {
            $port = 587;
        }

        $username = trim((string) ($settings['username'] ?? ''));
        $password = (string) ($secrets['password'] ?? '');
        $fromEmail = trim((string) ($settings['from_email'] ?? ''));
        if ($fromEmail === '' && $username !== '' && filter_var($username, FILTER_VALIDATE_EMAIL)) {
            $fromEmail = $username;
        }
        $encryption = strtolower(trim((string) ($settings['encryption'] ?? '')));

        $scheme = match ($encryption) {
            'ssl', 'smtps' => 'smtps',
            'tls', 'starttls', '' => $port === 465 ? 'smtps' : null,
            default => $port === 465 ? 'smtps' : null,
        };

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => $host,
            'mail.mailers.smtp.port' => $port,
            'mail.mailers.smtp.username' => $username !== '' ? $username : null,
            'mail.mailers.smtp.password' => $password !== '' ? $password : null,
            'mail.mailers.smtp.scheme' => $scheme,
        ]);

        if ($fromEmail !== '') {
            config([
                'mail.from.address' => $fromEmail,
                'mail.from.name' => (string) (config('mail.from.name') ?: config('app.name', 'Clickronix')),
            ]);
        }

        self::$lastNote = "Using SMTP integration ({$host}:{$port}).";

        return true;
    }

    /**
     * @param  array<string, mixed>  $settings
     * @param  array<string, string>  $secrets
     */
    public static function validateSettings(array $settings, array $secrets): ?string
    {
        $host = strtolower(trim((string) ($settings['host'] ?? '')));
        $username = trim((string) ($settings['username'] ?? ''));
        $password = trim((string) ($secrets['password'] ?? ''));
        $port = (int) ($settings['port'] ?? 587);

        if ($host === '') {
            return 'SMTP host is required.';
        }

        if (str_contains($host, 'mailgun') && preg_match('/@(gmail|googlemail)\./i', $username)) {
            return 'Host is Mailgun but username is Gmail — authentication will fail. Use Mailgun SMTP login (e.g. postmaster@mg.yourdomain.com) and Mailgun SMTP password, not Gmail credentials.';
        }

        if (str_contains($host, 'sendgrid') && $username !== '' && strtolower($username) !== 'apikey') {
            return 'SendGrid SMTP username must be apikey (password = your SendGrid API key).';
        }

        if (str_contains($host, 'gmail') && in_array($port, [587, 465, 25], true)) {
            return 'smtp.gmail.com is blocked on DigitalOcean and many VPS hosts (connection timeout). Use Mailgun or SendGrid on port 2525 instead.';
        }

        if ($username !== '' && $password === '') {
            return 'SMTP password is missing. Re-enter the password, click Save, then Test.';
        }

        $from = trim((string) ($settings['from_email'] ?? ''));
        if ($from === '' || $from === 'hello@example.com') {
            return 'Set a valid From email (verified sender for your SMTP provider).';
        }

        return null;
    }

    public static function lastNote(): ?string
    {
        return self::$lastNote;
    }

    /** Return a validation error message, or null when config looks send-ready. */
    public static function readinessError(): ?string
    {
        $host = trim((string) config('mail.mailers.smtp.host', ''));
        if ($host === '' || in_array($host, ['127.0.0.1', 'localhost'], true)) {
            return self::$lastNote ?: 'SMTP host is not configured.';
        }

        $username = trim((string) config('mail.mailers.smtp.username', ''));
        $password = (string) config('mail.mailers.smtp.password', '');
        if ($username !== '' && $password === '') {
            return 'SMTP username is set but password is missing. Re-open Integrations → SMTP, re-enter the password, Save, then test again.';
        }

        $from = trim((string) config('mail.from.address', ''));
        if ($from === '' || $from === 'hello@example.com') {
            return 'Set a valid From email in Integrations → SMTP (usually the same as your SMTP username).';
        }

        return self::validateSettings(
            [
                'host' => $host,
                'port' => config('mail.mailers.smtp.port'),
                'username' => $username,
                'from_email' => $from,
            ],
            ['password' => $password]
        );
    }

    public static function envSmtpIsConfigured(): bool
    {
        $mailer = strtolower(trim((string) env('MAIL_MAILER', 'log')));
        if ($mailer !== 'smtp') {
            return false;
        }

        $host = trim((string) env('MAIL_HOST', ''));
        if ($host === '' || in_array($host, ['127.0.0.1', 'localhost', 'mailpit', 'mailhog'], true)) {
            return false;
        }

        return true;
    }

    private static function resolveEnabledSmtpIntegration(): ?AdminIntegrationSetting
    {
        return AdminIntegrationCatalog::platformIntegrationSetting('smtp', true);
    }

    /** @return array<string, mixed> */
    public static function diagnosticSummary(): array
    {
        self::apply(true);

        return [
            'env_smtp' => self::envSmtpIsConfigured(),
            'integration_found' => self::resolveEnabledSmtpIntegration() !== null,
            'last_note' => self::$lastNote,
            'mailer' => config('mail.default'),
            'host' => config('mail.mailers.smtp.host'),
            'port' => config('mail.mailers.smtp.port'),
            'scheme' => config('mail.mailers.smtp.scheme'),
            'username_len' => strlen((string) config('mail.mailers.smtp.username')),
            'password_len' => strlen((string) config('mail.mailers.smtp.password')),
            'from' => config('mail.from.address'),
            'readiness_error' => self::readinessError(),
        ];
    }

    /** @return array<string, string> */
    public static function decryptSecretsFor(AdminIntegrationSetting $integration): array
    {
        return self::decryptSecrets($integration);
    }

    /** @return array<string, string> */
    private static function decryptSecrets(AdminIntegrationSetting $integration): array
    {
        if (! $integration->secret_payload) {
            return [];
        }

        try {
            $payload = json_decode(Crypt::decryptString($integration->secret_payload), true);

            return is_array($payload) ? $payload : [];
        } catch (\Throwable) {
            return [];
        }
    }
}
