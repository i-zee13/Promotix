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
     * Apply Super Admin → Integrations → SMTP when enabled; fall back to .env only otherwise.
     */
    public static function apply(bool $force = false): bool
    {
        self::$lastNote = null;

        $integration = self::resolveEnabledSmtpIntegration();
        if ($integration !== null) {
            $settings = is_array($integration->settings) ? $integration->settings : [];
            $secrets = self::decryptSecrets($integration);

            if ($force || self::integrationIsSendReady($settings, $secrets)) {
                if (self::applyFromSettings($settings, $secrets)) {
                    return true;
                }

                self::neutralizeEnvSmtpFallback();

                return false;
            }
        }

        if (! $force && self::envSmtpIsConfigured()) {
            self::$lastNote = 'Using SMTP from .env (MAIL_*). Disable or leave Integrations → SMTP off to keep .env; otherwise fill Integrations and toggle ON.';

            return false;
        }

        if ($integration === null) {
            self::$lastNote = 'No enabled SMTP integration found for a platform admin. Open Super Admin → Integrations → SMTP, fill host/port/credentials, toggle ON, and Save.';
        } else {
            self::$lastNote = 'SMTP integration is ON but incomplete. Add Mailgun domain + API key, or SMTP host/credentials, then Save.';
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $settings
     * @param  array<string, string>  $secrets
     */
    public static function applyFromSettings(array $settings, array $secrets): bool
    {
        $mailgunDomain = trim((string) ($settings['mailgun_domain'] ?? ''));
        $apiKey = trim((string) ($secrets['api_key'] ?? ''));

        if ($mailgunDomain !== '') {
            if ($apiKey === '') {
                self::$lastNote = 'Mailgun domain is set but API key is missing. Paste your Private API key, click Save, then Test. Clear SMTP host/username/password when using API mode.';

                return false;
            }

            return self::applyMailgunApi($settings, $mailgunDomain, $apiKey);
        }

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
     */
    private static function applyMailgunApi(array $settings, string $domain, string $apiKey): bool
    {
        $endpoint = trim((string) ($settings['mailgun_endpoint'] ?? ''));
        if ($endpoint === '') {
            $endpoint = 'api.mailgun.net';
        }
        $endpoint = preg_replace('#^https?://#', '', $endpoint) ?: 'api.mailgun.net';

        config([
            'mail.default' => 'mailgun',
            'services.mailgun.domain' => $domain,
            'services.mailgun.secret' => $apiKey,
            'services.mailgun.endpoint' => $endpoint,
            'services.mailgun.scheme' => 'https',
        ]);

        $fromEmail = trim((string) ($settings['from_email'] ?? ''));
        if ($fromEmail === '') {
            $fromEmail = 'postmaster@'.$domain;
        }

        config([
            'mail.from.address' => $fromEmail,
            'mail.from.name' => (string) (config('mail.from.name') ?: config('app.name', 'Clickronix')),
        ]);

        self::$lastNote = "Using Mailgun HTTP API ({$domain}).";

        return true;
    }

    /**
     * @param  array<string, mixed>  $settings
     * @param  array<string, string>  $secrets
     */
    public static function validateSettings(array $settings, array $secrets): ?string
    {
        $mailgunDomain = trim((string) ($settings['mailgun_domain'] ?? ''));
        $apiKey = trim((string) ($secrets['api_key'] ?? ''));

        if ($mailgunDomain !== '') {
            if ($apiKey === '') {
                return 'Mailgun domain is set but API key is missing. Paste your Private API key from Mailgun → API keys, then Save.';
            }

            $from = trim((string) ($settings['from_email'] ?? ''));
            if ($from === '' || $from === 'hello@example.com') {
                return 'Set From email to postmaster@your-mailgun-domain (sandbox: use the postmaster@…mailgun.org address).';
            }

            return null;
        }

        $host = strtolower(trim((string) ($settings['host'] ?? '')));
        $username = trim((string) ($settings['username'] ?? ''));
        $password = trim((string) ($secrets['password'] ?? ''));
        $port = (int) ($settings['port'] ?? 587);

        if ($host === '') {
            return 'SMTP host is required.';
        }

        if (preg_match('/@(gmail|googlemail)\./i', $username) && ! str_contains($host, 'gmail')) {
            return 'Username looks like Gmail but SMTP host is not smtp.gmail.com. Either use Mailgun/SendGrid credentials with their host, or use host smtp.gmail.com (often blocked on VPS).';
        }

        if (str_contains($host, 'gmail.org')) {
            return 'Invalid host smtp.gmail.org — that domain does not exist. Gmail is smtp.gmail.com (blocked on DigitalOcean). For port 2525 use smtp.mailgun.org or smtp.sendgrid.net with their credentials.';
        }

        if (str_contains($host, 'gmail.com') && $port === 2525) {
            return 'Port 2525 is for Mailgun/SendGrid, not Gmail. Gmail uses 587 or 465 (often blocked on VPS). Use smtp.mailgun.org:2525 with Mailgun SMTP login instead.';
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
        if (config('mail.default') === 'mailgun') {
            $domain = trim((string) config('services.mailgun.domain', ''));
            $secret = trim((string) config('services.mailgun.secret', ''));
            if ($domain === '' || $secret === '') {
                return self::$lastNote ?: 'Mailgun API is not fully configured.';
            }

            $from = trim((string) config('mail.from.address', ''));
            if ($from === '' || $from === 'hello@example.com') {
                return 'Set a valid From email for Mailgun (postmaster@sandbox….mailgun.org for sandbox).';
            }

            return null;
        }

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

    /**
     * @param  array<string, mixed>  $settings
     * @param  array<string, string>  $secrets
     */
    private static function integrationIsSendReady(array $settings, array $secrets): bool
    {
        $mailgunDomain = trim((string) ($settings['mailgun_domain'] ?? ''));

        if ($mailgunDomain !== '') {
            return true;
        }

        $apiKey = trim((string) ($secrets['api_key'] ?? ''));
        if ($apiKey !== '') {
            return true;
        }

        return trim((string) ($settings['host'] ?? '')) !== '';
    }

    /** Prevent stale .env SMTP from sending when Integrations apply failed. */
    private static function neutralizeEnvSmtpFallback(): void
    {
        config([
            'mail.default' => 'log',
            'mail.mailers.smtp.host' => '127.0.0.1',
            'mail.mailers.smtp.port' => 2525,
            'mail.mailers.smtp.username' => null,
            'mail.mailers.smtp.password' => null,
            'mail.mailers.smtp.scheme' => null,
            'services.mailgun.domain' => null,
            'services.mailgun.secret' => null,
        ]);
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
            'mailgun_domain' => config('services.mailgun.domain'),
            'mailgun_api_key_len' => strlen((string) config('services.mailgun.secret', '')),
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
