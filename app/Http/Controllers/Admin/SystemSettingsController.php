<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminIntegrationSetting;
use App\Models\AdminWebhook;
use App\Models\GoogleConnection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class SystemSettingsController extends Controller
{
    public function index(Request $request)
    {
        $userId = $request->user()->id;
        $this->ensureDefaultIntegrations($userId);

        $integrations = AdminIntegrationSetting::query()
            ->where('user_id', $userId)
            ->orderBy('display_name')
            ->get()
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
                'secrets_masked' => $this->maskedSecrets($integration),
                'fields' => $this->fieldsFor($integration->name),
            ])
            ->all();

        $webhooks = AdminWebhook::query()
            ->where('user_id', $userId)
            ->latest('id')
            ->get()
            ->map(fn (AdminWebhook $webhook) => [
                'id' => $webhook->id,
                'name' => $webhook->name,
                'url' => $webhook->url,
                'events' => $webhook->events ?? [],
                'is_active' => $webhook->is_active,
                'secret_masked' => $this->mask($webhook->secret),
                'last_delivery_status' => $webhook->last_delivery_status,
                'last_delivered_at' => $webhook->last_delivered_at?->diffForHumans(),
            ])
            ->all();

        $oauthProviders = [
            [
                'name' => 'google',
                'display_name' => 'Google OAuth',
                'configured' => (string) config('services.google_ads.client_id') !== '',
                'connections' => GoogleConnection::query()
                    ->where('user_id', $userId)
                    ->orderByDesc('id')
                    ->get(['id', 'google_email', 'connected_at']),
            ],
        ];

        $eventOptions = [
            'ticket.created',
            'ticket.replied',
            'ticket.escalated',
            'traffic.blocked',
            'traffic.threat_detected',
            'job.run.failed',
            'job.run.success',
        ];

        return view('system-settings', [
            'integrations' => $integrations,
            'webhooks' => $webhooks,
            'oauthProviders' => $oauthProviders,
            'eventOptions' => $eventOptions,
        ]);
    }

    private function ensureDefaultIntegrations(int $userId): void
    {
        foreach ([
            ['name' => 'stripe', 'display_name' => 'Stripe Settings', 'provider' => 'stripe'],
            ['name' => 'google-cloud', 'display_name' => 'Google Cloud Settings', 'provider' => 'google'],
            ['name' => 'smtp', 'display_name' => 'SMTP Settings', 'provider' => 'mail'],
            ['name' => 'oauth', 'display_name' => 'OAuth Providers', 'provider' => 'oauth'],
        ] as $row) {
            AdminIntegrationSetting::query()->firstOrCreate(
                ['user_id' => $userId, 'name' => $row['name']],
                array_merge($row, ['user_id' => $userId, 'status' => 'not_configured'])
            );
        }
    }

    private function maskedSecrets(AdminIntegrationSetting $integration): array
    {
        if (! $integration->secret_payload) {
            return [];
        }

        try {
            $payload = json_decode(Crypt::decryptString($integration->secret_payload), true) ?: [];
        } catch (\Throwable) {
            return ['payload' => '********'];
        }

        return collect($payload)->map(fn ($value) => $this->mask((string) $value))->all();
    }

    private function mask(string $value): string
    {
        return strlen($value) <= 8
            ? str_repeat('*', max(4, strlen($value)))
            : substr($value, 0, 4) . str_repeat('*', max(4, strlen($value) - 8)) . substr($value, -4);
    }

    private function fieldsFor(string $integration): array
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
                ['name' => 'username', 'label' => 'SMTP username', 'type' => 'text', 'secret' => false],
                ['name' => 'password', 'label' => 'SMTP password', 'type' => 'password', 'secret' => true],
                ['name' => 'from_email', 'label' => 'From email', 'type' => 'text', 'secret' => false],
            ],
            'oauth' => [
                ['name' => 'allowed_providers', 'label' => 'Allowed providers (comma separated)', 'type' => 'text', 'secret' => false],
                ['name' => 'client_id', 'label' => 'Client ID', 'type' => 'text', 'secret' => false],
                ['name' => 'client_secret', 'label' => 'Client secret', 'type' => 'password', 'secret' => true],
            ],
            default => [],
        };
    }
}
