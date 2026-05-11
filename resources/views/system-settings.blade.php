@extends('layouts.admin')

@section('title', 'System Settings')

@section('content')
<div class="space-y-6"
    x-data="systemSettings({
        integrations: @js($integrations),
        webhooks: @js($webhooks),
        eventOptions: @js($eventOptions),
        urls: {
            integrations: '{{ url('api/admin/integrations') }}',
            webhooks: '{{ url('api/admin/webhooks') }}',
        },
        csrf: '{{ csrf_token() }}',
    })">
    <x-ui.page-header
        title="System settings"
        subtitle="Manage integration credentials, webhooks, and OAuth providers used across your tenant.">
    </x-ui.page-header>

    @if (session('status'))
        <div class="rounded-xl2 border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200">
            {{ session('status') }}
        </div>
    @endif

    <div class="brand-tab-bar inline-flex gap-2 rounded-full bg-night-900/60 p-1 text-xs uppercase tracking-wide">
        @foreach (['integrations' => 'Integrations', 'webhooks' => 'Webhooks', 'oauth' => 'OAuth Providers'] as $key => $label)
            <button type="button"
                @click="tab = '{{ $key }}'"
                :class="tab === '{{ $key }}' ? 'bg-brand-500 text-white shadow-card' : 'text-slate-400 hover:text-white'"
                class="rounded-full px-4 py-2 font-semibold transition">
                {{ $label }}
            </button>
        @endforeach
    </div>

    {{-- Toast --}}
    <template x-if="toast.message">
        <div class="rounded-xl2 border px-4 py-3 text-sm"
            :class="toast.type === 'error' ? 'border-rose-500/40 bg-rose-500/10 text-rose-200' : 'border-emerald-500/30 bg-emerald-500/10 text-emerald-200'">
            <span x-text="toast.message"></span>
        </div>
    </template>

    {{-- INTEGRATIONS TAB --}}
    <div x-show="tab === 'integrations'" class="space-y-4">
        <template x-for="integration in integrations" :key="integration.id">
            <x-ui.card class="p-6">
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <div class="flex items-center gap-3">
                            <h3 class="text-lg font-semibold text-white" x-text="integration.display_name"></h3>
                            <span class="brand-pill"
                                :class="integration.enabled ? 'brand-pill-success' : 'brand-pill-neutral'"
                                x-text="integration.enabled ? 'Enabled' : 'Disabled'"></span>
                            <span class="text-xs text-slate-400">v<span x-text="integration.key_version"></span></span>
                        </div>
                        <p class="mt-1 text-sm text-slate-400">
                            <template x-if="integration.last_rotated_at">
                                <span>Rotated <span x-text="integration.last_rotated_at"></span> · </span>
                            </template>
                            <template x-if="integration.last_tested_at">
                                <span>Tested <span x-text="integration.last_tested_at"></span></span>
                            </template>
                            <template x-if="!integration.last_rotated_at && !integration.last_tested_at">
                                <span>Never rotated · Never tested</span>
                            </template>
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <label class="inline-flex items-center gap-2 text-xs text-slate-300">
                            <input type="checkbox" class="brand-checkbox" x-model="integration.enabled">
                            Enabled
                        </label>
                        <button type="button" class="brand-btn-secondary" @click="testIntegration(integration)">Test</button>
                        <button type="button" class="brand-btn-secondary" @click="rotateIntegration(integration)">Rotate key</button>
                        <button type="button" class="brand-btn-primary" @click="saveIntegration(integration)">Save</button>
                    </div>
                </div>

                <div class="mt-5 grid gap-4 md:grid-cols-2">
                    <template x-for="field in integration.fields" :key="field.name">
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wide text-slate-400" x-text="field.label"></label>
                            <template x-if="field.secret">
                                <div class="mt-1 space-y-1">
                                    <p class="text-xs text-slate-500">Stored encrypted · current: <span class="font-mono" x-text="integration.secrets_masked[field.name] || '— not set —'"></span></p>
                                    <template x-if="field.type === 'textarea'">
                                        <textarea rows="3" class="brand-input mt-1 w-full font-mono text-xs"
                                            :placeholder="'Paste new ' + field.label.toLowerCase() + ' (leaves blank to keep existing)'"
                                            x-model="integration._secrets[field.name]"></textarea>
                                    </template>
                                    <template x-if="field.type !== 'textarea'">
                                        <input type="password" autocomplete="new-password" class="brand-input mt-1 w-full"
                                            :placeholder="'Leave blank to keep existing'"
                                            x-model="integration._secrets[field.name]">
                                    </template>
                                </div>
                            </template>
                            <template x-if="!field.secret">
                                <input type="text" class="brand-input mt-1 w-full"
                                    :placeholder="field.label"
                                    x-model="integration.settings[field.name]">
                            </template>
                        </div>
                    </template>
                </div>
            </x-ui.card>
        </template>
    </div>

    {{-- WEBHOOKS TAB --}}
    <div x-show="tab === 'webhooks'" class="space-y-4" style="display: none;">
        <x-ui.card class="p-6">
            <h3 class="text-lg font-semibold text-white">Create webhook</h3>
            <p class="mt-1 text-sm text-slate-400">We'll generate a signing secret you can use to verify deliveries.</p>
            <form class="mt-4 grid gap-3 md:grid-cols-2" @submit.prevent="createWebhook()">
                <div class="md:col-span-1">
                    <label class="block text-xs font-semibold uppercase tracking-wide text-slate-400">Name</label>
                    <input type="text" class="brand-input mt-1 w-full" x-model="newWebhook.name" placeholder="Production CRM hook" required>
                </div>
                <div class="md:col-span-1">
                    <label class="block text-xs font-semibold uppercase tracking-wide text-slate-400">URL</label>
                    <input type="url" class="brand-input mt-1 w-full" x-model="newWebhook.url" placeholder="https://example.com/webhooks/promotix" required>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-semibold uppercase tracking-wide text-slate-400">Events</label>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <template x-for="event in eventOptions" :key="event">
                            <label class="inline-flex items-center gap-2 rounded-full border border-night-700 bg-night-900/50 px-3 py-1 text-xs text-slate-200">
                                <input type="checkbox" class="brand-checkbox" :value="event" x-model="newWebhook.events">
                                <span x-text="event"></span>
                            </label>
                        </template>
                    </div>
                </div>
                <div class="md:col-span-2 flex items-center justify-between">
                    <label class="inline-flex items-center gap-2 text-xs text-slate-300">
                        <input type="checkbox" class="brand-checkbox" x-model="newWebhook.is_active">
                        Active
                    </label>
                    <button type="submit" class="brand-btn-primary">Create webhook</button>
                </div>
            </form>
            <template x-if="lastCreatedSecret">
                <div class="mt-4 rounded-xl2 border border-amber-500/40 bg-amber-500/10 p-4 text-sm text-amber-200">
                    <p class="font-semibold">Copy this signing secret now — it will not be shown again:</p>
                    <code class="mt-2 block break-all font-mono" x-text="lastCreatedSecret"></code>
                </div>
            </template>
        </x-ui.card>

        <x-ui.card class="p-0">
            <div class="border-b border-night-800 px-6 py-4">
                <h3 class="text-lg font-semibold text-white">Configured webhooks</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="brand-table w-full text-sm">
                    <thead>
                        <tr>
                            <th class="px-6 py-3 text-left">Name</th>
                            <th class="px-6 py-3 text-left">URL</th>
                            <th class="px-6 py-3 text-left">Events</th>
                            <th class="px-6 py-3 text-left">Last delivery</th>
                            <th class="px-6 py-3 text-left">Secret</th>
                            <th class="px-6 py-3 text-left">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="webhook in webhooks" :key="webhook.id">
                            <tr>
                                <td class="px-6 py-3 font-medium text-white" x-text="webhook.name"></td>
                                <td class="px-6 py-3 font-mono text-xs text-slate-300" x-text="webhook.url"></td>
                                <td class="px-6 py-3">
                                    <div class="flex flex-wrap gap-1">
                                        <template x-for="ev in webhook.events" :key="ev">
                                            <span class="inline-flex items-center rounded-full bg-night-800 px-2 py-0.5 text-xs text-slate-200" x-text="ev"></span>
                                        </template>
                                    </div>
                                </td>
                                <td class="px-6 py-3 text-slate-300">
                                    <span x-text="webhook.last_delivery_status || '—'"></span>
                                    <span class="block text-xs text-slate-500" x-text="webhook.last_delivered_at"></span>
                                </td>
                                <td class="px-6 py-3 font-mono text-xs text-slate-400" x-text="webhook.secret_masked"></td>
                                <td class="px-6 py-3">
                                    <span class="brand-pill"
                                        :class="webhook.is_active ? 'brand-pill-success' : 'brand-pill-neutral'"
                                        x-text="webhook.is_active ? 'Active' : 'Disabled'"></span>
                                </td>
                            </tr>
                        </template>
                        <tr x-show="webhooks.length === 0">
                            <td colspan="6" class="px-6 py-6 text-center text-sm text-slate-400">No webhooks configured yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </x-ui.card>
    </div>

    {{-- OAUTH TAB --}}
    <div x-show="tab === 'oauth'" class="space-y-4" style="display: none;">
        @foreach ($oauthProviders as $provider)
            <x-ui.card class="p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-white">{{ $provider['display_name'] }}</h3>
                        <p class="mt-1 text-sm text-slate-400">
                            @if ($provider['configured'])
                                Provider credentials detected in environment configuration.
                            @else
                                Provider not configured — set <code class="font-mono">GOOGLE_ADS_CLIENT_ID</code> and friends in your environment.
                            @endif
                        </p>
                    </div>
                    <x-ui.pill :tone="$provider['configured'] ? 'success' : 'warning'">
                        {{ $provider['configured'] ? 'Configured' : 'Not configured' }}
                    </x-ui.pill>
                </div>

                <div class="mt-5">
                    <h4 class="text-sm font-semibold uppercase tracking-wide text-slate-300">User connections</h4>
                    @if ($provider['connections']->isEmpty())
                        <p class="mt-2 text-sm text-slate-500">You haven't connected any Google account yet. Use the Integrations page to start the OAuth flow.</p>
                    @else
                        <ul class="mt-2 divide-y divide-night-800 rounded-xl2 border border-night-800">
                            @foreach ($provider['connections'] as $conn)
                                <li class="flex items-center justify-between px-4 py-3 text-sm">
                                    <div class="text-white">{{ $conn->google_email ?? 'unknown@google.com' }}</div>
                                    <div class="text-xs text-slate-400">
                                        Connected {{ optional($conn->connected_at)->diffForHumans() ?? 'recently' }}
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </x-ui.card>
        @endforeach
    </div>
</div>

<script>
function systemSettings(initial) {
    return {
        tab: 'integrations',
        integrations: initial.integrations.map((row) => ({ ...row, _secrets: {} })),
        webhooks: initial.webhooks,
        eventOptions: initial.eventOptions,
        urls: initial.urls,
        csrf: initial.csrf,
        toast: { message: '', type: 'success' },
        lastCreatedSecret: null,
        newWebhook: {
            name: '',
            url: '',
            events: ['ticket.created', 'traffic.blocked'],
            is_active: true,
        },
        notify(message, type = 'success') {
            this.toast = { message, type };
            setTimeout(() => (this.toast.message = ''), 4000);
        },
        async request(url, method, body) {
            const res = await fetch(url, {
                method,
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': this.csrf,
                },
                body: body ? JSON.stringify(body) : undefined,
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) {
                throw new Error(data.message || 'Request failed');
            }
            return data;
        },
        async saveIntegration(integration) {
            try {
                const secrets = Object.fromEntries(
                    Object.entries(integration._secrets || {}).filter(([, v]) => v && String(v).trim().length > 0)
                );
                const payload = {
                    enabled: integration.enabled,
                    settings: integration.settings || {},
                };
                if (Object.keys(secrets).length > 0) payload.secrets = secrets;
                const data = await this.request(`${this.urls.integrations}/${integration.name}`, 'PUT', payload);
                Object.assign(integration, data.integration, { _secrets: {}, fields: integration.fields });
                this.notify('Integration saved.');
            } catch (e) {
                this.notify(e.message, 'error');
            }
        },
        async testIntegration(integration) {
            try {
                const data = await this.request(`${this.urls.integrations}/${integration.name}/test`, 'POST');
                Object.assign(integration, data.integration, { _secrets: {}, fields: integration.fields });
                this.notify(data.message || 'Connection test ran.');
            } catch (e) {
                this.notify(e.message, 'error');
            }
        },
        async rotateIntegration(integration) {
            if (!confirm('Rotate the API key for this integration? Existing keys stop working immediately.')) return;
            try {
                const data = await this.request(`${this.urls.integrations}/${integration.name}/rotate`, 'POST');
                Object.assign(integration, data.integration, { _secrets: {}, fields: integration.fields });
                this.notify(data.message || 'Key rotated.');
            } catch (e) {
                this.notify(e.message, 'error');
            }
        },
        async createWebhook() {
            try {
                const data = await this.request(this.urls.webhooks, 'POST', this.newWebhook);
                this.webhooks.unshift({
                    id: data.webhook.id,
                    name: data.webhook.name,
                    url: data.webhook.url,
                    events: data.webhook.events || [],
                    is_active: data.webhook.is_active,
                    secret_masked: data.webhook.secret ? data.webhook.secret.slice(0, 4) + '...' + data.webhook.secret.slice(-4) : '****',
                    last_delivery_status: null,
                    last_delivered_at: null,
                });
                this.lastCreatedSecret = data.webhook.secret;
                this.newWebhook = { name: '', url: '', events: ['ticket.created', 'traffic.blocked'], is_active: true };
                this.notify('Webhook created — copy the signing secret below.');
            } catch (e) {
                this.notify(e.message, 'error');
            }
        },
    };
}
</script>
@endsection
