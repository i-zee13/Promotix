@extends('layouts.super-admin')

@section('title', 'Integrations')

@section('content')
<style>
    .figma-sa-integrations-page .figma-sa-integration-field,
    .figma-sa-integrations-page .figma-sa-integration-field:-webkit-autofill {
        color: #ffffff !important;
        -webkit-text-fill-color: #ffffff !important;
    }
    html.light-mode .figma-main .figma-sa-integrations-page .figma-sa-integration-field {
        color: #ffffff !important;
        -webkit-text-fill-color: #ffffff !important;
    }
</style>
<x-super-admin.page title="Integrations">
    <div class="figma-sa-integrations-page space-y-[16px]"
        x-data="superAdminIntegrations({
            integrations: @js($integrations),
            urls: { integrations: '{{ url('api/admin/integrations') }}' },
            csrf: '{{ csrf_token() }}',
            smtpTestEmail: @js(auth()->user()?->email ?? ''),
        })">

        <template x-if="toast.message">
            <div class="figma-sa-msg"
                :class="toast.type === 'error' ? 'border-rose-500/40 bg-rose-500/10 text-rose-200' : 'border-emerald-500/30 bg-emerald-500/10 text-emerald-200'">
                <span x-text="toast.message"></span>
            </div>
        </template>

        <div class="flex flex-wrap items-center gap-[10px]">
            <label class="figma-sa-dash-search !min-w-[220px]">
                <svg class="h-[18px] w-[18px] shrink-0 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-5-5m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="search" placeholder="Search integrations" class="figma-sa-dash-search-input" x-model="query">
            </label>
            <x-super-admin.dashboard-dropdown align="left">
                <x-slot:trigger>
                    <button type="button" @click="open = !open" class="figma-sa-users-filter-btn">
                        <span x-text="statusLabel"></span>
                        <svg class="h-4 w-4 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                </x-slot:trigger>
                <button type="button" class="figma-sa-dash-dropdown-item" @click="statusFilter = ''">All Statuses</button>
                <button type="button" class="figma-sa-dash-dropdown-item" @click="statusFilter = 'connected'">Connected</button>
                <button type="button" class="figma-sa-dash-dropdown-item" @click="statusFilter = 'disabled'">Disabled</button>
            </x-super-admin.dashboard-dropdown>
        </div>

        <div class="grid grid-cols-1 gap-[14px] md:grid-cols-2 xl:grid-cols-3">
            <template x-for="integration in filteredIntegrations" :key="integration.id">
                <article class="figma-sa-integration-card" :class="['guidance-chatbot','cross-domain','smtp'].includes(integration.name) ? 'ring-1 ring-[color-mix(in_srgb,var(--brand-primary)_50%,transparent)]' : ''">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex min-w-0 items-center gap-3">
                            <span class="figma-sa-integration-icon" :class="'is-' + integration.name" aria-hidden="true">
                                <svg x-show="integration.name === 'stripe'" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9.5c0-1.5-1.5-2.5-3.5-2.5S10 8 10 9.5c0 3 7 1.5 7 4.5 0 1.5-1.5 2.5-3.5 2.5S9.5 15.5 9.5 14"/></svg>
                                <svg x-show="integration.name === 'google-cloud'" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 17a4 4 0 01-1-7.87A5.5 5.5 0 0115.5 6a4.5 4.5 0 011 8.88M8 17h9"/></svg>
                                <svg x-show="integration.name === 'smtp'" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                <svg x-show="integration.name === 'oauth'" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 2l7 3v6c0 4.5-3 8.5-7 10-4-1.5-7-5.5-7-10V5l7-3z"/></svg>
                                <svg x-show="integration.name === 'meta-ads'" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.04c-5.5 0-10 4.49-10 10.02 0 5 3.66 9.15 8.44 9.9v-7H7.9v-2.9h2.54V9.85c0-2.52 1.49-3.91 3.78-3.91 1.09 0 2.24.2 2.24.2v2.47h-1.26c-1.24 0-1.63.78-1.63 1.57v1.88h2.78l-.45 2.9h-2.33v7c4.78-.75 8.44-4.9 8.44-9.9 0-5.53-4.5-10.02-10-10.02z"/></svg>
                                <svg x-show="integration.name === 'microsoft-ads'" viewBox="0 0 24 24" fill="currentColor"><path d="M3 3h8.5v8.5H3V3zm9.5 0H21v8.5h-8.5V3zM3 12.5H11.5V21H3v-8.5zm9.5 0H21V21h-8.5v-8.5z"/></svg>
                                <svg x-show="integration.name === 'cross-domain'" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-width="1.8" d="M8 12h8M12 8v8"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="12" r="3"/></svg>
                                <svg x-show="integration.name === 'guidance-chatbot'" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 10h.01M12 10h.01M16 10h.01M21 12c0 4.418-4.03 8-9 8a9.86 9.86 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                                <template x-if="!['stripe','google-cloud','smtp','oauth','meta-ads','microsoft-ads','guidance-chatbot','cross-domain'].includes(integration.name)"><span x-text="integration.icon"></span></template>
                            </span>
                            <div class="min-w-0">
                                <h3 class="text-[20px] font-medium text-white" x-text="integration.display_name"></h3>
                                <p class="mt-1 text-[12px] text-white/55" x-show="integration.subtitle" x-text="integration.subtitle"></p>
                            </div>
                        </div>
                        <label class="inline-flex shrink-0 items-center gap-2 text-xs text-white/90" title="Enable / disable">
                            <input
                                type="checkbox"
                                class="sr-only"
                                :checked="integration.enabled"
                                @change="integration.enabled = $event.target.checked; saveIntegration(integration, true)"
                            >
                            <span class="relative inline-flex h-6 w-11 rounded-full transition"
                                :class="integration.enabled ? 'bg-[color-mix(in_srgb,var(--brand-primary)_55%,#ffffff)]' : 'bg-black/30'">
                                <span class="inline-block h-5 w-5 translate-y-0.5 rounded-full bg-white shadow transition"
                                    :class="integration.enabled ? 'translate-x-6' : 'translate-x-1'"></span>
                            </span>
                        </label>
                    </div>

                    <div class="mt-3">
                        <span class="figma-sa-integration-pill"
                            :class="integration.enabled ? 'is-connected' : 'is-muted'"
                            x-text="integration.enabled ? integration.connected_label : 'Disabled'"></span>
                    </div>

                    <p class="figma-sa-integration-meta" x-show="!['guidance-chatbot','cross-domain'].includes(integration.name)">
                        <template x-if="integration.last_rotated_at">
                            <span>API keys last updated: <span x-text="integration.last_rotated_at"></span></span>
                        </template>
                        <template x-if="!integration.last_rotated_at">
                            <span>API keys never rotated</span>
                        </template>
                        <template x-if="integration.last_tested_at">
                            <span> · Tested <span x-text="integration.last_tested_at"></span></span>
                        </template>
                    </p>

                    <template x-if="integration.name === 'smtp'">
                        <div class="figma-sa-integration-smtp-hint">
                            <p><strong>Recommended (DigitalOcean):</strong> Fill <strong>Mailgun domain</strong> + <strong>Mailgun API key</strong> from your Mailgun dashboard — uses HTTPS, no SMTP ports.</p>
                            <p class="mt-1"><strong>Do not mix modes:</strong> For API, fill only Mailgun domain + API key + From email — leave SMTP host/username/password empty.</p>
                            <p class="mt-1"><strong>SMTP fallback only:</strong> <code>smtp.mailgun.org</code> port <strong>2525</strong> with Mailgun <em>SMTP password</em> from Domain → SMTP credentials (not the API key).</p>
                            <p class="mt-1"><strong>Sandbox:</strong> Add authorized recipients in Mailgun before testing.</p>
                        </div>
                    </template>

                    <template x-if="integration.name === 'smtp'">
                        <div class="figma-sa-integration-key-row mt-2">
                            <span class="figma-sa-integration-key-label">Send test to</span>
                            <input
                                type="email"
                                class="figma-sa-integration-field"
                                placeholder="you@company.com"
                                x-model="smtpTestEmail"
                                :disabled="testingIntegration === 'smtp'"
                                autocomplete="email"
                            >
                        </div>
                    </template>

                    <div class="mt-3 space-y-2">
                        <template x-for="field in integration.fields" :key="field.name">
                            <div class="figma-sa-integration-key-row">
                                <span class="figma-sa-integration-key-label" x-text="field.label"></span>
                                <template x-if="field.secret">
                                    <input type="password" autocomplete="new-password"
                                        class="figma-sa-integration-field"
                                        :placeholder="integration.secrets_masked[field.name] ? ('Current: ' + integration.secrets_masked[field.name]) : 'Not set'"
                                        x-model="integration._secrets[field.name]">
                                </template>
                                <template x-if="!field.secret && field.readonly">
                                    <input type="text" readonly
                                        class="figma-sa-integration-field opacity-90"
                                        :value="integration.settings[field.name] || '—'">
                                </template>
                                <template x-if="!field.secret && !field.readonly">
                                    <input :type="field.type === 'textarea' ? 'text' : (field.type || 'text')"
                                        class="figma-sa-integration-field"
                                        x-model="integration.settings[field.name]">
                                </template>
                                <button type="button" class="figma-sa-integration-copy-btn" title="Copy value" @click="copyField(integration, field)">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-8 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                </button>
                            </div>
                        </template>
                    </div>

                    <div class="figma-sa-integration-actions">
                        <a
                            x-show="integration.name === 'guidance-chatbot'"
                            :href="integration.manage_url || '#'"
                            class="figma-sa-integration-btn figma-sa-integration-btn--solid no-underline"
                        >Manage Guidance KB →</a>
                        <a
                            x-show="integration.name === 'cross-domain'"
                            :href="integration.manage_url || '#'"
                            class="figma-sa-integration-btn figma-sa-integration-btn--solid no-underline"
                        >View cross-domain intel →</a>
                        <button type="button" class="figma-sa-integration-btn" x-show="!['guidance-chatbot','cross-domain'].includes(integration.name)" @click="testIntegration(integration)" :disabled="testingIntegration === integration.name">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <span x-text="testingIntegration === integration.name ? 'Sending…' : 'Test'"></span>
                        </button>
                        <button type="button" class="figma-sa-integration-btn" x-show="!['guidance-chatbot','cross-domain'].includes(integration.name)" @click="rotateIntegration(integration)">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 4v5h5M20 20v-5h-5M4 9a8 8 0 0114-5M20 15a8 8 0 01-14 5"/></svg>
                            Rotate Keys
                        </button>
                        <button type="button" class="figma-sa-integration-btn figma-sa-integration-btn--solid" x-show="!['guidance-chatbot','cross-domain'].includes(integration.name)" @click="saveIntegration(integration)">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M5 13l4 4L19 7"/></svg>
                            Save
                        </button>
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
        smtpTestEmail: initial.smtpTestEmail || '',
        testingIntegration: null,
        query: '',
        statusFilter: '',
        toast: { message: '', type: 'success' },
        get statusLabel() {
            return { '': 'All Statuses', connected: 'Connected', disabled: 'Disabled' }[this.statusFilter] || 'All Statuses';
        },
        get filteredIntegrations() {
            const q = this.query.trim().toLowerCase();
            return this.integrations.filter((i) => {
                if (q && !i.display_name.toLowerCase().includes(q)) return false;
                if (this.statusFilter === 'connected' && !i.enabled) return false;
                if (this.statusFilter === 'disabled' && i.enabled) return false;
                return true;
            });
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
            if (!res.ok) throw new Error(data.message || 'Request failed');
            return data;
        },
        copyField(integration, field) {
            const value = field.secret
                ? (integration._secrets[field.name] || '')
                : (integration.settings[field.name] || '');
            if (!value) {
                this.notify('Nothing to copy yet.', 'error');
                return;
            }
            navigator.clipboard.writeText(String(value));
            this.notify('Copied to clipboard.');
        },
        async saveIntegration(integration, quiet = false) {
            try {
                const secrets = Object.fromEntries(
                    Object.entries(integration._secrets || {}).filter(([, v]) => v && String(v).trim().length > 0)
                );
                const payload = { enabled: integration.enabled, settings: integration.settings || {} };
                if (Object.keys(secrets).length > 0) payload.secrets = secrets;
                const data = await this.request(`${this.urls.integrations}/${integration.name}`, 'PUT', payload);
                Object.assign(integration, data.integration, {
                    _secrets: {},
                    fields: integration.fields,
                    subtitle: integration.subtitle,
                    connected_label: integration.connected_label,
                    manage_url: integration.manage_url,
                    settings: {
                        ...(integration.settings || {}),
                        ...((data.integration && data.integration.settings) || {}),
                    },
                });
                if (!quiet) this.notify('Integration saved.');
                else this.notify(integration.enabled ? 'Enabled.' : 'Disabled.');
            } catch (e) {
                this.notify(e.message, 'error');
            }
        },
        async testIntegration(integration) {
            this.testingIntegration = integration.name;
            try {
                const secrets = Object.fromEntries(
                    Object.entries(integration._secrets || {}).filter(([, v]) => v && String(v).trim().length > 0)
                );
                const body = integration.name === 'smtp'
                    ? {
                        test_email: String(this.smtpTestEmail || '').trim(),
                        settings: integration.settings || {},
                        secrets,
                    }
                    : undefined;
                const data = await this.request(`${this.urls.integrations}/${integration.name}/test`, 'POST', body);
                Object.assign(integration, data.integration, { _secrets: {}, fields: integration.fields });
                this.notify(data.message || 'Connection test ran.');
            } catch (e) {
                this.notify(e.message, 'error');
            } finally {
                this.testingIntegration = null;
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
