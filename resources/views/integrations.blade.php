@extends('layouts.admin')

@section('title', 'Paid Marketing — Platform Connections')

@section('content')
    <div class="space-y-6" x-data="integrationsPage()">
        @if (session('status'))
            <div class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-300">
                {{ session('status') }}
            </div>
        @endif

        <div class="flex flex-wrap items-center gap-3">
            <a href="{{ route('paid-marketing.dashboard') }}" class="rounded-lg border border-dark-border bg-dark-card px-4 py-2 text-sm text-gray-300 hover:bg-dark-border">Dashboard</a>
            <a href="{{ route('paid-marketing.detailed') }}" class="rounded-lg border border-dark-border bg-dark-card px-4 py-2 text-sm text-gray-300 hover:bg-dark-border">Detailed View</a>
            <a href="{{ route('integrations') }}" class="rounded-lg bg-accent px-4 py-2 text-sm font-medium text-white">Platform Connections</a>
            <a href="{{ route('paid-marketing.detection-settings') }}" class="rounded-lg border border-dark-border bg-dark-card px-4 py-2 text-sm text-gray-300 hover:bg-dark-border">Detection Settings</a>
        </div>

        <section class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <article class="rounded-xl border border-dark-border bg-dark-card p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-white">Google Ads</h2>
                        <p class="mt-1 text-sm text-gray-400">Connect via OAuth and sync ads accounts (AW).</p>
                    </div>
                    <span class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-medium"
                          :class="status?.google?.connected ? 'bg-emerald-500/20 text-emerald-300' : 'bg-gray-500/20 text-gray-300'"
                          x-text="status?.google?.connected ? `${status.google.accounts} accounts` : 'Not connected'"></span>
                </div>
                <div class="mt-4 flex flex-wrap items-center gap-2">
                    <a href="{{ route('integrations.google.redirect') }}"
                       class="rounded-xl bg-accent px-4 py-2 text-sm font-medium text-white hover:bg-accent-hover">
                        Connect with Google
                    </a>
                    <button type="button" @click="openPixelGuard()"
                            class="rounded-xl border border-dark-border bg-dark px-4 py-2 text-sm text-gray-200 hover:bg-dark-border">
                        Pixel Guard
                    </button>
                </div>
                <p class="mt-2 text-xs text-gray-500"
                   x-show="status && status.google && (!status.google.oauth_configured || !status.google.developer_token_configured)">
                    <span x-show="!status?.google?.oauth_configured">Missing GOOGLE_ADS_CLIENT_ID/SECRET. </span>
                    <span x-show="!status?.google?.developer_token_configured">GOOGLE_ADS_DEVELOPER_TOKEN not configured (live spend disabled).</span>
                </p>
            </article>

            <article class="rounded-xl border border-dark-border bg-dark-card p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-white">Direct Ads</h2>
                        <p class="mt-1 text-sm text-gray-400">Add manual integrations (Meta, Microsoft Ads, Custom).</p>
                    </div>
                    <span class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-medium"
                          :class="status?.direct?.connected ? 'bg-emerald-500/20 text-emerald-300' : 'bg-gray-500/20 text-gray-300'"
                          x-text="status?.direct?.connected ? `${status.direct.count} active` : 'Not connected'"></span>
                </div>

                <form @submit.prevent="addDirectAds()" class="mt-4 grid grid-cols-1 gap-2 md:grid-cols-2">
                    <select x-model="directForm.platform" required
                            class="rounded-xl border border-dark-border bg-dark px-3 py-2 text-sm text-white">
                        <option value="">Platform</option>
                        <option value="meta">Meta (Facebook / Instagram)</option>
                        <option value="microsoft">Microsoft Ads</option>
                        <option value="tiktok">TikTok Ads</option>
                        <option value="linkedin">LinkedIn Ads</option>
                        <option value="x">X / Twitter Ads</option>
                        <option value="custom">Custom</option>
                    </select>
                    <input type="text" x-model="directForm.account_label" placeholder="Account label (optional)"
                           class="rounded-xl border border-dark-border bg-dark px-3 py-2 text-sm text-white placeholder-gray-500">
                    <input type="text" x-model="directForm.account_id" placeholder="Account ID (optional)"
                           class="rounded-xl border border-dark-border bg-dark px-3 py-2 text-sm text-white placeholder-gray-500">
                    <input type="text" x-model="directForm.tag_id" placeholder="Tag / Pixel ID (optional)"
                           class="rounded-xl border border-dark-border bg-dark px-3 py-2 text-sm text-white placeholder-gray-500">
                    <button class="rounded-xl bg-accent px-4 py-2 text-sm font-medium text-white hover:bg-accent-hover md:col-span-2">
                        Add Integration
                    </button>
                </form>

                <div class="mt-4 space-y-2" x-show="directList.length > 0">
                    <template x-for="row in directList" :key="row.id">
                        <div class="flex items-center justify-between rounded-lg border border-dark-border bg-dark p-3">
                            <div>
                                <p class="text-sm font-semibold text-white" x-text="row.platform"></p>
                                <p class="text-xs text-gray-400">
                                    <span x-text="row.account_label || row.account_id || '—'"></span>
                                    <span x-show="row.tag_id"> · Tag: <span x-text="row.tag_id"></span></span>
                                </p>
                            </div>
                            <button type="button" @click="removeDirectAds(row.id)"
                                    class="rounded-lg border border-red-500/30 bg-red-500/10 px-3 py-1.5 text-xs font-medium text-red-300 hover:bg-red-500/20">
                                Remove
                            </button>
                        </div>
                    </template>
                </div>
            </article>
        </section>

        <section class="rounded-xl border border-dark-border bg-dark-card p-6">
            <h2 class="text-lg font-semibold text-white">Connect Your Platforms</h2>
            <p class="mt-1 text-sm text-gray-400">Connect Google via OAuth, then sync accessible Ads accounts (AW) and map domains.</p>

            <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-3">
                <div class="space-y-3 rounded-xl border border-dark-border bg-dark p-4 lg:col-span-1">
                    <p class="text-sm font-semibold text-white">Google Account</p>
                    <a href="{{ route('integrations.google.redirect') }}"
                       class="inline-flex w-full items-center justify-center rounded-xl bg-accent px-4 py-2.5 text-sm font-medium text-white hover:bg-accent-hover">
                        Connect with Google
                    </a>
                    <p class="text-xs text-gray-500">
                        Requires Google OAuth app credentials and Ads API developer token in `.env`.
                    </p>
                    @if ($connections->isNotEmpty())
                        <div class="mt-3 space-y-2">
                            @foreach ($connections as $connection)
                                <div class="rounded-lg border border-dark-border bg-dark-card p-3">
                                    <p class="text-sm text-white">{{ $connection->google_email }}</p>
                                    <p class="text-xs text-gray-500">Connected {{ $connection->connected_at?->diffForHumans() ?? '—' }}</p>
                                    <div class="mt-2 flex gap-2">
                                        <form method="POST" action="{{ route('integrations.google.sync-accounts', $connection) }}">
                                            @csrf
                                            <button class="rounded-lg bg-accent px-3 py-1.5 text-xs font-medium text-white hover:bg-accent-hover">Sync Accounts</button>
                                        </form>
                                        <form method="POST" action="{{ route('integrations.google.disconnect', $connection) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button class="rounded-lg border border-red-500/30 bg-red-500/10 px-3 py-1.5 text-xs font-medium text-red-300 hover:bg-red-500/20">Disconnect</button>
                                        </form>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <form method="POST" action="{{ route('integrations.store-account') }}" class="space-y-3 rounded-xl border border-dark-border bg-dark p-4 lg:col-span-2">
                    @csrf
                    <p class="text-sm font-semibold text-white">Google Ads Account (AW) — Manual fallback</p>
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                        <select name="google_connection_id" required class="w-full rounded-xl border border-dark-border bg-dark-card px-3 py-2 text-sm text-white focus:border-accent focus:outline-none">
                            <option value="">Select connected Google user</option>
                            @foreach ($connections as $connection)
                                <option value="{{ $connection->id }}">{{ $connection->google_email }}</option>
                            @endforeach
                        </select>
                        <input name="customer_id" type="text" required placeholder="Customer ID (1234567890)" class="w-full rounded-xl border border-dark-border bg-dark-card px-3 py-2 text-sm text-white focus:border-accent focus:outline-none">
                        <input name="display_customer_id" type="text" placeholder="Display ID (AW-1234567890)" class="w-full rounded-xl border border-dark-border bg-dark-card px-3 py-2 text-sm text-white focus:border-accent focus:outline-none">
                        <input name="google_tag_id" type="text" placeholder="Google Tag ID (AW-123456...)" class="w-full rounded-xl border border-dark-border bg-dark-card px-3 py-2 text-sm text-white focus:border-accent focus:outline-none">
                        <input name="account_name" type="text" placeholder="Account name" class="w-full rounded-xl border border-dark-border bg-dark-card px-3 py-2 text-sm text-white focus:border-accent focus:outline-none">
                        <input name="manager_customer_id" type="text" placeholder="Manager ID (optional)" class="w-full rounded-xl border border-dark-border bg-dark-card px-3 py-2 text-sm text-white focus:border-accent focus:outline-none">
                    </div>
                    <label class="inline-flex items-center gap-2 text-sm text-gray-300">
                        <input type="checkbox" name="is_manager" value="1" class="rounded border-dark-border bg-dark-card text-accent focus:ring-accent">
                        Manager account (MCC)
                    </label>
                    <button class="rounded-xl bg-accent px-4 py-2.5 text-sm font-medium text-white hover:bg-accent-hover">Save Ads Account</button>
                </form>
            </div>
        </section>

        <section class="rounded-xl border border-dark-border bg-dark-card p-6">
            <h2 class="text-lg font-semibold text-white">Link Domain to Connected ID</h2>
            <p class="mt-1 text-sm text-gray-400">This creates the domain-specific rule target just like ClickCease Tag + Pixel Guard mapping.</p>

            <form method="POST" action="{{ route('integrations.store-mapping') }}" class="mt-4 grid grid-cols-1 gap-3 lg:grid-cols-5">
                @csrf
                <select name="domain_id" required class="rounded-xl border border-dark-border bg-dark px-3 py-2 text-sm text-white focus:border-accent focus:outline-none lg:col-span-2">
                    <option value="">Select domain</option>
                    @foreach ($domains as $domain)
                        <option value="{{ $domain->id }}">{{ $domain->hostname }}</option>
                    @endforeach
                </select>
                <select name="google_ads_account_id" required class="rounded-xl border border-dark-border bg-dark px-3 py-2 text-sm text-white focus:border-accent focus:outline-none lg:col-span-2">
                    <option value="">Select connected account</option>
                    @foreach ($accounts as $account)
                        <option value="{{ $account->id }}">
                            {{ $account->display_customer_id ?: $account->customer_id }} — {{ $account->google_tag_id ?: 'No tag ID' }}
                        </option>
                    @endforeach
                </select>
                <select name="protection_type" class="rounded-xl border border-dark-border bg-dark px-3 py-2 text-sm text-white focus:border-accent focus:outline-none">
                    <option value="ip_blocking">IP Blocking</option>
                    <option value="pixel_guard">Pixel Guard</option>
                </select>
                <label class="inline-flex items-center gap-2 text-sm text-gray-300 lg:col-span-2">
                    <input type="checkbox" name="audience_exclusion_enabled" value="1" checked class="rounded border-dark-border bg-dark text-accent focus:ring-accent">
                    Audience exclusion enabled
                </label>
                <button class="rounded-xl bg-accent px-4 py-2.5 text-sm font-medium text-white hover:bg-accent-hover lg:col-span-1">Link Domain</button>
            </form>
        </section>

        <section class="rounded-xl border border-dark-border bg-dark-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[980px] text-left text-sm">
                    <thead>
                    <tr class="border-b border-dark-border bg-accent">
                        <th class="px-4 py-3 text-xs font-medium uppercase tracking-wider text-white">Platform</th>
                        <th class="px-4 py-3 text-xs font-medium uppercase tracking-wider text-white">Protection Type</th>
                        <th class="px-4 py-3 text-xs font-medium uppercase tracking-wider text-white">Connected ID (AW)</th>
                        <th class="px-4 py-3 text-xs font-medium uppercase tracking-wider text-white">Tag</th>
                        <th class="px-4 py-3 text-xs font-medium uppercase tracking-wider text-white">Domain</th>
                        <th class="px-4 py-3 text-xs font-medium uppercase tracking-wider text-white">Settings</th>
                        <th class="px-4 py-3 text-xs font-medium uppercase tracking-wider text-white"></th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-dark-border">
                    @forelse ($mappings as $mapping)
                        <tr class="hover:bg-gray-800/40">
                            <td class="px-4 py-3 text-gray-200">Google</td>
                            <td class="px-4 py-3 text-gray-300">{{ $mapping->protection_type === 'pixel_guard' ? 'Pixel Guard' : 'IP Blocking' }}</td>
                            <td class="px-4 py-3 text-gray-300">{{ $mapping->account->display_customer_id ?: $mapping->account->customer_id }}</td>
                            <td class="px-4 py-3 text-gray-300">{{ $mapping->account->google_tag_id ?: '—' }}</td>
                            <td class="px-4 py-3 text-gray-300">{{ $mapping->domain->hostname }}</td>
                            <td class="px-4 py-3">
                                <a href="{{ route('paid-marketing.detection-settings', ['domain_id' => $mapping->domain_id]) }}" class="text-accent hover:underline">Campaign Settings</a>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <form method="POST" action="{{ route('integrations.destroy-mapping', $mapping) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button class="rounded-lg border border-red-500/30 bg-red-500/10 px-3 py-1.5 text-xs font-medium text-red-300 hover:bg-red-500/20">Remove</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-10 text-center text-gray-400">No platform mappings yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            @if ($mappings->hasPages())
                <div class="border-t border-dark-border px-4 py-3">{{ $mappings->links() }}</div>
            @endif
        </section>

        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4"
             x-show="pixelGuard.open"
             x-cloak
             x-transition
             @keydown.escape.window="closePixelGuard()"
             @click.self="closePixelGuard()">
            <div class="w-full max-w-3xl rounded-xl border border-dark-border bg-dark-card shadow-xl">
                <div class="flex items-center justify-between border-b border-dark-border px-6 py-4">
                    <div>
                        <h3 class="text-lg font-semibold text-white">Pixel Guard</h3>
                        <p class="mt-1 text-xs text-gray-400">Save Google Tag IDs and toggle audience exclusion per domain.</p>
                    </div>
                    <button type="button" class="rounded-lg p-2 text-gray-400 hover:bg-dark-border hover:text-white" @click="closePixelGuard()">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="space-y-6 p-6">
                    <div>
                        <h4 class="text-sm font-semibold text-white">Google Tag IDs</h4>
                        <p class="mt-1 text-xs text-gray-400">Update the Google Tag ID (AW-...) per connected ads account.</p>
                        <div class="mt-3 space-y-2">
                            <template x-for="acc in pixelGuard.accounts" :key="acc.id">
                                <div class="flex flex-col gap-2 rounded-lg border border-dark-border bg-dark p-3 md:flex-row md:items-center">
                                    <div class="md:w-1/3">
                                        <p class="text-sm font-semibold text-white" x-text="acc.account_name || acc.display_customer_id || acc.customer_id"></p>
                                        <p class="text-xs text-gray-500" x-text="acc.display_customer_id || acc.customer_id"></p>
                                    </div>
                                    <input type="text" x-model="acc.google_tag_id" placeholder="AW-1234567890"
                                           class="flex-1 rounded-xl border border-dark-border bg-dark-card px-3 py-2 text-sm text-white placeholder-gray-500">
                                    <button type="button" @click="saveTagId(acc)"
                                            class="rounded-xl bg-accent px-3 py-2 text-xs font-medium text-white hover:bg-accent-hover">
                                        Save Tag
                                    </button>
                                </div>
                            </template>
                            <p class="text-xs text-gray-400" x-show="pixelGuard.accounts.length === 0">
                                No Google Ads accounts synced yet. Connect Google and click Sync Accounts first.
                            </p>
                        </div>
                    </div>

                    <div>
                        <h4 class="text-sm font-semibold text-white">Audience Exclusion</h4>
                        <p class="mt-1 text-xs text-gray-400">Auto-push detected fraudulent IPs into Google Customer Match audiences.</p>
                        <div class="mt-3 space-y-2">
                            <template x-for="m in pixelGuard.mappings" :key="m.id">
                                <div class="flex items-center justify-between rounded-lg border border-dark-border bg-dark p-3">
                                    <div>
                                        <p class="text-sm font-semibold text-white" x-text="m.domain?.hostname || '—'"></p>
                                        <p class="text-xs text-gray-500" x-text="m.account?.display_customer_id || m.account?.customer_id"></p>
                                    </div>
                                    <label class="inline-flex items-center gap-2 text-xs text-gray-300">
                                        <input type="checkbox" :checked="m.audience_exclusion_enabled"
                                               @change="toggleAudienceExclusion(m, $event.target.checked)">
                                        Enabled
                                    </label>
                                </div>
                            </template>
                            <p class="text-xs text-gray-400" x-show="pixelGuard.mappings.length === 0">
                                No Pixel Guard mappings yet. Use the "Link Domain" form below with Protection Type = Pixel Guard.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function integrationsPage() {
            return {
                status: null,
                directForm: { platform: '', account_label: '', account_id: '', tag_id: '' },
                directList: [],
                pixelGuard: { open: false, accounts: [], mappings: [] },
                async init() {
                    await Promise.all([this.loadStatus(), this.loadDirect()]);
                },
                async loadStatus() {
                    try {
                        this.status = await fetch('/integrations/status').then(r => r.json());
                    } catch (e) { this.status = null; }
                },
                async loadDirect() {
                    try {
                        this.directList = await fetch('/integrations/direct-ads').then(r => r.json());
                    } catch (e) { this.directList = []; }
                },
                async addDirectAds() {
                    if (!this.directForm.platform) return;
                    const res = await fetch('/integrations/direct-ads', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        },
                        body: JSON.stringify(this.directForm),
                    }).then(r => r.json());
                    if (res?.ok) {
                        this.directForm = { platform: '', account_label: '', account_id: '', tag_id: '' };
                        await this.loadDirect();
                        await this.loadStatus();
                    }
                },
                async removeDirectAds(id) {
                    if (! confirm('Remove this integration?')) return;
                    await fetch(`/integrations/direct-ads/${id}`, {
                        method: 'DELETE',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        },
                    });
                    await this.loadDirect();
                    await this.loadStatus();
                },
                async openPixelGuard() {
                    this.pixelGuard.open = true;
                    const data = await fetch('/integrations/google/pixel-guard').then(r => r.json());
                    this.pixelGuard.accounts = data.accounts || [];
                    this.pixelGuard.mappings = data.mappings || [];
                },
                closePixelGuard() {
                    this.pixelGuard.open = false;
                },
                async saveTagId(acc) {
                    const res = await fetch('/integrations/google/pixel-guard', {
                        method: 'PUT',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        },
                        body: JSON.stringify({ account_id: acc.id, google_tag_id: acc.google_tag_id || '' }),
                    }).then(r => r.json()).catch(() => null);
                    if (res?.ok) {
                        acc.google_tag_id = res.account.google_tag_id;
                    }
                },
                async toggleAudienceExclusion(mapping, enabled) {
                    const res = await fetch('/integrations/google/audience-exclusion', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        },
                        body: JSON.stringify({ mapping_id: mapping.id, enabled }),
                    }).then(r => r.json()).catch(() => null);
                    if (res?.ok) {
                        mapping.audience_exclusion_enabled = res.enabled;
                    }
                },
            };
        }

    </script>
@endsection
