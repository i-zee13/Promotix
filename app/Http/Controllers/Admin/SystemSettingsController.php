<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminWebhook;
use App\Support\AdminIntegrationCatalog;
use App\Models\GoogleConnection;
use Illuminate\Http\Request;

class SystemSettingsController extends Controller
{
    public function index(Request $request)
    {
        $userId = $request->user()->id;
        $integrations = AdminIntegrationCatalog::listForUser($userId);

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

    private function mask(string $value): string
    {
        return strlen($value) <= 8
            ? str_repeat('*', max(4, strlen($value)))
            : substr($value, 0, 4) . str_repeat('*', max(4, strlen($value) - 8)) . substr($value, -4);
    }
}
