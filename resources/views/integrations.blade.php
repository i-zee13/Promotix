@extends('layouts.admin')

@section('title', 'Platform Integrate')
@section('subtitle', 'Connect Google Ads, direct ad platforms, and per-domain protection')

@section('content')
    <div class="space-y-6" x-data="integrationsPage()">
        <x-ui.page-header title="Platform Integrate" subtitle="Connect Google Ads, direct ad platforms, and per-domain protection">
            <x-slot:actions>
                <x-ui.button variant="outline" size="sm" href="{{ route('paid-marketing.dashboard') }}">Dashboard</x-ui.button>
                <x-ui.button variant="outline" size="sm" href="{{ route('paid-marketing.detection-settings') }}">Detection Panel</x-ui.button>
            </x-slot:actions>
        </x-ui.page-header>

        @if (session('status'))
            <div class="brand-pill brand-pill-success">{{ session('status') }}</div>
        @endif

        {{-- Top: Google + Direct Ads --}}
        <section class="grid grid-cols-1 gap-4 md:grid-cols-2">
            {{-- Google card --}}
            <x-ui.card>
                <header class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-white">Google Ads</h2>
                        <p class="mt-1 text-sm text-night-300">Connect via OAuth and sync ad accounts (AW).</p>
                    </div>
                    <span class="brand-pill"
                          :class="status?.google?.connected ? 'brand-pill-success' : 'brand-pill-neutral'"
                          x-text="status?.google?.connected ? `${status.google.accounts} accounts` : 'Not connected'"></span>
                </header>
                <div class="mt-4 flex flex-wrap items-center gap-2">
                    <x-ui.button variant="primary" href="{{ route('integrations.google.redirect') }}">Connect with Google</x-ui.button>
                    <x-ui.button type="button" variant="outline" @click="openPixelGuard()">Pixel Guard</x-ui.button>
                </div>
                <p class="mt-2 text-xs text-amber-300"
                   x-show="status && status.google && (!status.google.oauth_configured || !status.google.developer_token_configured)">
                    <span x-show="!status?.google?.oauth_configured">Missing GOOGLE_ADS_CLIENT_ID/SECRET. </span>
                    <span x-show="!status?.google?.developer_token_configured">GOOGLE_ADS_DEVELOPER_TOKEN not configured (live spend disabled).</span>
                </p>
            </x-ui.card>

            {{-- Direct ads card --}}
            <x-ui.card>
                <header class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-white">Direct Ads</h2>
                        <p class="mt-1 text-sm text-night-300">Add manual integrations (Meta, Microsoft, custom).</p>
                    </div>
                    <span class="brand-pill"
                          :class="status?.direct?.connected ? 'brand-pill-success' : 'brand-pill-neutral'"
                          x-text="status?.direct?.connected ? `${status.direct.count} active` : 'Not connected'"></span>
                </header>

                <form @submit.prevent="addDirectAds()" class="mt-4 grid grid-cols-1 gap-2 md:grid-cols-2">
                    <select x-model="directForm.platform" required class="brand-select">
                        <option value="">Platform</option>
                        <option value="meta">Meta (Facebook / Instagram)</option>
                        <option value="microsoft">Microsoft Ads</option>
                        <option value="tiktok">TikTok Ads</option>
                        <option value="linkedin">LinkedIn Ads</option>
                        <option value="x">X / Twitter Ads</option>
                        <option value="custom">Custom</option>
                    </select>
                    <input type="text" x-model="directForm.account_label" placeholder="Account label (optional)" class="brand-input">
                    <input type="text" x-model="directForm.account_id"    placeholder="Account ID (optional)" class="brand-input">
                    <input type="text" x-model="directForm.tag_id"        placeholder="Tag / Pixel ID (optional)" class="brand-input">
                    <button class="brand-btn-primary md:col-span-2">Add integration</button>
                </form>

                <div class="mt-4 space-y-2" x-show="directList.length > 0">
                    <template x-for="row in directList" :key="row.id">
                        <div class="flex items-center justify-between rounded-xl border border-night-700 bg-night-900/60 px-3 py-2.5">
                            <div>
                                <p class="text-sm font-semibold text-white" x-text="row.platform"></p>
                                <p class="text-xs text-night-400">
                                    <span x-text="row.account_label || row.account_id || '—'"></span>
                                    <span x-show="row.tag_id"> · Tag: <span x-text="row.tag_id"></span></span>
                                </p>
                            </div>
                            <button type="button" class="brand-btn-soft px-3 py-1.5 text-xs"
                                    @click="removeDirectAds(row.id)"
                                    style="background: rgba(244, 63, 94, 0.15); color: #fda4af;">
                                Remove
                            </button>
                        </div>
                    </template>
                </div>
            </x-ui.card>
        </section>

        {{-- Connect / accounts --}}
        <x-ui.card title="Connect Your Platforms" subtitle="Connect Google via OAuth, then sync accessible Ads accounts and map domains">
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                {{-- Google account column --}}
                <div class="space-y-3 rounded-xl border border-night-700 bg-night-900/60 p-4 lg:col-span-1">
                    <p class="text-sm font-semibold text-white">Google Account</p>
                    <a href="{{ route('integrations.google.redirect') }}" class="brand-btn-primary w-full">Connect with Google</a>
                    <p class="text-xs text-night-400">Requires Google OAuth credentials and Ads API developer token in <code>.env</code>.</p>

                    @if ($connections->isNotEmpty())
                        <div class="mt-3 space-y-2">
                            @foreach ($connections as $connection)
                                <div class="rounded-xl border border-night-700 bg-night-900 p-3">
                                    <p class="text-sm text-white">{{ $connection->google_email }}</p>
                                    <p class="text-xs text-night-400">Connected {{ $connection->connected_at?->diffForHumans() ?? '—' }}</p>
                                    <div class="mt-2 flex gap-2">
                                        <form method="POST" action="{{ route('integrations.google.sync-accounts', $connection) }}">
                                            @csrf
                                            <button class="brand-btn-soft px-3 py-1.5 text-xs">Sync accounts</button>
                                        </form>
                                        <form method="POST" action="{{ route('integrations.google.disconnect', $connection) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button class="brand-btn-outline px-3 py-1.5 text-xs"
                                                    style="border-color: rgba(244, 63, 94, 0.3); color: #fda4af;">
                                                Disconnect
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Manual fallback form --}}
                <form method="POST" action="{{ route('integrations.store-account') }}"
                      class="space-y-3 rounded-xl border border-night-700 bg-night-900/60 p-4 lg:col-span-2">
                    @csrf
                    <p class="text-sm font-semibold text-white">Google Ads Account (AW) — manual fallback</p>
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                        <select name="google_connection_id" required class="brand-select">
                            <option value="">Select connected Google user</option>
                            @foreach ($connections as $connection)
                                <option value="{{ $connection->id }}">{{ $connection->google_email }}</option>
                            @endforeach
                        </select>
                        <input name="customer_id" type="text" required placeholder="Customer ID (1234567890)" class="brand-input">
                        <input name="display_customer_id" type="text" placeholder="Display ID (AW-1234567890)" class="brand-input">
                        <input name="google_tag_id" type="text" placeholder="Google Tag ID (AW-…)" class="brand-input">
                        <input name="account_name" type="text" placeholder="Account name" class="brand-input">
                        <input name="manager_customer_id" type="text" placeholder="Manager ID (optional)" class="brand-input">
                    </div>
                    <label class="inline-flex items-center gap-2 text-sm text-night-100">
                        <input type="checkbox" name="is_manager" value="1" class="rounded border-night-700 bg-night-900 text-brand-500 focus:ring-brand-400">
                        Manager account (MCC)
                    </label>
                    <button class="brand-btn-primary">Save Ads Account</button>
                </form>
            </div>
        </x-ui.card>

        {{-- Link domain --}}
        <x-ui.card title="Link Domain to Connected ID" subtitle="Creates the domain-specific rule target">
            <form method="POST" action="{{ route('integrations.store-mapping') }}" class="grid grid-cols-1 gap-3 lg:grid-cols-5">
                @csrf
                <select name="domain_id" required class="brand-select lg:col-span-2">
                    <option value="">Select domain</option>
                    @foreach ($domains as $domain)
                        <option value="{{ $domain->id }}">{{ $domain->hostname }}</option>
                    @endforeach
                </select>
                <select name="google_ads_account_id" required class="brand-select lg:col-span-2">
                    <option value="">Select connected account</option>
                    @foreach ($accounts as $account)
                        <option value="{{ $account->id }}">
                            {{ $account->display_customer_id ?: $account->customer_id }} — {{ $account->google_tag_id ?: 'No tag ID' }}
                        </option>
                    @endforeach
                </select>
                <select name="protection_type" class="brand-select">
                    <option value="ip_blocking">IP Blocking</option>
                    <option value="pixel_guard">Pixel Guard</option>
                </select>
                <label class="inline-flex items-center gap-2 text-sm text-night-100 lg:col-span-2">
                    <input type="checkbox" name="audience_exclusion_enabled" value="1" checked
                           class="rounded border-night-700 bg-night-900 text-brand-500 focus:ring-brand-400">
                    Audience exclusion enabled
                </label>
                <button class="brand-btn-primary lg:col-span-1">Link domain</button>
            </form>
        </x-ui.card>

        {{-- Existing mappings --}}
        <x-ui.card variant="flat">
            <div class="overflow-x-auto">
                <table class="brand-table min-w-[980px]">
                    <thead>
                        <tr>
                            <th>Platform</th>
                            <th>Protection</th>
                            <th>Connected ID</th>
                            <th>Tag</th>
                            <th>Domain</th>
                            <th>Settings</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($mappings as $mapping)
                            <tr>
                                <td class="font-medium">Google</td>
                                <td>
                                    <x-ui.pill :tone="$mapping->protection_type === 'pixel_guard' ? 'purple' : 'neutral'">
                                        {{ $mapping->protection_type === 'pixel_guard' ? 'Pixel Guard' : 'IP Blocking' }}
                                    </x-ui.pill>
                                </td>
                                <td class="text-night-200">{{ $mapping->account->display_customer_id ?: $mapping->account->customer_id }}</td>
                                <td class="text-night-200">{{ $mapping->account->google_tag_id ?: '—' }}</td>
                                <td class="text-night-200">{{ $mapping->domain->hostname }}</td>
                                <td>
                                    <a href="{{ route('paid-marketing.detection-settings', ['domain_id' => $mapping->domain_id]) }}"
                                       class="text-sm font-medium text-brand-200 hover:text-white">Campaign Settings</a>
                                </td>
                                <td class="text-right">
                                    <form method="POST" action="{{ route('integrations.destroy-mapping', $mapping) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button class="brand-btn-outline px-3 py-1.5 text-xs"
                                                style="border-color: rgba(244, 63, 94, 0.3); color: #fda4af;">Remove</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="py-10 text-center text-night-300">No platform mappings yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($mappings->hasPages())
                <div class="mt-4 border-t border-night-700/60 pt-4">{{ $mappings->links() }}</div>
            @endif
        </x-ui.card>

        {{-- Pixel Guard modal --}}
        <div class="brand-modal-overlay" x-show="pixelGuard.open" x-cloak x-transition
             @keydown.escape.window="closePixelGuard()" @click.self="closePixelGuard()">
            <div class="brand-modal max-w-3xl">
                <header class="mb-4 flex items-start justify-between gap-3">
                    <div>
                        <h3 class="brand-modal-title">Pixel Guard</h3>
                        <p class="mt-1 text-xs text-night-300">Save Google Tag IDs and toggle audience exclusion per domain.</p>
                    </div>
                    <button type="button" class="rounded-lg p-1.5 text-night-300 hover:bg-night-800 hover:text-white" @click="closePixelGuard()">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </header>

                <div class="space-y-6">
                    <div>
                        <h4 class="text-sm font-semibold text-white">Google Tag IDs</h4>
                        <p class="mt-1 text-xs text-night-400">Update the Google Tag ID (AW-…) per connected ads account.</p>
                        <div class="mt-3 space-y-2">
                            <template x-for="acc in pixelGuard.accounts" :key="acc.id">
                                <div class="flex flex-col gap-2 rounded-xl border border-night-700 bg-night-900/60 p-3 md:flex-row md:items-center">
                                    <div class="md:w-1/3">
                                        <p class="text-sm font-semibold text-white" x-text="acc.account_name || acc.display_customer_id || acc.customer_id"></p>
                                        <p class="text-xs text-night-400" x-text="acc.display_customer_id || acc.customer_id"></p>
                                    </div>
                                    <input type="text" x-model="acc.google_tag_id" placeholder="AW-1234567890" class="brand-input flex-1">
                                    <button type="button" @click="saveTagId(acc)" class="brand-btn-primary px-3 py-2 text-xs">Save tag</button>
                                </div>
                            </template>
                            <p class="text-xs text-night-400" x-show="pixelGuard.accounts.length === 0">
                                No Google Ads accounts synced yet. Connect Google and click Sync Accounts first.
                            </p>
                        </div>
                    </div>

                    <div>
                        <h4 class="text-sm font-semibold text-white">Audience Exclusion</h4>
                        <p class="mt-1 text-xs text-night-400">Auto-push detected fraudulent IPs into Google Customer Match audiences.</p>
                        <div class="mt-3 space-y-2">
                            <template x-for="m in pixelGuard.mappings" :key="m.id">
                                <div class="flex items-center justify-between rounded-xl border border-night-700 bg-night-900/60 p-3">
                                    <div>
                                        <p class="text-sm font-semibold text-white" x-text="m.domain?.hostname || '—'"></p>
                                        <p class="text-xs text-night-400" x-text="m.account?.display_customer_id || m.account?.customer_id"></p>
                                    </div>
                                    <label class="inline-flex items-center gap-2 text-xs text-night-200">
                                        <input type="checkbox" :checked="m.audience_exclusion_enabled"
                                               class="rounded border-night-700 bg-night-900 text-brand-500 focus:ring-brand-400"
                                               @change="toggleAudienceExclusion(m, $event.target.checked)">
                                        Enabled
                                    </label>
                                </div>
                            </template>
                            <p class="text-xs text-night-400" x-show="pixelGuard.mappings.length === 0">
                                No Pixel Guard mappings yet. Use the "Link Domain" form above with Protection Type = Pixel Guard.
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
                async init() { await Promise.all([this.loadStatus(), this.loadDirect()]); },
                async loadStatus() {
                    try { this.status = await fetch('/integrations/status').then(r => r.json()); }
                    catch (e) { this.status = null; }
                },
                async loadDirect() {
                    try { this.directList = await fetch('/integrations/direct-ads').then(r => r.json()); }
                    catch (e) { this.directList = []; }
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
                closePixelGuard() { this.pixelGuard.open = false; },
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
                    if (res?.ok) acc.google_tag_id = res.account.google_tag_id;
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
                    if (res?.ok) mapping.audience_exclusion_enabled = res.enabled;
                },
            };
        }
    </script>
@endsection
