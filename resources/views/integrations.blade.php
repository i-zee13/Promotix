@extends('layouts.admin')

@section('title', 'Platform Integrate')

@php
    $googleOAuthConnected = $connections->isNotEmpty();
    $directConnected = $directAds->isNotEmpty();
    $googleStatusConnected = $platformReady ?? false;
    $menuDomain = $domains->first();
    $primaryConnection = $connections->first();
@endphp

@section('rightbar')
<div class="figma-rightbar-default pi-rightbar">
    @include('partials.figma-rightbar-header-actions')

    <div class="figma-rightbar-center mt-[16px] border-t-2 border-[#5a2a99] pt-[14px]">
        <h2 class="mb-[10px] w-full max-w-[168px] text-[16px] font-bold text-[#a9a9a9]">Quick Actions</h2>
        <div class="mx-auto grid w-full max-w-[168px] grid-cols-2 gap-[10px]">
            <a href="{{ route('domains.index') }}" class="paid-quick-action" title="Test Tracking">
                @include('partials.sidebar-icon', ['name' => 'eye', 'class' => 'h-[16px] w-[16px]'])
                <span>Test Tracking</span>
            </a>
            @if ($primaryConnection)
                <form method="POST" action="{{ route('integrations.google.sync-accounts', $primaryConnection) }}" class="contents">
                    @csrf
                    <button type="submit" class="paid-quick-action" title="Sync Ads">
                        @include('partials.sidebar-icon', ['name' => 'plug', 'class' => 'h-[16px] w-[16px]'])
                        <span>Sync Ads</span>
                </button>
                </form>
            @else
                <a href="{{ route('integrations.google.redirect') }}" class="paid-quick-action" title="Sync Ads">
                    @include('partials.sidebar-icon', ['name' => 'plug', 'class' => 'h-[16px] w-[16px]'])
                    <span>Sync Ads</span>
                </a>
            @endif
            <a href="{{ route('domains.index') }}" class="paid-quick-action" title="Generate Tag">
                @include('partials.sidebar-icon', ['name' => 'tag', 'class' => 'h-[16px] w-[16px]'])
                <span>Generate Tag</span>
            </a>
            <button type="button" onclick="window.dispatchEvent(new CustomEvent('open-promotix-settings',{detail:{tab:'reports'}}))" class="paid-quick-action" title="View Reports">
                @include('partials.sidebar-icon', ['name' => 'chart', 'class' => 'h-[16px] w-[16px]'])
                <span>View Reports</span>
            </button>
        </div>
    </div>

    <div class="mt-[18px] border-t-2 border-[#5a2a99] pt-[14px]">
        <h2 class="mb-[10px] text-[16px] font-bold text-[#a9a9a9]">System Overview</h2>
        <div class="figma-rightbar-sys space-y-[8px] text-[10px] text-white/75">
            <div class="paid-sys-row">
                <span>Server status</span>
                <span id="pi-sys-server-status" class="text-emerald-200">Online</span>
            </div>
            <div class="paid-sys-row">
                <span>Events today</span>
                <span id="pi-sys-events-today" class="text-white/90">{{ number_format((int) ($connectionHealth['events_today'] ?? 0)) }}</span>
            </div>
            <div class="paid-sys-row">
                <span>Tracking</span>
                <span id="pi-sys-tracking" class="{{ ($tagReady ?? false) ? 'text-emerald-200' : 'text-white/55' }}">
                    {{ ($tagReady ?? false) ? 'Active' : 'Pending' }}
                </span>
            </div>
            <div class="paid-sys-row">
                <span>Google Ads API</span>
                <span id="pi-sys-google-api" class="{{ $googleOAuthConnected ? 'text-emerald-200' : 'text-white/55' }}">
                    {{ $googleOAuthConnected ? 'Connected' : 'Not connected' }}
                </span>
            </div>
        </div>
    </div>
</div>
<style>
    .pi-rightbar .paid-quick-action {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 6px;
        min-height: 64px;
        padding: 8px 6px;
        border-radius: 6px;
        background: var(--brand-primary);
        color: #fff;
        text-align: center;
        font-size: 9px;
        font-weight: 600;
        line-height: 1.2;
        border: 0;
        cursor: pointer;
        text-decoration: none;
        width: 100%;
    }
    .pi-rightbar .paid-quick-action:hover {
        background: var(--figma-chrome-accent-hover);
        color: #fff;
    }
</style>
<script>
document.addEventListener('DOMContentLoaded', () => {
    window.promotixPageLoader?.show('Loading Integrations…');
    fetch('/overview/summary', { headers: { Accept: 'application/json' } })
        .then((r) => (r.ok ? r.json() : null))
        .then((data) => {
            if (!data) return;
            const conn = data.connectionStatus || {};
            const eventsEl = document.getElementById('pi-sys-events-today');
            const apiEl = document.getElementById('pi-sys-google-api');
            const trackEl = document.getElementById('pi-sys-tracking');
            if (eventsEl && conn.eventsToday != null) {
                eventsEl.textContent = Number(conn.eventsToday).toLocaleString();
            }
            if (apiEl && conn.googleAdsApi) {
                const label = String(conn.googleAdsApi);
                apiEl.textContent = label;
                apiEl.className = /not connected|error/i.test(label) ? 'text-white/55' : 'text-emerald-200';
            }
            if (trackEl && conn.tracking) {
                const on = /healthy|active/i.test(String(conn.tracking));
                trackEl.textContent = on ? 'Active' : 'Pending';
                trackEl.className = on ? 'text-emerald-200' : 'text-white/55';
            }
        })
        .catch(() => {})
        .finally(() => window.promotixPageLoader?.hide());
});
</script>
@endsection

@section('content')
<div
    class="brand-page-bg min-h-[calc(100vh-49px)]"
    x-data="platformIntegrations(@js([
        'csrf' => csrf_token(),
        'directStoreUrl' => url('/integrations/direct-ads'),
        'trackingLink' => $menuDomain ? url('/tag/' . $menuDomain->domain_key . '.js') : null,
        'statusUrl' => url('/integrations/status'),
        'logsUrl' => url('/integrations/logs'),
        'testUrl' => $primaryConnection ? route('integrations.google.test', $primaryConnection) : null,
        'disconnectUrl' => $primaryConnection ? route('integrations.google.disconnect', $primaryConnection) : null,
        'paidMarketingConnectUrl' => route('domains.paid-marketing.connect', ['domain' => 0]),
        'domainConnections' => $domainConnections,
        'connectionHealth' => $connectionHealth ?? [],
        'tagReady' => (bool) ($tagReady ?? false),
        'botReady' => (bool) ($botReady ?? false),
        'googleOAuthConnected' => (bool) $googleOAuthConnected,
        'syncLogs' => ($syncLogs ?? collect())->map(fn ($log) => [
            'id' => $log->id,
            'action' => $log->action,
            'status' => $log->status,
            'message' => $log->message,
            'domain' => $log->domain?->hostname,
            'created_at' => optional($log->created_at)->toIso8601String(),
        ])->values(),
        'directInitial' => $directAds->map(fn ($row) => [
            'id' => $row->id,
            'platform' => $row->platform,
            'account_label' => $row->account_label,
            'account_id' => $row->account_id,
            'tag_id' => $row->tag_id,
        ])->values(),
        'platformRows' => ($platformRows ?? collect())->values(),
        'trackingIds' => ($trackingIds ?? collect())->values(),
        'setupProgress' => $setupProgress ?? [],
        'setupProgressByDomain' => $setupProgressByDomain ?? [],
    ]))"
    @platform-menu.window="handlePlatformMenu($event.detail)"
>
    <section class="mx-auto w-full max-w-[1180px] px-[12px] pb-[28px] pt-[28px] sm:px-[18px] xl:max-w-none xl:px-[19px] xl:pt-[68px]">
        <div class="bp-adv-page-head mb-[23px] flex flex-col gap-[14px] sm:flex-row sm:items-center sm:justify-between">
            <div class="flex flex-wrap items-center gap-[12px] shrink-0">
                <h1 class="text-[24px] font-semibold leading-none text-[#a9a9a9] sm:text-[32px]">Paid Marketing</h1>
                <span class="h-[34px] w-[2px] bg-[#a9a9a9] sm:h-[44px]"></span>
                <span class="text-[24px] font-semibold leading-none text-[#a9a9a9] sm:text-[32px]">Platform Integration</span>
            </div>

            <div class="figma-filter-bar figma-filter-bar--overview figma-filter-bar--pi ml-auto flex min-h-[54px] w-fit max-w-full flex-nowrap overflow-visible rounded-[10px] border border-white/25 bg-[#d9d9d9] text-[10px] text-black shadow-[0_0_0_rgba(255,255,255,.25)]">
                <label class="flex w-[150px] shrink-0 flex-col justify-center border-r border-black/20 px-[10px] py-[6px]">
                    <span class="mb-[3px] text-[8px] font-semibold uppercase text-black/55">Domain</span>
                    <div class="figma-filter-select-wrap">
                        <select x-model="selectedDomainId" class="figma-filter-control h-[23px] w-full rounded-[3px] border-0 bg-[#101010] py-0 pl-[8px] pr-[26px] text-[11px] text-[#8c8787] focus:ring-0">
                            <option value="">All Domains</option>
                            @foreach ($manualDomains as $domain)
                                <option value="{{ $domain->id }}">{{ $domain->hostname }}</option>
                            @endforeach
                        </select>
                    </div>
                </label>
                <label class="flex w-[170px] shrink-0 flex-col justify-center border-r border-black/20 px-[10px] py-[6px]">
                    <span class="mb-[3px] text-[8px] font-semibold uppercase text-black/55">Google Ads Account</span>
                    <div class="figma-filter-select-wrap">
                        <select x-model="selectedAdsAccountId" class="figma-filter-control h-[23px] w-full rounded-[3px] border-0 bg-[#101010] py-0 pl-[8px] pr-[26px] text-[11px] text-[#8c8787] focus:ring-0">
                            <option value="">All Accounts</option>
                            @php
                                $linkedFilterAccounts = collect($trackingIds ?? [])
                                    ->unique('account_id')
                                    ->values();
                            @endphp
                            @foreach ($linkedFilterAccounts as $row)
                                <option value="{{ $row['account_id'] }}">{{ $row['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                </label>
                <label class="flex w-[140px] shrink-0 flex-col justify-center border-r border-black/20 px-[10px] py-[6px]">
                    <span class="mb-[3px] text-[8px] font-semibold uppercase text-black/55">Landing Page</span>
                    <div class="figma-filter-select-wrap">
                        <select x-model="selectedLandingPage" class="figma-filter-control h-[23px] w-full rounded-[3px] border-0 bg-[#101010] py-0 pl-[8px] pr-[26px] text-[11px] text-[#8c8787] focus:ring-0">
                            <option value="">All Pages</option>
                        </select>
                    </div>
                </label>
                @include('partials.figma-filter-date-fields')
            </div>
        </div>

        @if (session('status'))
            <div class="mb-[14px] rounded-[8px] border border-white/30 bg-[var(--brand-primary)]/70 px-[14px] py-[10px] text-[13px] text-white">{{ session('status') }}</div>
        @endif

        {{-- First row: Connect Platforms | Status + Health --}}
        <div class="pi-first-row">
            <section class="pi-connect-card">
                <h2 class="pi-section-title">Connect Your Platforms</h2>

                <div class="pi-connect-grid">
                    {{-- Google Ads OAuth panel --}}
                    <article class="pi-panel">
                        <div class="flex items-start justify-between gap-[8px]">
                            <div class="flex min-w-0 flex-1 gap-[16px]">
                                <div class="w-[88px] shrink-0 text-center">
                                    <div class="mx-auto mb-[10px] flex h-[72px] w-[72px] items-center justify-center rounded-[8px] bg-white">
                                        @include('partials.icons.google', ['class' => 'h-[44px] w-[44px]'])
                                    </div>
                                    <p class="text-[15px] font-semibold leading-none text-white">Google Ads</p>
                                    <span class="pi-status-pill mt-[8px]" :class="googleOAuthConnected ? 'is-on' : 'is-off'">
                                        <span class="pi-status-dot"></span>
                                        <span x-text="googleOAuthConnected ? 'Connected' : 'Not connected'"></span>
                                    </span>
                                </div>

                                <div class="flex min-w-0 flex-1 flex-col justify-center gap-[8px]">
                                    <a href="{{ route('domains.index') }}" class="pi-ghost-btn">
                                        <span class="font-mono text-[11px]">&lt;/&gt;</span>
                                        <span>Tag Manager</span>
                                    </a>
                                    <a href="{{ route('paid-marketing.dashboard') }}" class="pi-ghost-btn">
                                        @include('partials.sidebar-icon', ['name' => 'chart', 'class' => 'h-[14px] w-[14px] shrink-0'])
                                        <span>Paid Marketing</span>
                                    </a>
                                    <a href="{{ route('analytics.dashboard') }}" class="pi-ghost-btn">
                                        @include('partials.sidebar-icon', ['name' => 'shield-check', 'class' => 'h-[14px] w-[14px] shrink-0'])
                                        <span>Bot Protection</span>
                                    </a>

                                    @if ($googleOAuthConnected && $primaryConnection)
                                        <form method="POST" action="{{ route('integrations.google.sync-accounts', $primaryConnection) }}">
                                            @csrf
                                            <button type="submit" class="pi-primary-btn">
                                                <svg class="h-[14px] w-[14px] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                                Sync Ads
                                            </button>
                                        </form>
                                        <a href="{{ route('integrations.google.redirect') }}" class="pi-text-link" title="Reconnect if Google Ads token expired">Reconnect Google</a>
                                        <form method="POST" action="{{ route('integrations.google.reconnect-all') }}" onsubmit="return confirm('Refresh tokens for all Google connections / domains? Failed ones still need OAuth reconnect.');">
                                            @csrf
                                            <button type="submit" class="pi-text-link" title="Force-refresh tokens for every Google connection">Reconnect all domains</button>
                                        </form>
                                        <a href="{{ route('integrations.google.redirect') }}" class="pi-text-link">+ Add Google Login</a>
                                    @else
                                        <a href="{{ route('integrations.google.redirect') }}" class="pi-primary-btn">
                                            <span class="text-[14px] leading-none">+</span>
                                            Connect Google
                                        </a>
                                    @endif
                                </div>
                            </div>
                            <x-integrations.google-platform-menu
                                menu-id="google"
                                :google-o-auth-connected="$googleOAuthConnected"
                                :menu-domain="$menuDomain"
                                :primary-connection="$primaryConnection"
                            />
                        </div>
                    </article>

                    {{-- Direct Ads panel --}}
                    <article class="pi-panel">
                        <div class="mb-[14px] flex items-start justify-between gap-[8px]">
                            <div class="flex items-center gap-[12px]">
                                @include('partials.icons.google-ads', ['class' => 'h-[36px] w-[36px]'])
                                <p class="text-[16px] font-semibold text-white">Direct Ads</p>
                            </div>
                            <x-integrations.direct-ads-platform-menu menu-id="direct" />
                        </div>

                        <form class="flex flex-col gap-[12px]" @submit.prevent="addDirectAds()">
                            <label class="block">
                                <span class="mb-[5px] flex items-center gap-[5px] text-[10px] font-medium text-white/65">
                                    Google Ads Customer ID
                                    <span class="inline-flex h-[12px] w-[12px] items-center justify-center rounded-full border border-white/35 text-[8px]" title="Your Google Ads customer ID, e.g. 123-456-7890">i</span>
                                </span>
                                <div class="pi-field">
                                    <input
                                        id="direct-account-id"
                                        x-model="directForm.account_id"
                                        placeholder="123-456-7890"
                                        class="pi-field__input"
                                    >
                                    <button type="button" class="pi-field__copy" title="Copy" @click="copyText(directForm.account_id)">
                                        <svg class="h-[13px] w-[13px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 8h10v12H8z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 16H4V4h12v2"/></svg>
                                    </button>
                                </div>
                            </label>
                            <label class="block">
                                <span class="mb-[5px] flex items-center gap-[5px] text-[10px] font-medium text-white/65">
                                    Conversion Tag ID
                                    <span class="inline-flex h-[12px] w-[12px] items-center justify-center rounded-full border border-white/35 text-[8px]" title="Google conversion / AW tag ID">i</span>
                                </span>
                                <div class="pi-field">
                                    <input
                                        id="direct-tag-id"
                                        x-model="directForm.tag_id"
                                        placeholder="AW-123456789"
                                        class="pi-field__input"
                                    >
                                    <button type="button" class="pi-field__copy" title="Copy" @click="copyText(directForm.tag_id)">
                                        <svg class="h-[13px] w-[13px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 8h10v12H8z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 16H4V4h12v2"/></svg>
                                    </button>
                                </div>
                            </label>
                            <button type="submit" class="pi-primary-btn pi-primary-btn--wide">Save &amp; Connect</button>
                        </form>
                    </article>

                    @if (! empty($enabledAdPlatforms['meta']))
                        <article class="pi-panel">
                            <div class="flex items-start justify-between gap-[8px]">
                                <div class="flex min-w-0 flex-1 gap-[16px]">
                                    <div class="w-[88px] shrink-0 text-center">
                                        <div class="mx-auto mb-[10px] flex h-[72px] w-[72px] items-center justify-center rounded-[8px] bg-white">
                                            <svg class="h-[40px] w-[40px] text-[#1877F2]" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2.04c-5.5 0-10 4.49-10 10.02 0 5 3.66 9.15 8.44 9.9v-7H7.9v-2.9h2.54V9.85c0-2.52 1.49-3.91 3.78-3.91 1.09 0 2.24.2 2.24.2v2.47h-1.26c-1.24 0-1.63.78-1.63 1.57v1.88h2.78l-.45 2.9h-2.33v7c4.78-.75 8.44-4.9 8.44-9.9 0-5.53-4.5-10.02-10-10.02z"/></svg>
                            </div>
                                        <p class="text-[15px] font-semibold leading-none text-white">Meta Ads</p>
                                        <span class="pi-status-pill is-off mt-[8px]">
                                            <span class="pi-status-dot"></span>
                                            <span>Not connected</span>
                                        </span>
                        </div>
                                    <div class="flex min-w-0 flex-1 flex-col justify-center gap-[8px]">
                                        <a href="{{ route('paid-marketing.dashboard') }}" class="pi-ghost-btn">
                                            @include('partials.sidebar-icon', ['name' => 'chart', 'class' => 'h-[14px] w-[14px] shrink-0'])
                                            <span>Paid Marketing</span>
                                        </a>
                                        <a href="{{ route('analytics.dashboard') }}" class="pi-ghost-btn">
                                            @include('partials.sidebar-icon', ['name' => 'shield-check', 'class' => 'h-[14px] w-[14px] shrink-0'])
                                            <span>Bot Protection</span>
                                        </a>
                                        <button type="button" class="pi-primary-btn" @click="showMenuToast('Meta Ads connect is coming soon.', 'info')">
                                            <span class="text-[14px] leading-none">+</span>
                                            Connect Meta
                                        </button>
                            </div>
                        </div>
                    </div>
                        </article>
                    @endif

                    @if (! empty($enabledAdPlatforms['microsoft']))
                        <article class="pi-panel">
                            <div class="flex items-start justify-between gap-[8px]">
                                <div class="flex min-w-0 flex-1 gap-[16px]">
                                    <div class="w-[88px] shrink-0 text-center">
                                        <div class="mx-auto mb-[10px] flex h-[72px] w-[72px] items-center justify-center rounded-[8px] bg-white">
                                            <svg class="h-[36px] w-[36px]" viewBox="0 0 24 24" aria-hidden="true">
                                                <path fill="#F25022" d="M3 3h8.5v8.5H3z"/>
                                                <path fill="#7FBA00" d="M12.5 3H21v8.5h-8.5z"/>
                                                <path fill="#00A4EF" d="M3 12.5H11.5V21H3z"/>
                                                <path fill="#FFB900" d="M12.5 12.5H21V21h-8.5z"/>
                                            </svg>
                        </div>
                                        <p class="text-[15px] font-semibold leading-none text-white">Microsoft Ads</p>
                                        <span class="pi-status-pill is-off mt-[8px]">
                                            <span class="pi-status-dot"></span>
                                            <span>Not connected</span>
                                        </span>
                        </div>
                                    <div class="flex min-w-0 flex-1 flex-col justify-center gap-[8px]">
                                        <a href="{{ route('paid-marketing.dashboard') }}" class="pi-ghost-btn">
                                            @include('partials.sidebar-icon', ['name' => 'chart', 'class' => 'h-[14px] w-[14px] shrink-0'])
                                            <span>Paid Marketing</span>
                                        </a>
                                        <a href="{{ route('analytics.dashboard') }}" class="pi-ghost-btn">
                                            @include('partials.sidebar-icon', ['name' => 'shield-check', 'class' => 'h-[14px] w-[14px] shrink-0'])
                                            <span>Bot Protection</span>
                                        </a>
                                        <button type="button" class="pi-primary-btn" @click="showMenuToast('Microsoft Ads connect is coming soon.', 'info')">
                                            <span class="text-[14px] leading-none">+</span>
                                            Connect Microsoft
                                        </button>
                        </div>
                        </div>
                        </div>
                        </article>
                        @endif
                    </div>
                </section>

            <div class="pi-side-stack">
                <section class="pi-side-card">
                    <div class="mb-[12px] flex items-center justify-between gap-[8px]">
                        <h2 class="text-[14px] font-semibold text-white">Connection Status</h2>
                        <a href="#connected-platforms" class="text-[11px] font-semibold text-[#B893D8] hover:text-white">View All</a>
                    </div>
                    <div class="space-y-[8px]">
                        <div class="pi-status-row">
                            <span class="flex h-[28px] w-[28px] items-center justify-center rounded-[6px] bg-white">
                                @include('partials.icons.google', ['class' => 'h-[16px] w-[16px]'])
                        </span>
                            <span class="min-w-0 flex-1 truncate text-[12px] text-white/90">Google Ads API</span>
                            <span class="pi-status-pill" :class="googleAdsApiHealthy ? 'is-on' : 'is-off'">
                                <span class="pi-status-dot"></span>
                                <span x-text="googleAdsApiHealthy ? 'Connected' : (googleOAuthConnected ? 'Pending' : 'Offline')"></span>
                        </span>
                        </div>
                        <div class="pi-status-row">
                            <span class="flex h-[28px] w-[28px] items-center justify-center rounded-[6px] bg-white p-[3px]">
                                <img src="{{ asset('images/google-tag-manager.svg') }}" alt="" class="h-[18px] w-[18px]">
                            </span>
                            <span class="min-w-0 flex-1 truncate text-[12px] text-white/90">Google Tag Manager</span>
                            <span class="pi-status-pill" :class="tagManagerConnected ? 'is-on' : 'is-off'">
                                <span class="pi-status-dot"></span>
                                <span x-text="tagManagerConnected ? 'Connected' : 'Offline'"></span>
                            </span>
                    </div>
                        </div>
                </section>

                <section class="pi-side-card">
                    <div class="mb-[12px] flex items-center justify-between gap-[8px]">
                        <h2 class="text-[14px] font-semibold text-white">Connection Health</h2>
                        <span class="pi-status-pill" :class="healthLive ? 'is-on' : 'is-off'">
                            <span class="pi-status-dot"></span>
                            <span x-text="healthLive ? 'Live' : 'Pending'"></span>
                        </span>
                    </div>
                    <div class="flex items-center gap-[14px]">
                        <div class="pi-health-ring shrink-0" :style="`--pi-health:${healthPct}`">
                            <div class="pi-health-ring__inner">
                                <span class="text-[13px] font-bold leading-none text-white" x-text="healthPct + '%'"></span>
                                <span class="mt-[2px] text-[8px] uppercase tracking-wide text-white/55">Healthy</span>
                            </div>
                        </div>
                        <div class="min-w-0 flex-1 space-y-[7px]">
                            <template x-for="item in healthItems" :key="item.key">
                                <div class="flex items-center gap-[8px] text-[11px]">
                                    <span class="min-w-0 flex-1 truncate text-white/80" x-text="item.label"></span>
                                    <span class="inline-flex items-center gap-[4px] shrink-0" :class="item.ok ? 'text-emerald-300' : 'text-amber-300'">
                                        <svg x-show="item.ok" class="h-[12px] w-[12px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M5 13l4 4L19 7"/></svg>
                                        <span x-text="item.stateLabel || (item.ok ? 'Healthy' : 'Pending')"></span>
                                    </span>
                                    <span class="w-[42px] shrink-0 text-right text-[10px] text-white/40" x-text="item.ago"></span>
                                </div>
                            </template>
                        </div>
                    </div>
                    @if ($primaryConnection)
                        <button type="button" class="pi-text-link mt-[12px]" @click="testGoogleHealth()">Test connection health →</button>
                    @endif
                </section>
            </div>
        </div>

        <style>
            .pi-first-row {
                display: grid;
                gap: 14px;
                align-items: stretch;
            }
            @media (min-width: 1100px) {
                .pi-first-row {
                    grid-template-columns: minmax(0, 1.55fr) minmax(280px, 0.85fr);
                }
            }
            .figma-filter-bar--pi {
                overflow: visible;
                width: fit-content;
                max-width: 100%;
            }
            .figma-filter-bar--pi > label {
                flex: 0 0 auto;
            }
            .figma-filter-bar--pi .figma-filter-calendar-host {
                flex: 0 0 auto;
            }
            @media (max-width: 900px) {
                .figma-filter-bar--pi {
                    width: 100%;
                    flex-wrap: wrap;
                }
            }
            .pi-connect-card,
            .pi-side-card {
                border-radius: 10px;
                border: 1px solid color-mix(in srgb, var(--brand-primary) 65%, transparent);
                background: #111111;
                padding: 16px 18px;
            }
            .pi-section-title {
                margin-bottom: 14px;
                font-size: 18px;
                font-weight: 600;
                color: #fff;
            }
            .pi-connect-grid {
                display: grid;
                gap: 14px;
            }
            @media (min-width: 720px) {
                .pi-connect-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            }
            @media (min-width: 1400px) {
                .pi-connect-grid { grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); }
            }
            .pi-panel {
                border-radius: 10px;
                border: 1px solid rgba(255, 255, 255, 0.18);
                background: var(--brand-primary);
                padding: 14px;
                min-height: 230px;
            }
            .pi-side-stack {
                display: grid;
                gap: 14px;
                align-content: start;
            }
            .pi-ghost-btn {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                height: 28px;
                max-width: 168px;
                width: 100%;
                border-radius: 6px;
                border: 1px solid rgba(255, 255, 255, 0.35);
                background: rgba(0, 0, 0, 0.12);
                padding: 0 10px;
                font-size: 11px;
                color: rgba(255, 255, 255, 0.95);
            }
            .pi-ghost-btn:hover { background: rgba(0, 0, 0, 0.22); }
            .pi-primary-btn {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
                height: 30px;
                max-width: 168px;
                width: 100%;
                border-radius: 6px;
                border: 0;
                background: #ffffff;
                padding: 0 10px;
                font-size: 11px;
                font-weight: 600;
                color: var(--brand-primary);
            }
            .pi-primary-btn:hover { background: var(--brand-tint-hover); color: var(--brand-secondary); }
            .pi-primary-btn--wide { max-width: none; height: 34px; margin-top: 4px; }
            .pi-text-link {
                display: inline-flex;
                font-size: 11px;
                font-weight: 600;
                color: #ffffff;
            }
            .pi-text-link:hover { color: rgba(255, 255, 255, 0.8); }
            .pi-status-pill {
                display: inline-flex;
                align-items: center;
                gap: 5px;
                border-radius: 999px;
                padding: 3px 8px;
                font-size: 10px;
                font-weight: 600;
                line-height: 1;
                white-space: nowrap;
            }
            .pi-status-pill.is-on { background: rgba(34, 197, 94, 0.22); color: #bbf7d0; }
            .pi-status-pill.is-off { background: rgba(0, 0, 0, 0.2); color: rgba(255, 255, 255, 0.7); }
            .pi-status-dot {
                width: 6px;
                height: 6px;
                border-radius: 999px;
                background: currentColor;
            }
            .pi-field {
                display: flex;
                align-items: center;
                gap: 6px;
                height: 34px;
                border-radius: 6px;
                border: 1px solid rgba(255, 255, 255, 0.28);
                background: rgba(0, 0, 0, 0.28);
                padding: 0 8px;
            }
            .pi-field__input {
                min-width: 0;
                flex: 1;
                border: 0;
                background: transparent;
                color: #fff;
                font-size: 12px;
                outline: none;
            }
            .pi-field__input::placeholder { color: rgba(255, 255, 255, 0.4); }
            .pi-field__copy {
                display: inline-flex;
                color: rgba(255, 255, 255, 0.65);
            }
            .pi-field__copy:hover { color: #fff; }
            .pi-status-row {
                display: flex;
                align-items: center;
                gap: 10px;
                border-radius: 8px;
                border: 1px solid rgba(255, 255, 255, 0.1);
                background: rgba(0, 0, 0, 0.28);
                padding: 10px 12px;
            }
            .pi-health-ring {
                --pi-health: 0;
                width: 92px;
                height: 92px;
                border-radius: 999px;
                background: conic-gradient(#22c55e calc(var(--pi-health) * 1%), color-mix(in srgb, var(--brand-primary) 55%, transparent) 0);
                display: grid;
                place-items: center;
            }
            .pi-health-ring__inner {
                width: 68px;
                height: 68px;
                border-radius: 999px;
                background: #111111;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
            }

            .pi-setup-card,
            .pi-platforms-card {
                margin-top: 20px;
                scroll-margin-top: 80px;
                border-radius: 10px;
                border: 1px solid var(--brand-secondary);
                background: #0d0d0d;
                padding: 18px 18px 16px;
            }
            .pi-platforms-card {
                background: #ffffff;
                border-color: color-mix(in srgb, var(--brand-primary) 35%, transparent);
            }
            .pi-setup-title {
                margin: 0 0 22px;
                font-size: 14px;
                font-weight: 600;
                color: #fff;
            }
            .pi-setup-track {
                display: grid;
                grid-template-columns: repeat(6, minmax(0, 1fr));
                gap: 8px;
                position: relative;
            }
            .pi-setup-track__fill {
                position: absolute;
                left: calc(100% / 12);
                top: 18px;
                height: 2px;
                width: calc((100% - (100% / 6)) * (var(--pi-setup-fill, 0) / 100));
                max-width: calc(100% - (100% / 6));
                background: var(--brand-primary);
                opacity: 0.95;
                z-index: 0;
                border-radius: 999px;
                transition: width 0.25s ease;
                pointer-events: none;
            }
            .pi-setup-track::before { display: none; }
            .pi-setup-step {
                position: relative;
                z-index: 1;
                display: flex;
                flex-direction: column;
                align-items: center;
                text-align: center;
                min-width: 0;
            }
            .pi-setup-icon {
                width: 36px;
                height: 36px;
                border-radius: 999px;
                display: grid;
                place-items: center;
                background: var(--brand-primary);
                color: #fff;
                box-shadow: 0 0 0 4px #0d0d0d;
            }
            .pi-setup-icon.is-pending {
                background: color-mix(in srgb, var(--brand-primary) 35%, transparent);
                color: rgba(255, 255, 255, 0.55);
            }
            .pi-setup-label {
                margin-top: 10px;
                font-size: 12px;
                font-weight: 600;
                color: #fff;
                line-height: 1.25;
            }
            .pi-setup-detail {
                margin-top: 4px;
                font-size: 11px;
                color: rgba(255, 255, 255, 0.45);
                max-width: 100%;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }
            .pi-platforms-head {
                display: flex;
                flex-wrap: wrap;
                align-items: flex-start;
                justify-content: space-between;
                gap: 14px;
                margin-bottom: 16px;
            }
            .pi-platforms-head h2 {
                margin: 0;
                font-size: 22px;
                font-weight: 600;
                color: #121212;
                line-height: 1.1;
            }
            .pi-platforms-head p {
                margin: 4px 0 0;
                font-size: 13px;
                color: rgba(18, 18, 18, 0.55);
            }
            .pi-platforms-tools {
                display: flex;
                flex-wrap: wrap;
                align-items: center;
                gap: 10px;
            }
            .pi-search {
                display: flex;
                align-items: center;
                gap: 8px;
                min-width: 200px;
                height: 36px;
                padding: 0 12px;
                border-radius: 8px;
                border: 1px solid rgba(18, 18, 18, 0.16);
                background: #f4f4f4;
                color: rgba(18, 18, 18, 0.45);
            }
            .pi-search input {
                width: 100%;
                border: 0;
                background: transparent;
                color: #121212;
                font-size: 12px;
                outline: none;
            }
            .pi-search input::placeholder { color: rgba(18, 18, 18, 0.4); }
            .pi-refresh-btn {
                display: inline-flex;
                align-items: center;
                gap: 7px;
                height: 36px;
                padding: 0 14px;
                border-radius: 8px;
                border: 1px solid rgba(18, 18, 18, 0.16);
                background: #ffffff;
                color: #121212;
                font-size: 12px;
                font-weight: 500;
            }
            .pi-refresh-btn:hover { background: #f4f4f4; }
            .pi-add-btn {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                height: 36px;
                padding: 0 14px;
                border-radius: 8px;
                border: 1px solid color-mix(in srgb, var(--brand-primary) 25%, transparent);
                background: #ffffff;
                color: var(--brand-primary);
                font-size: 12px;
                font-weight: 600;
            }
            .pi-add-btn:hover { background: #f8f0ff; }
            .pi-table-wrap {
                overflow-x: auto;
                border-radius: 8px;
                border: 1px solid rgba(18, 18, 18, 0.1);
                background: #ffffff;
            }
            .pi-table {
                width: 100%;
                min-width: 980px;
                border-collapse: collapse;
                text-align: left;
            }
            .pi-table thead th {
                padding: 12px 14px;
                font-size: 12px;
                font-weight: 600;
                color: #ffffff;
                border-bottom: 1px solid color-mix(in srgb, var(--brand-primary) 35%, transparent);
                white-space: nowrap;
                background: var(--brand-primary);
            }
            .pi-table tbody td {
                padding: 14px;
                font-size: 12px;
                color: #121212;
                border-bottom: 1px solid rgba(18, 18, 18, 0.06);
                vertical-align: middle;
                background: #ffffff;
            }
            .pi-table tbody tr:last-child td { border-bottom: 0; }
            .pi-table tbody tr:hover td { background: #f8f0ff; }
            .pi-plat-name {
                display: inline-flex;
                align-items: center;
                gap: 10px;
                font-size: 13px;
                font-weight: 600;
                color: #121212;
            }
            .pi-plat-logo {
                width: 28px;
                height: 28px;
                border-radius: 6px;
                background: #f4f4f4;
                display: grid;
                place-items: center;
                overflow: hidden;
                flex-shrink: 0;
            }
            .pi-acct-primary { font-size: 13px; font-weight: 500; color: #121212; }
            .pi-acct-secondary { margin-top: 2px; font-size: 11px; color: rgba(18, 18, 18, 0.5); }
            .pi-prot {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                color: rgba(18, 18, 18, 0.88);
                white-space: nowrap;
            }
            .pi-prot svg { flex-shrink: 0; }
            .pi-prot.is-audience svg { color: var(--brand-primary); }
            .pi-prot.is-track svg { color: #2563eb; }
            .pi-status-connected {
                display: inline-flex;
                align-items: center;
                gap: 7px;
                height: 26px;
                padding: 0 10px;
                border-radius: 999px;
                background: rgba(34, 197, 94, 0.12);
                color: #15803d;
                font-size: 12px;
                font-weight: 500;
                white-space: nowrap;
            }
            .pi-status-connected::before {
                content: '';
                width: 7px;
                height: 7px;
                border-radius: 999px;
                background: #22c55e;
            }
            .pi-status-pending {
                display: inline-flex;
                align-items: center;
                gap: 7px;
                height: 26px;
                padding: 0 10px;
                border-radius: 999px;
                background: rgba(18, 18, 18, 0.06);
                color: rgba(18, 18, 18, 0.55);
                font-size: 12px;
                white-space: nowrap;
            }
            .pi-status-pending::before {
                content: '';
                width: 7px;
                height: 7px;
                border-radius: 999px;
                background: rgba(18, 18, 18, 0.35);
            }
            .pi-row-actions {
                display: inline-flex;
                align-items: center;
                gap: 10px;
                white-space: nowrap;
            }
            .pi-row-link {
                color: var(--brand-primary);
                font-size: 12px;
                font-weight: 600;
            }
            .pi-row-link:hover { color: var(--brand-secondary); }
            .pi-platforms-card .integration-row-menu button,
            .pi-platforms-card .text-white\/45 {
                color: rgba(18, 18, 18, 0.45) !important;
            }
            .pi-platforms-card .hover\:text-white:hover {
                color: #121212 !important;
            }
            .pi-table-foot {
                display: flex;
                flex-wrap: wrap;
                align-items: center;
                justify-content: space-between;
                gap: 10px;
                margin-top: 14px;
                padding-top: 4px;
            }
            .pi-table-foot__meta {
                font-size: 12px;
                color: rgba(18, 18, 18, 0.5);
            }
            .pi-pager {
                display: inline-flex;
                align-items: center;
                gap: 6px;
            }
            .pi-pager button {
                width: 28px;
                height: 28px;
                border-radius: 6px;
                border: 1px solid rgba(18, 18, 18, 0.14);
                background: #ffffff;
                color: rgba(18, 18, 18, 0.55);
                display: grid;
                place-items: center;
            }
            .pi-pager button.is-active {
                border-color: transparent;
                background: var(--brand-primary);
                color: #fff;
            }
            .pi-pager button:disabled { opacity: 0.35; cursor: not-allowed; }
            .pi-platforms-card td.text-white\/45,
            .pi-platforms-card .text-\[13px\].text-white\/45 {
                color: rgba(18, 18, 18, 0.45) !important;
            }
            .pi-scroll-box {
                max-height: 260px;
                overflow-y: auto;
                overflow-x: hidden;
                padding-right: 4px;
                overscroll-behavior: contain;
            }
            .pi-scroll-box::-webkit-scrollbar {
                width: 6px;
            }
            .pi-scroll-box::-webkit-scrollbar-track {
                background: transparent;
            }
            .pi-scroll-box::-webkit-scrollbar-thumb {
                background: color-mix(in srgb, var(--brand-primary) 55%, transparent);
                border-radius: 999px;
            }
            .pi-scroll-box::-webkit-scrollbar-thumb:hover {
                background: color-mix(in srgb, var(--brand-primary) 80%, transparent);
            }
            @media (max-width: 900px) {
                .pi-setup-track {
                    grid-template-columns: repeat(3, minmax(0, 1fr));
                    row-gap: 18px;
                }
                .pi-setup-track::before,
                .pi-setup-track__fill { display: none; }
            }
            @media (max-width: 640px) {
                .pi-setup-track { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            }

            html.light-mode .pi-connect-card,
            html.light-mode .pi-side-card {
                background: #ffffff;
                border-color: var(--brand-tint-border);
                box-shadow: 0 1px 10px color-mix(in srgb, var(--brand-primary) 8%, transparent);
            }
            html.light-mode .pi-section-title {
                color: #2d2d3a;
            }
            html.light-mode .pi-setup-card {
                background: #ffffff;
                border-color: var(--brand-tint-border);
                box-shadow: 0 1px 10px color-mix(in srgb, var(--brand-primary) 8%, transparent);
            }
            html.light-mode .pi-setup-title,
            html.light-mode .pi-setup-label {
                color: #2d2d3a;
            }
            html.light-mode .pi-setup-detail {
                color: #6b6578;
            }
            html.light-mode .pi-setup-icon {
                box-shadow: 0 0 0 4px #ffffff;
            }
            html.light-mode .pi-health-ring__inner {
                background: #ffffff;
            }
            html.light-mode .pi-health-ring__inner [class*="text-white"] {
                color: #2d2d3a !important;
            }
            html.light-mode .pi-status-row {
                background: var(--brand-tint-soft);
                border-color: var(--brand-tint-border);
            }
            html.light-mode .pi-side-card [class*="text-white"] {
                color: #2d2d3a !important;
            }
            html.light-mode .pi-side-card .text-white\/80 {
                color: #4a4458 !important;
            }
            html.light-mode .pi-side-card .text-white\/40,
            html.light-mode .pi-side-card .text-white\/55,
            html.light-mode .pi-side-card .text-white\/65,
            html.light-mode .pi-side-card .text-white\/70 {
                color: #6b6578 !important;
            }
            html.light-mode .pi-side-card a[class*="text-[#B893D8]"] {
                color: var(--brand-primary) !important;
            }
            html.light-mode .pi-side-card a[class*="text-[#B893D8]"]:hover {
                color: var(--brand-secondary) !important;
            }
            html.light-mode .pi-status-pill.is-off {
                background: #f3f4f6;
                color: #6b7280;
            }
            html.light-mode .pi-platforms-card details[class*="bg-black"] {
                background: var(--brand-tint-soft) !important;
                border-color: var(--brand-tint-border) !important;
            }
            html.light-mode .pi-platforms-card details summary[class*="text-white"] {
                color: #2d2d3a !important;
            }
            html.light-mode .pi-platforms-card details [class*="text-white"] {
                color: #5c5470 !important;
            }
            html.light-mode .pi-platforms-card details .text-white\/90 {
                color: #2d2d3a !important;
            }
            html.light-mode .pi-platforms-card details [class*="text-[#B893D8]"] {
                color: var(--brand-primary) !important;
            }
            html.light-mode .pi-add-btn:hover {
                background: var(--brand-tint-hover);
            }
            html.light-mode .pi-table tbody tr:hover td {
                background: var(--brand-tint-hover);
            }
            html.light-mode .pi-footer-card {
                background: #ffffff !important;
                border-color: var(--brand-tint-border) !important;
                box-shadow: 0 1px 10px color-mix(in srgb, var(--brand-primary) 8%, transparent);
            }
            html.light-mode .pi-footer-card h2,
            html.light-mode .pi-footer-card [class*="text-white"] {
                color: #2d2d3a !important;
            }
            html.light-mode .pi-footer-card .text-white\/60,
            html.light-mode .pi-footer-card .text-white\/55,
            html.light-mode .pi-footer-card .text-white\/65,
            html.light-mode .pi-footer-card .text-white\/45,
            html.light-mode .pi-footer-card .text-white\/40 {
                color: #6b6578 !important;
            }
            html.light-mode .pi-footer-card .border-white\/15,
            html.light-mode .pi-footer-card .border-white\/10,
            html.light-mode .pi-footer-card .border-white\/25 {
                border-color: var(--brand-tint-border) !important;
            }
            html.light-mode .pi-footer-card [class*="bg-[var(--brand-primary)]"] {
                background: var(--brand-tint-selected) !important;
            }
            html.light-mode .pi-footer-card .bg-white\/5 {
                background: var(--brand-tint-soft) !important;
            }
            html.light-mode .pi-footer-card code.bg-black\/40 {
                background: #ffffff !important;
                color: var(--brand-secondary) !important;
                border: 1px solid var(--brand-tint-border);
            }
            html.light-mode .pi-footer-card .bg-emerald-500\/20 {
                background: rgba(34, 197, 94, 0.14) !important;
            }
            html.light-mode .pi-footer-card .text-emerald-200 {
                color: #15803d !important;
            }
            html.light-mode .pi-footer-card .bg-rose-500\/20 {
                background: rgba(244, 63, 94, 0.12) !important;
            }
            html.light-mode .pi-footer-card .text-rose-200 {
                color: #be123c !important;
            }
        </style>

        {{-- Setup Progress --}}
        <section class="pi-setup-card">
            <h2 class="pi-setup-title">Setup Progress</h2>
            <div class="pi-setup-track" :style="`--pi-setup-fill: ${setupProgressFill}`">
                <div class="pi-setup-track__fill" aria-hidden="true"></div>
                <template x-for="step in activeSetupProgress" :key="step.key">
                    <div class="pi-setup-step">
                        <div class="pi-setup-icon" :class="step.done ? '' : 'is-pending'">
                            <template x-if="step.key === 'domain' && step.done">
                                <svg class="h-[16px] w-[16px]" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 21a9 9 0 100-18 9 9 0 000 18z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.6 9h16.8M3.6 15h16.8M12 3c2.5 2.6 3.8 5.7 3.8 9s-1.3 6.4-3.8 9c-2.5-2.6-3.8-5.7-3.8-9S9.5 5.6 12 3z"/>
                                </svg>
                            </template>
                            <template x-if="step.key === 'domain' && !step.done">
                                <svg class="h-[16px] w-[16px] opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 21a9 9 0 100-18 9 9 0 000 18z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.6 9h16.8M3.6 15h16.8M12 3c2.5 2.6 3.8 5.7 3.8 9s-1.3 6.4-3.8 9c-2.5-2.6-3.8-5.7-3.8-9S9.5 5.6 12 3z"/>
                                </svg>
                            </template>
                            <template x-if="step.key !== 'domain' && step.done">
                                <svg class="h-[16px] w-[16px]" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                </svg>
                            </template>
                            <template x-if="step.key !== 'domain' && !step.done">
                                <span class="h-[8px] w-[8px] rounded-full bg-white/40"></span>
                            </template>
            </div>
                        <div class="pi-setup-label" x-text="step.label"></div>
                        <div class="pi-setup-detail" :title="step.detail || ''" x-text="step.detail || '—'"></div>
                    </div>
                </template>
            </div>
        </section>

        {{-- Connected Platforms --}}
        <section id="connected-platforms" class="pi-platforms-card">
            <details class="mb-[14px] rounded-[10px] border border-white/10 bg-black/20 px-[14px] py-[12px]">
                <summary class="cursor-pointer text-[12px] font-semibold text-white">Pixel Guard &amp; Audience Exclusion guidance</summary>
                <div class="mt-[10px] grid gap-[10px] text-[11px] leading-relaxed text-white/70 sm:grid-cols-2">
            <div>
                        <p class="font-semibold text-[#B893D8]">Pixel Guard</p>
                        <ul class="mt-[6px] list-disc space-y-1 pl-[16px]">
                            <li><strong class="text-white/90">Google Tag ID</strong> — the AW-/GT- ID from Google Ads / Tag Manager for this domain.</li>
                            <li>Tag must match the hostname on the mapping; mismatch fails Save validation.</li>
                            <li>Prerequisite: tracking tag installed + Google Ads OAuth connected for Pixel Guard protection type.</li>
                        </ul>
                    </div>
                    <div>
                        <p class="font-semibold text-[#B893D8]">Audience Exclusion</p>
                        <ul class="mt-[6px] list-disc space-y-1 pl-[16px]">
                            <li>Create Google Ads conversion <strong class="text-white/90">promo for ppc - invalid Users</strong>, then paste Conversion ID + Label.</li>
                            <li>Match each row to the domain tag (Installed / Not detected).</li>
                            <li>Use <strong class="text-white/90">Connect Additional audience</strong> for more conversion mappings. Save validates required fields.</li>
                            <li>Open from Google platform menu → Set Up Audience Exclusion. IP push rules remain in Detection Panel.</li>
                        </ul>
                    </div>
                </div>
            </details>
            <div class="pi-platforms-head">
                <div>
                    <h2>Connected Platforms</h2>
                    <p>Linked accounts &amp; domains</p>
                </div>
                <div class="pi-platforms-tools">
                    <label class="pi-search">
                        <svg class="h-[14px] w-[14px] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M11 18a7 7 0 100-14 7 7 0 000 14z"/>
                        </svg>
                        <input type="search" placeholder="Search platform..." x-model="platformSearch" @input="platformPage = 1">
                    </label>
                    <button type="button" class="pi-refresh-btn" @click="refreshPlatforms()">
                        <svg class="h-[14px] w-[14px]" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v6h6M20 20v-6h-6M5 13a7 7 0 0112.2-4.5L20 11M4 13l2.8 2.5A7 7 0 0019 13"/>
                        </svg>
                        Refresh
                    </button>
                    <a href="{{ route('integrations.google.redirect') }}" class="pi-add-btn">+ Add Connection</a>
                </div>
            </div>

            <div class="pi-table-wrap">
                <table class="pi-table">
                    <thead>
                        <tr>
                            <th>Platform</th>
                            <th>Account / Domain</th>
                            <th>Protection Type</th>
                            <th>Connected Entity ID</th>
                            <th>Status</th>
                            <th>Last Sync</th>
                            <th>Tracked Clicks</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse (($platformRows ?? collect()) as $row)
                            <tr x-show="platformRowVisible(@js($row['search'] ?? ''))">
                                <td>
                                    <span class="pi-plat-name">
                                        <span class="pi-plat-logo">
                                            @if (($row['kind'] ?? '') === 'gtm')
                                                <img src="{{ url('/images/google-tag-manager.svg') }}" alt="" width="18" height="18">
                                            @elseif (($row['kind'] ?? '') === 'direct')
                                                <svg class="h-[14px] w-[14px] text-[var(--brand-primary)]" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2l3 7h7l-5.5 4.5L18 21l-6-4-6 4 1.5-7.5L2 9h7z"/></svg>
                                            @else
                                                @include('partials.icons.google', ['class' => 'h-[16px] w-[16px]'])
                                            @endif
                                        </span>
                                        <span>{{ $row['platform'] }}</span>
                                    </span>
                                </td>
                                <td>
                                    <div class="pi-acct-primary truncate" title="{{ $row['account_primary'] }}">{{ $row['account_primary'] }}</div>
                                    <div class="pi-acct-secondary truncate" title="{{ $row['account_secondary'] }}">{{ $row['account_secondary'] }}</div>
                                </td>
                                <td>
                                    <span class="pi-prot {{ ($row['protection_tone'] ?? '') === 'track' ? 'is-track' : 'is-audience' }}">
                                        <svg class="h-[14px] w-[14px]" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2l8 3v6c0 5-3.4 9.4-8 11-4.6-1.6-8-6-8-11V5l8-3z"/></svg>
                                        <span>{{ $row['protection'] }}</span>
                                        </span>
                                </td>
                                <td class="font-mono text-[12px]">{{ $row['entity_id'] }}</td>
                                <td>
                                    <span class="{{ ($row['status'] ?? '') === 'Connected' ? 'pi-status-connected' : 'pi-status-pending' }}">{{ $row['status'] }}</span>
                                </td>
                                <td>
                                    @if (! empty($row['last_sync_at']))
                                        <span x-text="relativeAgo(@js($row['last_sync_at']))">{{ $row['last_sync'] }}</span>
                                    @else
                                        {{ $row['last_sync'] }}
                                    @endif
                                </td>
                                <td>{{ $row['clicks_label'] }}</td>
                                <td>
                                    <div class="pi-row-actions">
                                        <a href="{{ $row['action_url'] }}" class="pi-row-link">{{ $row['action_label'] }}</a>
                                        @if (! empty($row['delete_url']) || ! empty($row['edit_url']))
                                            <div class="integration-row-menu inline-flex">
                                                <x-integrations.platform-card-dropdown :menu-id="$row['menu_id']" label="Platform row options">
                                                    @if (! empty($row['edit_url']))
                                                        <a href="{{ $row['edit_url'] }}" class="figma-platform-menu-item">
                                                            {{ $row['edit_label'] ?? 'Edit Connection' }}
                                                        </a>
                                                    @endif
                                                    @if (! empty($row['delete_url']))
                                                        <form method="POST" action="{{ $row['delete_url'] }}" onsubmit="return confirm('Remove this platform link?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="figma-platform-menu-item figma-platform-menu-item--danger w-full text-left">
                                                    @include('partials.sidebar-icon', ['name' => 'trash', 'class' => 'mr-[8px] inline h-[14px] w-[14px]'])
                                                    Delete
                                                </button>
                                            </form>
                                                    @endif
                                        </x-integrations.platform-card-dropdown>
                                            </div>
                                        @else
                                            <button type="button" class="rounded p-[4px] text-black/40 hover:text-black" title="More" aria-label="More options">
                                                <svg class="h-[16px] w-[16px]" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="5" r="1.6"/><circle cx="12" cy="19" r="1.6"/><circle cx="12" cy="12" r="1.6"/></svg>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="!py-[28px] text-center text-[13px] text-black/45">No connected platforms yet. Add a connection to get started.</td>
                            </tr>
                        @endforelse
                        <tr x-show="filteredPlatformRows.length === 0 && platformRows.length > 0" x-cloak>
                            <td colspan="8" class="!py-[28px] text-center text-[13px] text-black/45">No platforms match your search.</td>
                            </tr>
                    </tbody>
                </table>
            </div>

            <div class="pi-table-foot" x-show="filteredPlatformRows.length > 0" x-cloak>
                <div class="pi-table-foot__meta" x-text="platformRangeLabel"></div>
                <div class="pi-pager">
                    <button type="button" @click="platformPage = Math.max(1, platformPage - 1)" :disabled="platformPage <= 1" aria-label="Previous page">
                        <svg class="h-[12px] w-[12px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    </button>
                    <button type="button" class="is-active" aria-current="page">1</button>
                    <button type="button" @click="platformPage = Math.min(platformPageCount, platformPage + 1)" :disabled="platformPage >= platformPageCount" aria-label="Next page">
                        <svg class="h-[12px] w-[12px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </button>
                </div>
            </div>
        </section>

        <div class="mt-[16px] grid gap-[12px] xl:grid-cols-2 xl:items-stretch">
            <section class="pi-footer-card flex min-h-0 flex-col rounded-[10px] border border-[var(--brand-secondary)] bg-[#121212] p-[16px]">
                <div class="mb-[12px] flex shrink-0 items-center justify-between gap-3">
                    <div>
                        <h2 class="text-[18px] font-medium text-white">Tracking ID management</h2>
                        <p class="mt-[3px] text-[12px] text-white/60">Google Ads tag IDs linked to your domains (selected accounts only)</p>
                    </div>
                </div>
                <div class="pi-scroll-box space-y-[8px]">
                    <template x-for="row in filteredTrackingIds" :key="row.id">
                        <div class="flex flex-wrap items-center justify-between gap-[8px] rounded-[8px] border border-white/15 bg-[var(--brand-primary)]/25 px-[12px] py-[10px]">
                            <div class="min-w-0">
                                <p class="truncate text-[13px] font-medium text-white" x-text="row.label"></p>
                                <p class="truncate text-[11px] text-white/55">
                                    <span x-text="'Domain: ' + (row.domain || '—')"></span>
                                    <span x-text="' · Customer: ' + (row.customer_id || '—')"></span>
                                </p>
                            </div>
                            <div class="flex items-center gap-[8px]">
                                <code class="rounded bg-black/40 px-[8px] py-[4px] font-mono text-[11px] text-white/90" x-text="row.google_tag_id || '—'"></code>
                                <button
                                    type="button"
                                    class="rounded border border-white/25 bg-white px-[8px] py-[4px] text-[10px] font-semibold text-[var(--brand-primary)] hover:bg-white/90"
                                    x-show="row.google_tag_id"
                                    @click="copyKeyText(row.google_tag_id)"
                                >Copy</button>
                            </div>
                        </div>
                    </template>
                    <p x-show="filteredTrackingIds.length === 0" class="rounded-[8px] border border-white/10 bg-white/5 px-[12px] py-[14px] text-center text-[12px] text-white/55">
                        No linked Ads account for this domain yet. Connect Paid Advertising and pick the account for the domain.
                    </p>
                </div>
            </section>

            <section class="pi-footer-card flex min-h-0 flex-col rounded-[10px] border border-[var(--brand-secondary)] bg-[#121212] p-[16px]">
                <div class="mb-[12px] flex shrink-0 items-center justify-between gap-3">
                    <div>
                        <h2 class="text-[18px] font-medium text-white">Sync history &amp; logs</h2>
                        <p class="mt-[3px] text-[12px] text-white/60">OAuth, account sync, domain link, and health checks</p>
                    </div>
                    <button type="button" class="rounded border border-white/25 bg-white px-[8px] py-[4px] text-[10px] font-semibold text-[var(--brand-primary)] hover:bg-white/90" @click="refreshSyncLogs()">Refresh</button>
                </div>
                <div class="pi-scroll-box space-y-[6px]">
                    <template x-for="log in syncLogs" :key="log.id">
                        <article class="rounded-[8px] border border-white/10 bg-white/5 px-[10px] py-[8px]">
                            <div class="flex flex-wrap items-center justify-between gap-[6px]">
                                <span class="rounded px-[6px] py-[1px] text-[9px] font-semibold uppercase"
                                      :class="log.status === 'ok' ? 'bg-emerald-500/20 text-emerald-200' : 'bg-rose-500/20 text-rose-200'"
                                      x-text="log.status"></span>
                                <span class="text-[10px] text-white/45" x-text="formatHealthTime(log.created_at)"></span>
                            </div>
                            <p class="mt-[4px] text-[11px] font-medium text-white" x-text="log.action.replaceAll('_', ' ')"></p>
                            <p class="mt-[2px] text-[10px] text-white/65" x-text="log.message || '—'"></p>
                            <p class="mt-[2px] text-[10px] text-white/40" x-show="log.domain" x-text="'Domain: ' + log.domain"></p>
                        </article>
                    </template>
                    <p x-show="syncLogs.length === 0" class="rounded-[8px] border border-white/10 bg-white/5 px-[12px] py-[14px] text-center text-[12px] text-white/55">No sync events yet. Connect Google or run Sync Ads.</p>
                </div>
            </section>
        </div>
    </section>

    @if ($primaryConnection)
        <form id="google-disconnect-form" method="POST" action="{{ route('integrations.google.disconnect', $primaryConnection) }}" class="hidden">
            @csrf
            @method('DELETE')
        </form>
    @endif

    {{-- Domain keys modal (Tag Manager + Bot Protection) --}}
    <div class="fixed inset-0 z-[90] flex items-center justify-center bg-black/70 p-[16px]" x-show="keysModal.open" x-cloak x-transition @click.self="closeKeysModal()" @keydown.escape.window="closeKeysModal()">
        <div class="w-full max-w-[560px] overflow-hidden rounded-[12px] bg-[var(--brand-primary)] text-white shadow-2xl" @click.stop>
            <header class="flex items-center justify-between border-b border-white/25 px-[24px] py-[18px]">
                <h2 class="text-[18px] font-semibold">Finish Setup <span class="text-[13px] font-normal text-white/80">(Required For WordPress Domains)</span></h2>
                <button type="button" @click="copyAllKeys()" class="flex items-center gap-[6px] text-[12px] text-white/90 hover:text-white">
                    <svg class="h-[14px] w-[14px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="1.8" d="M8 8h8v8H8zM4 4h8v2H6v6H4z"/></svg>
                    Copy all
                </button>
            </header>
            <div class="space-y-[14px] px-[24px] py-[20px]">
                <p class="text-[12px] text-white/85" x-text="'Installation Keys for (' + (keysModal.hostname || 'domain') + ')'"></p>
                <template x-for="row in keysModal.rows" :key="row.label">
                    <div class="flex flex-col gap-[6px] sm:flex-row sm:items-center sm:gap-[12px]">
                        <span class="w-[130px] shrink-0 text-[12px] font-medium" x-text="row.label"></span>
                        <div class="min-w-0 flex-1 rounded-[4px] border border-dashed border-white/70 bg-[color-mix(in_srgb,var(--brand-secondary)_50%,transparent)] px-[12px] py-[8px] font-mono text-[11px] break-all" x-text="row.value || '…'"></div>
                        <button type="button" @click="copyKeyText(row.value)" class="flex shrink-0 items-center gap-[4px] text-[11px] text-white/90 hover:text-white">
                            <svg class="h-[13px] w-[13px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="1.8" d="M8 8h8v8H8zM4 4h8v2H6v6H4z"/></svg>
                            Copy
                        </button>
                    </div>
                </template>
                <div class="flex flex-wrap gap-[10px] pt-[4px]">
                    <a :href="keysModal.setupUrl" class="inline-block text-[12px] text-white underline">Full tracking setup →</a>
                    <a :href="keysModal.wpAdminUrl" target="_blank" rel="noopener noreferrer" class="inline-block text-[12px] text-white/90 underline">Open WordPress</a>
                    <a :href="keysModal.wpPluginSettingsUrl" target="_blank" rel="noopener noreferrer" class="inline-block text-[12px] text-white/90 underline">Promotix plugin settings</a>
                </div>
            </div>
            <footer class="flex flex-wrap justify-end gap-[10px] border-t border-white/25 px-[24px] py-[14px]">
                <button type="button" @click="verifyKeysInstallation()" class="rounded-[6px] border border-white px-[16px] py-[8px] text-[13px] text-white">Verify installation</button>
                <button type="button" @click="closeKeysModal()" class="rounded-[6px] bg-white px-[22px] py-[8px] text-[13px] font-semibold text-[var(--brand-primary)]">Done</button>
            </footer>
        </div>
    </div>

    {{-- Set Up Audience Exclusion --}}
    <div class="fixed inset-0 z-[95] flex items-center justify-center bg-black/70 p-[16px]" x-show="audienceModal.open" x-cloak x-transition @click.self="closeAudienceModal()" @keydown.escape.window="closeAudienceModal()">
        <div class="w-full max-w-[760px] overflow-hidden rounded-[12px] bg-[var(--brand-primary)] text-white shadow-2xl" @click.stop>
            <header class="border-b border-white/25 px-[24px] py-[18px]">
                <h2 class="text-[18px] font-semibold">Set Up Audience Exclusion</h2>
            </header>
            <div class="max-h-[min(70vh,560px)] space-y-[14px] overflow-y-auto px-[24px] py-[18px]">
                <ol class="list-decimal space-y-[8px] pl-[18px] text-[12px] leading-relaxed text-white/90">
                    <li>Create a conversion on google ads named ‘promo for ppc - invalid Users’ and follow the guidelines</li>
                    <li>Paste the Conversion ID and Conversion label below, and match it to the relevant domain.</li>
                    <li>After the set up is completed on Clickpromo, create an audience on the Google Ads platform using the following guidelines</li>
                </ol>

                <p class="text-[11px] text-white/70" x-show="audienceModal.error" x-text="audienceModal.error" x-cloak></p>

                <div class="space-y-[10px]">
                    <template x-for="(row, idx) in audienceModal.rows" :key="idx">
                        <div class="grid grid-cols-1 gap-[10px] rounded-[8px] border border-white/20 bg-[color-mix(in_srgb,var(--brand-secondary)_35%,transparent)] p-[12px] sm:grid-cols-[1fr_1fr_1fr_auto]">
                            <label class="block">
                                <span class="mb-[4px] block text-[11px] font-semibold text-white">Conversion ID</span>
                                <input type="text" x-model="row.conversion_id" placeholder="AW-17783207578" class="w-full rounded-[6px] border border-white/25 bg-[#2a0050] px-[10px] py-[8px] text-[12px] text-white placeholder:text-white/40 focus:outline-none focus:ring-1 focus:ring-white/40">
                            </label>
                            <label class="block">
                                <span class="mb-[4px] block text-[11px] font-semibold text-white">Conversion Label</span>
                                <input type="text" x-model="row.conversion_label" placeholder="getpropanereill.online" class="w-full rounded-[6px] border border-white/25 bg-[#2a0050] px-[10px] py-[8px] text-[12px] text-white placeholder:text-white/40 focus:outline-none focus:ring-1 focus:ring-white/40">
                            </label>
                            <label class="block">
                                <span class="mb-[4px] block text-[11px] font-semibold text-white">Tag</span>
                                <select
                                    class="w-full rounded-[6px] border border-white/25 bg-[#2a0050] px-[10px] py-[8px] text-[12px] text-white focus:outline-none focus:ring-1 focus:ring-white/40"
                                    :value="row.domain_id || ''"
                                    @change="onAudienceTagChange(idx, $event.target.value)"
                                >
                                    <option value="">Select tag</option>
                                    <template x-for="tag in audienceModal.tags" :key="tag.id">
                                        <option :value="tag.id" x-text="tag.label"></option>
                                    </template>
                                </select>
                            </label>
                            <div class="flex items-end justify-end pb-[2px]">
                                <button type="button" class="rounded-[6px] border border-white/30 p-[8px] text-white/85 hover:bg-white/10" @click="removeAudienceRow(idx)" title="Remove row" :disabled="audienceModal.rows.length <= 1">
                                    <svg class="h-[16px] w-[16px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3m5 0H4"/></svg>
                                </button>
                            </div>
                        </div>
                    </template>
                </div>

                <button type="button" class="inline-flex items-center gap-[6px] text-[12px] font-semibold text-white hover:underline" @click="addAudienceRow()">
                    <span class="inline-flex h-[18px] w-[18px] items-center justify-center rounded-full border border-white/60 text-[12px]">+</span>
                    Connect Additional audience
                </button>
            </div>
            <footer class="flex flex-wrap justify-end gap-[10px] border-t border-white/25 px-[24px] py-[14px]">
                <button type="button" @click="closeAudienceModal()" class="rounded-[6px] border border-white px-[18px] py-[8px] text-[13px] text-white">Cancel</button>
                <button type="button" @click="saveAudienceExclusion()" :disabled="audienceModal.saving" class="rounded-[6px] bg-white px-[22px] py-[8px] text-[13px] font-semibold text-[var(--brand-primary)] disabled:opacity-50">
                    <span x-text="audienceModal.saving ? 'Saving…' : 'Save'"></span>
                </button>
            </footer>
        </div>
    </div>

    <div
        x-show="menuToast"
        x-cloak
        x-transition
        class="fixed top-[70px] right-[24px] z-[250] max-w-[min(360px,calc(100vw-48px))] rounded-[8px] px-[14px] py-[10px] text-[12px] shadow-lg backdrop-blur-sm"
        :class="{
            'border border-red-400/45 bg-red-500/20 text-red-50': menuToastType === 'error',
            'border border-emerald-400/35 bg-emerald-500/15 text-emerald-50': menuToastType === 'success',
            'border border-[var(--brand-primary)]/40 bg-[var(--brand-primary)]/30 text-white': menuToastType === 'info',
        }"
        x-text="menuToast"
    ></div>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.store('platformCardMenu', { open: null });
});

function platformCardDropdown(menuId, align = 'right') {
    return {
        menuId,
        align,
        menuStyle: '',
        _docHandler: null,
        get isOpen() {
            return Alpine.store('platformCardMenu').open === this.menuId;
        },
        toggle() {
            const store = Alpine.store('platformCardMenu');
            store.open = store.open === this.menuId ? null : this.menuId;
            if (store.open === this.menuId) {
                this.$nextTick(() => this.positionMenu());
            }
        },
        close() {
            if (Alpine.store('platformCardMenu').open === this.menuId) {
                Alpine.store('platformCardMenu').open = null;
            }
        },
        init() {
            this._docHandler = (e) => {
                if (!this.isOpen) return;
                if (this.$refs.trigger?.contains(e.target)) return;
                if (this.$refs.panel?.contains(e.target)) return;
                this.close();
            };
            document.addEventListener('click', this._docHandler, true);
        },
        destroy() {
            if (this._docHandler) {
                document.removeEventListener('click', this._docHandler, true);
            }
        },
        onMenuClick(e) {
            const action = e.target.closest('a[href], button[type="button"]');
            if (action) {
                this.close();
            }
        },
        positionMenu() {
            const btn = this.$refs.trigger?.querySelector('button');
            if (!btn) return;
            const r = btn.getBoundingClientRect();
            const top = Math.round(r.bottom + 6);
            if (this.align === 'right') {
                const left = Math.round(r.right);
                this.menuStyle = `top:${top}px;left:${left}px;transform:translateX(-100%);`;
            } else {
                this.menuStyle = `top:${top}px;left:${Math.round(r.left)}px;`;
            }
        },
    };
}

function platformIntegrations(config) {
    return {
        directList: config.directInitial || [],
        domainConnections: config.domainConnections || [],
        connectionHealth: config.connectionHealth || {},
        tagReady: Boolean(config.tagReady),
        botReady: Boolean(config.botReady),
        googleOAuthConnected: Boolean(config.googleOAuthConnected),
        syncLogs: config.syncLogs || [],
        platformRows: config.platformRows || [],
        trackingIds: config.trackingIds || [],
        setupProgressAll: config.setupProgress || [],
        setupProgressByDomain: config.setupProgressByDomain || {},
        platformSearch: '',
        platformPage: 1,
        platformPerPage: 8,
        selectedDomainId: '',
        selectedAdsAccountId: '',
        selectedLandingPage: '',
        keysModal: {
            open: false,
            id: null,
            hostname: '',
            rows: [],
            setupUrl: '#',
            wpAdminUrl: '#',
            wpPluginSettingsUrl: '#',
        },
        audienceModal: {
            open: false,
            loading: false,
            saving: false,
            error: '',
            mapping_id: null,
            rows: [{ conversion_id: '', conversion_label: '', tag: '', domain_id: null }],
            tags: [],
        },
        audienceGetUrl: '/integrations/google/audience-exclusion',
        audienceSaveUrl: '/integrations/google/audience-exclusion',
        csrf: config.csrf || '',
        directForm: {
            platform: 'custom',
            account_label: 'Direct Ads',
            account_id: (config.directInitial && config.directInitial[0] && config.directInitial[0].account_id) || '',
            tag_id: (config.directInitial && config.directInitial[0] && config.directInitial[0].tag_id) || '',
        },
        menuToast: '',
        menuToastType: 'info',
        menuToastTimer: null,
        copyText(value) {
            const text = String(value || '').trim();
            if (!text) {
                this.showMenuToast('Nothing to copy.', 'error');
                return;
            }
            navigator.clipboard?.writeText(text)
                .then(() => this.showMenuToast('Copied.', 'success'))
                .catch(() => this.showMenuToast('Copy failed.', 'error'));
        },
        relativeAgo(value) {
            if (!value) return '—';
            const d = new Date(value);
            if (Number.isNaN(d.getTime())) return '—';
            const sec = Math.max(0, Math.round((Date.now() - d.getTime()) / 1000));
            if (sec < 60) return `${sec}s ago`;
            const min = Math.round(sec / 60);
            if (min < 60) return `${min}m ago`;
            const hr = Math.round(min / 60);
            if (hr < 48) return `${hr}h ago`;
            return this.formatHealthTime(value);
        },
        formatHealthTime(value) {
            if (!value) return '—';
            const d = new Date(value);
            if (Number.isNaN(d.getTime())) return '—';
            return d.toLocaleString(undefined, { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
        },
        get tagManagerConnected() {
            if (this.activeDomainStatus) {
                return Boolean(this.activeDomainStatus.tag_connected || this.activeDomainStatus.steps?.find((s) => s.label === 'Tag Manager')?.done);
            }
            return Boolean(this.tagReady);
        },
        get trackingScriptOk() {
            // Tag script only — not Google Ads OAuth/API.
            if (this.activeDomainStatus) {
                return Boolean(this.activeDomainStatus.tag_connected || this.activeDomainStatus.steps?.find((s) => s.label === 'Tag Manager')?.done);
            }
            return Boolean(this.tagReady);
        },
        get healthItems() {
            const syncAgo = this.relativeAgo(this.connectionHealth.last_sync_at);
            const eventAgo = this.relativeAgo(this.connectionHealth.last_event_at);
            // Tag/GTM ≠ Google Ads API. API is healthy only after OAuth + non-error health.
            const apiStatus = String(this.connectionHealth.health_status || '').toLowerCase();
            const apiLinked = Boolean(this.googleOAuthConnected);
            const apiOk = apiLinked && apiStatus !== 'error' && apiStatus !== 'pending';
            const tagOk = this.tagManagerConnected;
            const trackOk = this.trackingScriptOk;
            const botOk = this.activeDomainStatus
                ? Boolean((this.activeDomainStatus.steps || []).find((s) => s.label === 'Analytics' || s.label === 'Bot Protection')?.done)
                : Boolean(this.botReady || this.domainConnections.some((d) => (d.steps || []).find((s) => s.label === 'Analytics' || s.label === 'Bot Protection')?.done));
            return [
                {
                    key: 'api',
                    label: 'Google Ads API',
                    ok: apiOk,
                    // Linked but never verified / token issue → Pending (not “Connected via tag”).
                    stateLabel: apiOk ? 'Healthy' : (apiLinked ? 'Pending' : 'Not connected'),
                    ago: apiLinked ? syncAgo : '—',
                },
                { key: 'gtm', label: 'Tag Manager', ok: tagOk, stateLabel: tagOk ? 'Healthy' : 'Pending', ago: eventAgo },
                { key: 'script', label: 'Tracking Script', ok: trackOk, stateLabel: trackOk ? 'Healthy' : 'Pending', ago: eventAgo },
                    { key: 'bot', label: 'Analytics', ok: botOk, stateLabel: botOk ? 'Healthy' : 'Pending', ago: eventAgo },
            ];
        },
        get setupProgressFill() {
            const steps = this.activeSetupProgress || [];
            if (steps.length < 2) return 0;
            let lastDone = -1;
            for (let i = 0; i < steps.length; i += 1) {
                if (steps[i].done) lastDone = i;
                else break;
            }
            if (lastDone <= 0) return 0;
            return Math.round((lastDone / (steps.length - 1)) * 100);
        },
        get healthPct() {
            const items = this.healthItems;
            if (!items.length) return 0;
            return Math.round((items.filter((i) => i.ok).length / items.length) * 100);
        },
        get healthLive() {
            return this.googleAdsApiHealthy && this.healthPct >= 75;
        },
        get filteredTrackingIds() {
            let rows = this.trackingIds || [];
            if (this.selectedDomainId) {
                rows = rows.filter((row) => String(row.domain_id) === String(this.selectedDomainId));
            }
            if (this.selectedAdsAccountId) {
                rows = rows.filter((row) => String(row.account_id) === String(this.selectedAdsAccountId));
            }
            return rows;
        },
        get filteredPlatformRows() {
            let rows = this.platformRows || [];
            if (this.selectedDomainId) {
                rows = rows.filter((row) => {
                    if (row.domain_id == null || row.domain_id === '') return row.kind === 'direct';
                    return String(row.domain_id) === String(this.selectedDomainId);
                });
            }
            if (this.selectedAdsAccountId) {
                rows = rows.filter((row) => {
                    if (row.account_id == null || row.account_id === '') return row.kind !== 'google_ads';
                    return String(row.account_id) === String(this.selectedAdsAccountId);
                });
            }
            const q = String(this.platformSearch || '').trim().toLowerCase();
            if (!q) return rows;
            return rows.filter((row) => String(row.search || '').includes(q)
                || String(row.platform || '').toLowerCase().includes(q)
                || String(row.account_primary || '').toLowerCase().includes(q)
                || String(row.entity_id || '').toLowerCase().includes(q));
        },
        get platformPageCount() {
            return Math.max(1, Math.ceil(this.filteredPlatformRows.length / this.platformPerPage));
        },
        get pagedPlatformRows() {
            const page = Math.min(this.platformPage, this.platformPageCount);
            const start = (page - 1) * this.platformPerPage;
            return this.filteredPlatformRows.slice(start, start + this.platformPerPage);
        },
        get platformRangeLabel() {
            const total = this.filteredPlatformRows.length;
            if (!total) return 'Showing 0 results';
            return `Showing 1 to ${total} of ${total} results`;
        },
        platformRowVisible(searchText) {
            const q = String(this.platformSearch || '').trim().toLowerCase();
            if (!q) return true;
            return String(searchText || '').includes(q);
        },
        refreshPlatforms() {
            window.location.reload();
        },
        async refreshSyncLogs() {
            try {
                const res = await fetch(config.logsUrl || '/integrations/logs', { headers: { Accept: 'application/json' } });
                const data = await res.json();
                this.syncLogs = data.logs || [];
            } catch (e) {
                this.showMenuToast('Could not refresh sync logs.', 'error');
            }
        },
        async testGoogleHealth() {
            if (!config.testUrl) {
                this.showMenuToast('Connect Google first.', 'error');
                return;
            }
            try {
                const res = await fetch(config.testUrl, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': config.csrf,
                        'Content-Type': 'application/json',
                    },
                    body: '{}',
                });
                const data = await res.json();
                if (!res.ok || !data.ok) {
                    this.showMenuToast(data.message || 'Health check failed.', 'error');
                    this.connectionHealth = {
                        ...this.connectionHealth,
                        health_status: 'error',
                        last_sync_message: data.message || 'Health check failed',
                    };
                    await this.refreshSyncLogs();
                    return;
                }
                this.connectionHealth = {
                    ...this.connectionHealth,
                    health_status: data.health_status || 'ok',
                    email: data.email || this.connectionHealth.email,
                    last_sync_at: data.last_sync_at || new Date().toISOString(),
                    last_sync_status: 'ok',
                };
                this.showMenuToast(data.message || 'Connection healthy', 'success');
                await this.refreshSyncLogs();
            } catch (e) {
                this.showMenuToast('Health check failed.', 'error');
            }
        },
        get activeDomainStatus() {
            if (!this.selectedDomainId) return null;
            return this.domainConnections.find((d) => String(d.id) === String(this.selectedDomainId)) || null;
        },
        get activeSetupProgress() {
            if (this.selectedDomainId) {
                const steps = this.setupProgressByDomain[String(this.selectedDomainId)];
                if (Array.isArray(steps) && steps.length) return steps;
            }
            return this.setupProgressAll;
        },
        get googleConnected() {
            // OAuth / Ads API only — never infer from tag script.
            return Boolean(this.googleOAuthConnected);
        },
        get googleAdsApiHealthy() {
            const status = String(this.connectionHealth.health_status || '').toLowerCase();
            return Boolean(this.googleOAuthConnected) && status !== 'error' && status !== 'pending';
        },
        get googleAdsConnected() {
            if (this.activeDomainStatus) return Boolean(this.activeDomainStatus.google_ads_connected);
            return this.domainConnections.some((d) => d.google_ads_connected);
        },
        get requirementSteps() {
            const labels = ['Tag Manager', 'Paid Marketing', 'Bot Protection', 'Google Ads'];
            if (this.activeDomainStatus) {
                return this.activeDomainStatus.steps || [];
            }
            return labels.map((label) => ({
                label,
                done: this.domainConnections.some((d) => (d.steps || []).find((s) => s.label === label)?.done),
            }));
        },
        get requirementRingPct() {
            const steps = this.requirementSteps;
            if (!steps.length) return 0;
            const done = steps.filter((s) => s.done).length;
            return Math.round((done / steps.length) * 100);
        },
        get requirementLive() {
            const steps = this.requirementSteps;
            return steps.length > 0 && steps.every((s) => s.done);
        },
        requireSelectedDomain() {
            if (!this.selectedDomainId) {
                this.showMenuToast('Select a domain from the header first.', 'error');
                return null;
            }
            const domain = this.activeDomainStatus;
            if (!domain) {
                this.showMenuToast('Selected domain not found.', 'error');
                return null;
            }
            return domain;
        },
        wpUrls(hostname) {
            const host = String(hostname || '').replace(/^https?:\/\//i, '').replace(/\/.*$/, '');
            const base = 'https://' + host;
            return {
                admin: base + '/wp-login.php?redirect_to=' + encodeURIComponent(base + '/wp-admin/'),
                settings: base + '/wp-login.php?redirect_to=' + encodeURIComponent(base + '/wp-admin/options-general.php?page=promotix-tag'),
            };
        },
        closeKeysModal() {
            this.keysModal.open = false;
        },
        async openDomainKeys(domain) {
            const wp = this.wpUrls(domain.hostname);
            this.keysModal = {
                open: true,
                id: domain.id,
                hostname: domain.hostname,
                setupUrl: `/domains/${domain.id}/setup`,
                wpAdminUrl: wp.admin,
                wpPluginSettingsUrl: wp.settings,
                rows: [
                    { label: 'Server URL', value: '…' },
                    { label: 'Domain Key', value: '…' },
                    { label: 'Secret key', value: '…' },
                    { label: 'Authentication Key', value: '…' },
                ],
            };
            try {
                const res = await fetch(`/domains/${domain.id}/api-key`, { headers: { Accept: 'application/json' } });
                const data = await res.json();
                this.keysModal.rows = [
                    { label: 'Server URL', value: data.server_url },
                    { label: 'Domain Key', value: data.domain_key },
                    { label: 'Secret key', value: data.secret_key },
                    { label: 'Authentication Key', value: data.authentication_key },
                ];
            } catch (_) {
                this.showMenuToast('Could not load domain keys.', 'error');
            }
        },
        handleRequirementClick(step) {
            const domain = this.requireSelectedDomain();
            if (!domain) return;

            const label = step.label;

            if (label === 'Tag Manager' || label === 'Bot Protection') {
                this.openDomainKeys(domain);
                return;
            }

            if (label === 'Paid Marketing' || label === 'Google Ads') {
                const domainStep = (domain.steps || []).find((s) => s.label === label);
                if (domainStep?.done) {
                    this.showMenuToast(`${label} is already connected for ${domain.hostname}.`, 'info');
                    return;
                }
                window.location.href = config.paidMarketingConnectUrl.replace('/domains/0/', `/domains/${domain.id}/`);
            }
        },
        copyKeyText(text) {
            this.copyText(text, 'Key');
        },
        copyAllKeys() {
            const blob = (this.keysModal.rows || []).map((r) => `${r.label}: ${r.value}`).join('\n');
            this.copyText(blob, 'All keys');
        },
        async verifyKeysInstallation() {
            if (!this.keysModal.id) return;
            try {
                const res = await fetch(`/domains/${this.keysModal.id}/verify-wordpress`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': config.csrf,
                        Accept: 'application/json',
                    },
                    body: JSON.stringify({}),
                });
                const data = await res.json();
                this.showMenuToast(data.verified ? 'Installation verified — reload page' : (data.message || 'Not verified'), data.verified ? 'success' : 'error');
                if (data.verified) {
                    setTimeout(() => window.location.reload(), 1200);
                }
            } catch (_) {
                this.showMenuToast('Verify request failed.', 'error');
            }
        },
        showMenuToast(message, type = 'info') {
            this.menuToast = message;
            this.menuToastType = type;
            clearTimeout(this.menuToastTimer);
            this.menuToastTimer = setTimeout(() => { this.menuToast = ''; }, 3200);
        },
        async copyText(value, label = 'Copied') {
            const text = String(value || '').trim();
            if (!text) {
                this.showMenuToast('Nothing to copy yet.', 'error');
                return false;
            }
            try {
                await navigator.clipboard.writeText(text);
                this.showMenuToast(`${label} copied.`, 'success');
                return true;
            } catch (e) {
                this.showMenuToast('Could not copy to clipboard.', 'error');
                return false;
            }
        },
        scrollToEl(id, focusSelector = null) {
            const el = document.getElementById(id);
            if (!el) return;
            el.scrollIntoView({ behavior: 'smooth', block: 'start' });
            if (focusSelector) {
                const target = el.querySelector(focusSelector) || el;
                if (target?.focus) setTimeout(() => target.focus(), 400);
            }
        },
        closeAllMenus() {
            Alpine.store('platformCardMenu').open = null;
        },
        emptyAudienceRow() {
            return { conversion_id: '', conversion_label: '', tag: '', domain_id: null };
        },
        closeAudienceModal() {
            this.audienceModal.open = false;
            this.audienceModal.error = '';
            this.audienceModal.saving = false;
        },
        addAudienceRow() {
            this.audienceModal.rows.push(this.emptyAudienceRow());
        },
        removeAudienceRow(idx) {
            if (this.audienceModal.rows.length <= 1) return;
            this.audienceModal.rows.splice(idx, 1);
        },
        onAudienceTagChange(idx, value) {
            const id = value ? Number(value) : null;
            const tag = (this.audienceModal.tags || []).find((t) => Number(t.id) === id);
            if (!this.audienceModal.rows[idx]) return;
            this.audienceModal.rows[idx].domain_id = id;
            this.audienceModal.rows[idx].tag = tag ? (tag.domain_key || tag.hostname || '') : '';
        },
        async openAudienceModal() {
            this.audienceModal.open = true;
            this.audienceModal.loading = true;
            this.audienceModal.error = '';
            try {
                const res = await fetch(this.audienceGetUrl, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                const payload = await res.json().catch(() => ({}));
                if (!res.ok || payload.ok === false) {
                    throw new Error(payload.message || 'Could not load Audience Exclusion.');
                }
                this.audienceModal.mapping_id = payload.mapping_id || null;
                this.audienceModal.tags = Array.isArray(payload.tags) ? payload.tags : [];
                this.audienceModal.rows = Array.isArray(payload.audiences) && payload.audiences.length
                    ? payload.audiences.map((r) => ({
                        conversion_id: r.conversion_id || '',
                        conversion_label: r.conversion_label || '',
                        tag: r.tag || '',
                        domain_id: r.domain_id || null,
                    }))
                    : [this.emptyAudienceRow()];
            } catch (error) {
                this.audienceModal.error = error?.message || 'Could not load Audience Exclusion.';
                this.audienceModal.rows = [this.emptyAudienceRow()];
            } finally {
                this.audienceModal.loading = false;
            }
        },
        async saveAudienceExclusion() {
            if (this.audienceModal.saving) return;
            this.audienceModal.saving = true;
            this.audienceModal.error = '';
            try {
                const res = await fetch(this.audienceSaveUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                        mapping_id: this.audienceModal.mapping_id,
                        enabled: true,
                        audiences: this.audienceModal.rows,
                    }),
                });
                const payload = await res.json().catch(() => ({}));
                if (!res.ok || payload.ok === false) {
                    throw new Error(payload.message || (payload.errors && payload.errors[0]) || 'Save failed.');
                }
                this.showMenuToast(payload.message || 'Audience Exclusion saved.', 'success');
                this.closeAudienceModal();
            } catch (error) {
                this.audienceModal.error = error?.message || 'Save failed.';
            } finally {
                this.audienceModal.saving = false;
            }
        },
        handlePlatformMenu(detail) {
            const action = detail?.action;
            if (!action) return;
            this.closeAllMenus();
            switch (action) {
                case 'google-details':
                case 'direct-details':
                    this.scrollToEl('connected-platforms');
                    break;
                case 'copy-tracking':
                    this.copyText(config.trackingLink, 'Tracking link');
                    break;
                case 'open-pixel-guard':
                    this.scrollToEl('connected-platforms');
                    this.showMenuToast('Connected platforms below.');
                    break;
                case 'open-audience-exclusion':
                    this.openAudienceModal();
                    break;
                case 'manage-ad-account':
                    this.scrollToEl('connected-platforms');
                    break;
                case 'test-google':
                    this.testGoogleConnection();
                    break;
                case 'disconnect-google':
                    this.disconnectGoogle();
                    break;
                case 'edit-direct-id': {
                    const input = document.getElementById('direct-account-id');
                    input?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    setTimeout(() => input?.focus(), 300);
                    break;
                }
                case 'copy-direct-id': {
                    const row = this.directList[0];
                    const id = row?.account_id || this.directForm.account_id;
                    this.copyText(id, 'Tracking ID');
                    break;
                }
                case 'test-direct':
                    this.testDirectTracking();
                    break;
                case 'regenerate-direct-id': {
                    const next = `AW-${Date.now().toString().slice(-9)}`;
                    this.directForm.tag_id = next;
                    const tagEl = document.getElementById('direct-tag-id');
                    if (tagEl) tagEl.focus();
                    this.showMenuToast('New conversion tag ID generated.');
                    break;
                }
                case 'remove-direct':
                    this.removeDirectPlatform();
                    break;
            }
        },
        async testGoogleConnection() {
            try {
                const res = await fetch(config.statusUrl, { headers: { Accept: 'application/json' } });
                const data = await res.json();
                const g = data?.google || {};
                const msg = g.connected
                    ? `Google connected${g.accounts ? ` · ${g.accounts} ad account(s)` : ''}.`
                    : (g.oauth_configured ? 'Google OAuth ready — connect your account.' : 'Google Ads OAuth is not configured on the server.');
                this.showMenuToast(msg);
            } catch (e) {
                this.showMenuToast('Connection test failed.');
            }
        },
        async testDirectTracking() {
            if (!this.directList.length && !this.directForm.account_id) {
                this.showMenuToast('Add a Direct Ads ID first.');
                return;
            }
            this.showMenuToast(this.directList.length
                ? `Direct Ads active · ${this.directList.length} integration(s).`
                : 'Draft ID ready — click Add to save.');
        },
        disconnectGoogle() {
            if (!config.disconnectUrl) {
                this.showMenuToast('No Google connection to remove.');
                return;
            }
            if (!confirm('Disconnect Google from this workspace?')) return;
            const form = document.getElementById('google-disconnect-form');
            if (form) form.submit();
        },
        async removeDirectPlatform() {
            const row = this.directList[0];
            if (!row?.id) {
                this.directForm = { platform: 'custom', account_label: 'Direct Ads', account_id: '', tag_id: '' };
                this.showMenuToast('Direct Ads cleared.');
                return;
            }
            if (!confirm('Remove this Direct Ads integration?')) return;
            const res = await fetch(`${config.directStoreUrl}/${row.id}`, {
                method: 'DELETE',
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': config.csrf },
            });
            if (!res.ok) {
                this.showMenuToast('Could not remove Direct Ads.');
                return;
            }
            this.directList = this.directList.filter((item) => item.id !== row.id);
            this.showMenuToast('Direct Ads removed.');
        },
        async addDirectAds() {
            const response = await fetch(config.directStoreUrl, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': config.csrf,
                },
                body: JSON.stringify(this.directForm),
            });
            if (!response.ok) return;
            const data = await response.json();
            if (data.integration) this.directList.unshift(data.integration);
            this.directForm = { platform: 'custom', account_label: 'Direct Ads', account_id: '', tag_id: '' };
        },
    };
}
</script>
@endsection
