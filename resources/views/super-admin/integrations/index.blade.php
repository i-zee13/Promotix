@extends('layouts.super-admin')

@section('title', 'Integrations')

@section('content')
<x-super-admin.page title="Integrations" subtitle="Stripe, Google Cloud, SMTP, and OAuth credentials">
    <div class="space-y-[16px]"
        x-data="superAdminIntegrations({
            integrations: @js($integrations),
            urls: { integrations: '{{ url('api/admin/integrations') }}' },
            csrf: '{{ csrf_token() }}',
        })">

        <template x-if="toast.message">
            <div class="figma-sa-msg"
                :class="toast.type === 'error' ? 'border-rose-500/40 bg-rose-500/10 text-rose-200' : 'border-emerald-500/30 bg-emerald-500/10 text-emerald-200'">
                <span x-text="toast.message"></span>
            </div>
        </template>

        <div class="grid grid-cols-1 gap-[14px] xl:grid-cols-2">
            <template x-for="integration in integrations" :key="integration.id">
                <article class="figma-sa-integration-card">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex min-w-0 items-center gap-3">
                            <span class="figma-sa-integration-icon" x-text="integration.icon"></span>
                            <div class="min-w-0">
                                <h3 class="text-[18px] font-medium text-white" x-text="integration.display_name"></h3>
                                <p class="text-[13px] text-white/80" x-text="integration.subtitle"></p>
                            </div>
                        </div>
                        <label class="inline-flex shrink-0 items-center gap-2 text-xs text-white/90">
                            <input type="checkbox" class="sr-only" :checked="integration.enabled" @change="integration.enabled = $event.target.checked">
                            <span class="relative inline-flex h-6 w-11 rounded-full transition"
                                :class="integration.enabled ? 'bg-white/30' : 'bg-black/30'">
                                <span class="inline-block h-5 w-5 translate-y-0.5 rounded-full bg-white shadow transition"
                                    :class="integration.enabled ? 'translate-x-6' : 'translate-x-1'"></span>
                            </span>
                        </label>
                    </div>

                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <span class="figma-sa-integration-pill"
                            :class="integration.enabled ? 'is-connected' : 'is-muted'"
                            x-text="integration.enabled ? integration.connected_label : 'Disabled'"></span>
                        <span class="text-[11px] text-white/70">v<span x-text="integration.key_version"></span></span>
                    </div>

                    <p class="mt-2 text-[12px] text-white/70">
                        <template x-if="integration.last_rotated_at">
                            <span>Rotated <span x-text="integration.last_rotated_at"></span></span>
                        </template>
                        <template x-if="!integration.last_rotated_at">
                            <span>Never rotated</span>
                        </template>
                        <template x-if="integration.last_tested_at">
                            <span> · Tested <span x-text="integration.last_tested_at"></span></span>
                        </template>
                    </p>

                    <div class="mt-4 space-y-2">
                        <template x-for="field in integration.fields" :key="field.name">
                            <div>
                                <label class="text-[11px] font-medium uppercase tracking-wide text-white/70" x-text="field.label"></label>
                                <template x-if="field.secret">
                                    <input type="password" autocomplete="new-password"
                                        class="figma-sa-integration-field mt-1 w-full"
                                        :placeholder="integration.secrets_masked[field.name] ? ('Current: ' + integration.secrets_masked[field.name]) : 'Not set'"
                                        x-model="integration._secrets[field.name]">
                                </template>
                                <template x-if="!field.secret">
                                    <input :type="field.type === 'textarea' ? 'text' : (field.type || 'text')"
                                        class="figma-sa-integration-field mt-1 w-full"
                                        x-model="integration.settings[field.name]">
                                </template>
                            </div>
                        </template>
                    </div>

                    <div class="mt-4 flex flex-wrap gap-2">
                        <button type="button" class="figma-sa-integration-btn" @click="testIntegration(integration)">Test</button>
                        <button type="button" class="figma-sa-integration-btn" @click="rotateIntegration(integration)">Rotate Keys</button>
                        <button type="button" class="figma-sa-integration-btn figma-sa-integration-btn--solid" @click="saveIntegration(integration)">Save</button>
                    </div>
                </article>
            </template>
        </div>
    </div>
</x-super-admin.page>

<script>
function superAdminIntegrations(initial) {
    return {
        integrations: initial.integrations.map((row) => ({
            ...row,
            settings: { ...(row.settings || {}) },
            _secrets: {},
        })),
        urls: initial.urls,
        csrf: initial.csrf,
        toast: { message: '', type: 'success' },
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
            if (!res.ok) throw new Error(data.message || 'Request failed');
            return data;
        },
        async saveIntegration(integration) {
            try {
                const secrets = Object.fromEntries(
                    Object.entries(integration._secrets || {}).filter(([, v]) => v && String(v).trim().length > 0)
                );
                const payload = { enabled: integration.enabled, settings: integration.settings || {} };
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
            if (!confirm('Rotate API keys for this integration?')) return;
            try {
                const data = await this.request(`${this.urls.integrations}/${integration.name}/rotate`, 'POST');
                Object.assign(integration, data.integration, { _secrets: {}, fields: integration.fields });
                this.notify(data.message || 'Keys rotated.');
            } catch (e) {
                this.notify(e.message, 'error');
            }
        },
    };
}
</script>
@endsection
