<?php

namespace App\Services\Mail;

use App\Models\AdminIntegrationSetting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;

class SmtpConfigResolver
{
    /**
     * Apply Super Admin → Integrations → SMTP when .env is not configured for real delivery.
     */
    public static function apply(bool $force = false): bool
    {
        if (! $force && self::envSmtpIsConfigured()) {
            return false;
        }

        $integration = self::resolveEnabledSmtpIntegration();
        if ($integration === null) {
            return false;
        }

        $settings = is_array($integration->settings) ? $integration->settings : [];
        $secrets = self::decryptSecrets($integration);

        $host = trim((string) ($settings['host'] ?? ''));
        if ($host === '') {
            return false;
        }

        $port = (int) ($settings['port'] ?? 587);
        if ($port <= 0) {
            $port = 587;
        }

        $username = trim((string) ($settings['username'] ?? ''));
        $password = (string) ($secrets['password'] ?? '');
        $fromEmail = trim((string) ($settings['from_email'] ?? ''));
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

        return true;
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
        if (! Schema::hasTable('admin_integration_settings')) {
            return null;
        }

        $query = AdminIntegrationSetting::query()
            ->where('name', 'smtp')
            ->where('enabled', true);

        if (Schema::hasColumn('users', 'is_super_admin')) {
            $query->whereHas('user', fn ($q) => $q->where('is_super_admin', true));
        }

        return $query->orderByDesc('updated_at')->first();
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
