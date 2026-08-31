<?php

namespace App\Support;

use App\Models\AdminIntegrationSetting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;

class AdminIntegrationCatalog
{
    /** Ad platforms that can appear on tenant Platform Integrate (not Stripe/SMTP/etc). */
    public const AD_PLATFORM_NAMES = ['meta-ads', 'microsoft-ads'];

    /** Optional tenant-facing integrations toggled from Super Admin → Integrations. */
    public const TENANT_OPTIONAL_NAMES = ['cross-domain', 'guidance-chatbot'];

    public static function ensureForUser(int $userId): void
    {
        foreach ([
            ['name' => 'stripe', 'display_name' => 'Stripe', 'provider' => 'stripe'],
            ['name' => 'google-cloud', 'display_name' => 'Google Cloud', 'provider' => 'google'],
            ['name' => 'smtp', 'display_name' => 'SMTP', 'provider' => 'mail'],
            ['name' => 'oauth', 'display_name' => 'OAuth', 'provider' => 'oauth'],
            ['name' => 'meta-ads', 'display_name' => 'Meta Ads', 'provider' => 'meta'],
            ['name' => 'microsoft-ads', 'display_name' => 'Microsoft Ads', 'provider' => 'microsoft'],
            ['name' => 'cross-domain', 'display_name' => 'Cross-domain intelligence', 'provider' => 'promotix'],
            ['name' => 'guidance-chatbot', 'display_name' => 'Guidance chatbot / KB sync', 'provider' => 'guidance'],
        ] as $row) {
            $defaults = [
                'user_id' => $userId,
                'status' => 'not_configured',
                'enabled' => false,
            ];
            AdminIntegrationSetting::query()->firstOrCreate(
                ['user_id' => $userId, 'name' => $row['name']],
                array_merge($row, $defaults)
            );
        }
    }

    /** Tenant portal: chat active when Super Admin enables Guidance chatbot in Integrations. */
    public static function guidanceChatbotEnabledForUser(?int $userId): bool
    {
        return $userId !== null && self::guidanceChatbotEnabled();
    }

    /** Platform-wide: Guidance chatbot enabled from Super Admin → Integrations toggle. */
    public static function guidanceChatbotEnabled(): bool
    {
        return self::integrationEnabledForTenants('guidance-chatbot');
    }

    /** @return array<int, array<string, mixed>> */
    public static function listForUser(int $userId): array
    {
        self::ensureForUser($userId);

        $order = [
            'stripe' => 0,
            'google-cloud' => 1,
            'smtp' => 2,
            'oauth' => 3,
            'meta-ads' => 4,
            'microsoft-ads' => 5,
            'cross-domain' => 6,
            'guidance-chatbot' => 7,
        ];

        return AdminIntegrationSetting::query()
            ->where('user_id', $userId)
            ->get()
            ->sortBy(fn (AdminIntegrationSetting $integration) => $order[$integration->name] ?? 99)
            ->values()
            ->map(fn (AdminIntegrationSetting $integration) => [
                'id' => $integration->id,
                'name' => $integration->name,
                'display_name' => $integration->display_name,
                'provider' => $integration->provider,
                'enabled' => $integration->enabled,
                'settings' => $integration->settings ?? [],
                'status' => $integration->status,
                'key_version' => $integration->key_version,
                'last_rotated_at' => $integration->last_rotated_at?->diffForHumans(),
                'last_tested_at' => $integration->last_tested_at?->diffForHumans(),
                'secrets_masked' => self::maskedSecrets($integration),
                'fields' => self::fieldsFor($integration->name),
            ])
            ->values()
            ->all();
    }

    /**
     * Platform-wide: Meta / Microsoft enabled from Super Admin → Integrations.
     * Only these ad platforms are surfaced on tenant Platform Integrate.
     *
     * @return array{meta: bool, microsoft: bool}
     */
    public static function enabledAdPlatforms(): array
    {
        if (! Schema::hasTable('admin_integration_settings')) {
            return ['meta' => false, 'microsoft' => false];
        }

        $enabled = self::platformOperatorIntegrationQuery()
            ->whereIn('name', self::AD_PLATFORM_NAMES)
            ->where('enabled', true)
            ->pluck('name')
            ->unique()
            ->all();

        return [
            'meta' => in_array('meta-ads', $enabled, true),
            'microsoft' => in_array('microsoft-ads', $enabled, true),
        ];
    }

    /**
     * Optional integrations surfaced on tenant Platform Integrate
     * when enabled in Super Admin → Integrations.
     *
     * @return array{cross_domain: bool, chatbot: bool}
     */
    public static function enabledTenantIntegrations(): array
    {
        return [
            'cross_domain' => self::integrationEnabledForTenants('cross-domain'),
            'chatbot' => self::integrationEnabledForTenants('guidance-chatbot'),
        ];
    }

    public static function integrationEnabledForTenants(string $name): bool
    {
        if (! Schema::hasTable('admin_integration_settings')) {
            return false;
        }

        return self::platformOperatorIntegrationQuery($name)
            ->where('enabled', true)
            ->exists();
    }

    public static function platformIntegrationSetting(string $name, bool $enabledOnly = true): ?AdminIntegrationSetting
    {
        if (! Schema::hasTable('admin_integration_settings')) {
            return null;
        }

        $query = self::platformOperatorIntegrationQuery($name);
        if ($enabledOnly) {
            $query->where('enabled', true);
        }

        return $query->orderByDesc('updated_at')->first();
    }

    /** @return \Illuminate\Database\Eloquent\Builder<AdminIntegrationSetting> */
    private static function platformOperatorIntegrationQuery(?string $name = null)
    {
        $query = AdminIntegrationSetting::query();
        if ($name !== null) {
            $query->where('name', $name);
        }

        if (! Schema::hasTable('users')) {
            return $query;
        }

        return $query->whereHas('user', function ($userQuery): void {
            if (Schema::hasColumn('users', 'is_super_admin') && Schema::hasColumn('users', 'is_admin')) {
                $userQuery->where(function ($inner): void {
                    $inner->where('is_super_admin', true)->orWhere('is_admin', true);
                });

                return;
            }

            if (Schema::hasColumn('users', 'is_super_admin')) {
                $userQuery->where('is_super_admin', true);

                return;
            }

            if (Schema::hasColumn('users', 'is_admin')) {
                $userQuery->where('is_admin', true);
            }
        });
    }

    public static function cardMeta(string $name): array
    {
        return match ($name) {
            'stripe' => [
                'icon' => 'S',
                'subtitle' => 'Payment processing & billing',
                'connected_label' => 'Connected',
            ],
            'google-cloud' => [
                'icon' => 'G',
                'subtitle' => 'Maps, analytics & service accounts',
                'connected_label' => 'Integrated',
            ],
            'smtp' => [
                'icon' => '@',
                'subtitle' => 'Mailgun/SendGrid on port 2525 for VPS (Gmail SMTP blocked)',
                'connected_label' => 'Configured',
            ],
            'oauth' => [
                'icon' => 'O',
                'subtitle' => 'OAuth providers for sign-in',
                'connected_label' => 'Authenticated',
            ],
            'meta-ads' => [
                'icon' => 'M',
                'subtitle' => 'Meta (Facebook / Instagram) Ads',
                'connected_label' => 'Enabled for tenants',
            ],
            'microsoft-ads' => [
                'icon' => 'B',
                'subtitle' => 'Microsoft Advertising (Bing)',
                'connected_label' => 'Enabled for tenants',
            ],
            'cross-domain' => [
                'icon' => 'X',
                'subtitle' => 'Link visitor sessions across domains in your workspace for journey intelligence.',
                'connected_label' => 'Enabled for tenants',
            ],
            'guidance-chatbot' => [
                'icon' => 'C',
                'subtitle' => 'Dashboard + website chat both read published Guidance articles from Super Admin.',
                'connected_label' => 'Synced',
            ],
            default => [
                'icon' => '•',
                'subtitle' => 'Platform integration',
                'connected_label' => 'Connected',
            ],
        };
    }

    /** @return array<string, string> */
    private static function maskedSecrets(AdminIntegrationSetting $integration): array
    {
        if (! $integration->secret_payload) {
            return [];
        }

        try {
            $payload = json_decode(Crypt::decryptString($integration->secret_payload), true) ?: [];
        } catch (\Throwable) {
            return ['payload' => '********'];
        }

        return collect($payload)->map(fn ($value) => self::mask((string) $value))->all();
    }

    private static function mask(string $value): string
    {
        return strlen($value) <= 8
            ? str_repeat('*', max(4, strlen($value)))
            : substr($value, 0, 4).str_repeat('*', max(4, strlen($value) - 8)).substr($value, -4);
    }

    /** @return array<int, array<string, mixed>> */
    private static function fieldsFor(string $integration): array
    {
        return match ($integration) {
            'stripe' => [
                ['name' => 'publishable_key', 'label' => 'Publishable key', 'type' => 'text', 'secret' => false],
                ['name' => 'secret_key', 'label' => 'Secret key', 'type' => 'password', 'secret' => true],
                ['name' => 'webhook_secret', 'label' => 'Webhook signing secret', 'type' => 'password', 'secret' => true],
            ],
            'google-cloud' => [
                ['name' => 'project_id', 'label' => 'Project ID', 'type' => 'text', 'secret' => false],
                ['name' => 'service_account_email', 'label' => 'Service account email', 'type' => 'text', 'secret' => false],
                ['name' => 'service_account_key', 'label' => 'Service account JSON key', 'type' => 'textarea', 'secret' => true],
            ],
            'smtp' => [
                ['name' => 'host', 'label' => 'SMTP host', 'type' => 'text', 'secret' => false],
                ['name' => 'port', 'label' => 'SMTP port', 'type' => 'text', 'secret' => false],
                ['name' => 'encryption', 'label' => 'Encryption (tls or ssl)', 'type' => 'text', 'secret' => false],
                ['name' => 'username', 'label' => 'SMTP username', 'type' => 'text', 'secret' => false],
                ['name' => 'password', 'label' => 'SMTP password', 'type' => 'password', 'secret' => true],
                ['name' => 'from_email', 'label' => 'From email', 'type' => 'text', 'secret' => false],
            ],
            'oauth' => [
                ['name' => 'allowed_providers', 'label' => 'Allowed providers', 'type' => 'text', 'secret' => false],
                ['name' => 'client_id', 'label' => 'Client ID', 'type' => 'text', 'secret' => false],
                ['name' => 'client_secret', 'label' => 'Client secret', 'type' => 'password', 'secret' => true],
            ],
            'meta-ads' => [
                ['name' => 'app_id', 'label' => 'Meta App ID', 'type' => 'text', 'secret' => false],
                ['name' => 'app_secret', 'label' => 'Meta App Secret', 'type' => 'password', 'secret' => true],
            ],
            'microsoft-ads' => [
                ['name' => 'client_id', 'label' => 'Microsoft Client ID', 'type' => 'text', 'secret' => false],
                ['name' => 'client_secret', 'label' => 'Microsoft Client Secret', 'type' => 'password', 'secret' => true],
                ['name' => 'developer_token', 'label' => 'Developer token', 'type' => 'password', 'secret' => true],
            ],
            'cross-domain' => [
                ['name' => 'linked_domains', 'label' => 'Linked domains', 'type' => 'text', 'secret' => false, 'readonly' => true],
                ['name' => 'cross_sessions_30d', 'label' => 'Cross-domain hits (30d)', 'type' => 'text', 'secret' => false, 'readonly' => true],
            ],
            'guidance-chatbot' => [
                ['name' => 'published_articles', 'label' => 'Published articles', 'type' => 'text', 'secret' => false, 'readonly' => true],
                ['name' => 'open_chat_sessions', 'label' => 'Open chat sessions', 'type' => 'text', 'secret' => false, 'readonly' => true],
                ['name' => 'dashboard_endpoint', 'label' => 'Ask endpoint', 'type' => 'text', 'secret' => false, 'readonly' => true],
            ],
            default => [],
        };
    }
}
